<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ArticleCategory;
use App\Models\Admin\ArticleLanguage;
use Illuminate\Http\Request;
use Mews\Purifier\Facades\Purifier;
use App\Models\Admin\Article;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Support\Facades\Cache;
use App\Jobs\PermanentlyDeleteTrashedUsedArticlesJob;
use App\Models\Admin;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $categories = Cache::remember('article_categories', 3600, function () {
            return ArticleCategory::select('id', 'name')->get();
        });

        $languages = Cache::remember('article_languages', 3600, function () {
            return ArticleLanguage::select('id', 'name')->get();
        });

        $limit = 100;
        $admin = Auth::guard('admin')->user();
        $query = Article::with([
            'category:id,name',
            'language:id,name',
            'admin:id,name'
        ])
            ->whereNull('articles.lock_at') // ✅ EXPLICIT
            ->whereNull('articles.deleted_at') // ✅ EXPLICIT
            ->where('articles.status', '!=', 1) // ✅ EXPLICIT
            ->when(
                !$admin->isSuperAdmin(),
                fn($q) => $q->where('articles.admin_id', $admin->id)
            ) // 
            ->orderBy('id', 'desc');

        // 🔍 Search
        if ($request->filled('search')) {
            $query->whereFullText(
                ['name', 'description'],
                $request->search
            );
        }

        // 🏷 Category filter
        if ($request->filled('category')) {
            $query->where('article_category_id', $request->category);
        }

        // 🌐 Language filter
        if ($request->filled('language')) {
            $query->where('article_language_id', $request->language);
        }

        $articles = $query->paginate($limit)->appends($request->query());

        $offset = ($articles->currentPage() - 1) * $limit;

        return view(
            'admin.article.articles',
            compact('categories', 'languages', 'articles', 'offset')
        );
    }

    /**
     * Base query: soft-deleted used articles for this admin, optional list filters, newest deleted first.
     */
    private function trashedUsedArticlesQuery(Request $request, Admin $admin)
    {
        $query = Article::onlyTrashed()
            ->where('articles.status', Article::STATUS_USED)
            ->when(
                ! $admin->isSuperAdmin(),
                fn ($q) => $q->where('articles.admin_id', $admin->id)
            )
            ->orderBy('articles.deleted_at', 'desc')
            ->orderBy('articles.id', 'desc');

        if ($request->filled('search')) {
            $query->whereFullText(
                ['name', 'description'],
                $request->search
            );
        }

        if ($request->filled('category')) {
            $query->where('article_category_id', $request->category);
        }

        if ($request->filled('language')) {
            $query->where('article_language_id', $request->language);
        }

        return $query;
    }

    /**
     * List soft-deleted **used** articles only (status = used). Permanent removal is queued.
     */
    public function trashedIndex(Request $request)
    {
        $categories = Cache::remember('article_categories', 3600, function () {
            return ArticleCategory::select('id', 'name')->get();
        });

        $languages = Cache::remember('article_languages', 3600, function () {
            return ArticleLanguage::select('id', 'name')->get();
        });

        $limit = 100;
        $admin = Auth::guard('admin')->user();

        $query = $this->trashedUsedArticlesQuery($request, $admin);

        $totalTrashedUsedMatchingFilters = (clone $query)->count();
        $articles = (clone $query)
            ->with([
                'category:id,name',
                'language:id,name',
                'admin:id,name',
            ])
            ->paginate($limit)
            ->appends($request->query());
        $offset = ($articles->currentPage() - 1) * $limit;

        $totalTrashedUsedForPurgeAll = Article::onlyTrashed()
            ->where('articles.status', Article::STATUS_USED)
            ->when(
                ! $admin->isSuperAdmin(),
                fn ($q) => $q->where('articles.admin_id', $admin->id)
            )
            ->count();

        return view(
            'admin.article.trashed-articles',
            compact(
                'categories',
                'languages',
                'articles',
                'offset',
                'totalTrashedUsedMatchingFilters',
                'totalTrashedUsedForPurgeAll'
            )
        );
    }

    /**
     * Queue permanent delete for one soft-deleted **used** article.
     */
    public function forceDestroy(string $id)
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            abort(403, 'Unauthorized action.');
        }

        $query = Article::onlyTrashed()
            ->whereKey($id)
            ->where('status', Article::STATUS_USED);
        if (! $admin->isSuperAdmin()) {
            $query->where('admin_id', $admin->id);
        }

        $article = $query->first();
        if (! $article) {
            return back()->with(
                'cus__error',
                'Deleted used article not found, or it is not in the trash, or you do not have access.'
            );
        }

        PermanentlyDeleteTrashedUsedArticlesJob::dispatch(
            [(int) $article->id],
            (int) $admin->id,
            $admin->isSuperAdmin()
        );

        return back()->with(
            'cus__success',
            'Permanent removal has been queued. Ensure the queue worker is running (queue: article_permanent_purge).'
        );
    }

    /**
     * Queue permanent delete for selected soft-deleted **used** articles.
     */
    public function forceDestroyBulk(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'bulk_ids' => 'required|string',
        ]);

        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $validated['bulk_ids'])))));
        if ($ids === []) {
            return back()->with('cus__error', 'No valid article ids were submitted.');
        }

        $query = Article::onlyTrashed()
            ->where('status', Article::STATUS_USED)
            ->whereIn('id', $ids);
        if (! $admin->isSuperAdmin()) {
            $query->where('admin_id', $admin->id);
        }

        $found = $query->pluck('id')->map(fn ($i) => (int) $i)->all();
        $missing = array_diff($ids, $found);
        if ($missing !== []) {
            return back()->with(
                'cus__error',
                'Some selected rows are not deleted-used articles or are not yours: ' . implode(', ', $missing)
            );
        }

        PermanentlyDeleteTrashedUsedArticlesJob::dispatch(
            $found,
            (int) $admin->id,
            $admin->isSuperAdmin()
        );

        $n = count($found);

        return back()->with(
            'cus__success',
            "Permanent removal of {$n} article(s) has been queued. Ensure the queue worker is running (queue: article_permanent_purge)."
        );
    }

    /**
     * Queue permanent delete for **all** soft-deleted **used** articles visible to this admin.
     */
    public function queuePurgeAllTrashedUsed(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            abort(403, 'Unauthorized action.');
        }

        $count = Article::onlyTrashed()
            ->where('status', Article::STATUS_USED)
            ->when(
                ! $admin->isSuperAdmin(),
                fn ($q) => $q->where('admin_id', $admin->id)
            )
            ->count();

        if ($count === 0) {
            return back()->with('cus__error', 'No deleted used articles are in the trash to purge.');
        }

        PermanentlyDeleteTrashedUsedArticlesJob::dispatch(
            null,
            (int) $admin->id,
            $admin->isSuperAdmin()
        );

        return back()->with(
            'cus__success',
            "Permanent removal of {$count} deleted used article(s) has been queued. Ensure the queue worker is running (queue: article_permanent_purge)."
        );
    }

    /**
     * Queue permanent delete for up to N soft-deleted used articles (newest deleted first).
     * Uses the same search/category/language filters as the current list when passed in the request.
     */
    public function queuePurgeTrashedUsedByQuantity(Request $request)
    {
        $admin = Auth::guard('admin')->user();
        if (! $admin) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:500000',
            'search'   => 'nullable|string|max:500',
            'category' => 'nullable|integer|exists:article_categories,id',
            'language' => 'nullable|integer|exists:article_languages,id',
        ]);

        $filterRequest = Request::create(
            $request->url(),
            'GET',
            array_filter(
                [
                    'search'   => $validated['search'] ?? null,
                    'category' => $validated['category'] ?? null,
                    'language' => $validated['language'] ?? null,
                ],
                fn ($v) => $v !== null && $v !== ''
            )
        );

        $query = $this->trashedUsedArticlesQuery($filterRequest, $admin);

        $available = (clone $query)->count();
        if ($available === 0) {
            return back()->with(
                'cus__error',
                'No deleted used articles match the filters for this purge.'
            );
        }

        $take = min($validated['quantity'], $available);
        $ids = (clone $query)->limit($take)->pluck('id')->map(fn ($i) => (int) $i)->all();

        PermanentlyDeleteTrashedUsedArticlesJob::dispatch(
            $ids,
            (int) $admin->id,
            $admin->isSuperAdmin()
        );

        $msg = "Queued permanent removal of {$take} article(s) (newest deleted first). Ensure the queue worker is running (queue: article_permanent_purge).";
        if ($take < $validated['quantity']) {
            $msg .= " Only {$available} matched the current filters.";
        }

        return back()->with('cus__success', $msg);
    }

    public function opt()
    {
        return view('admin.article.options');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $categories = ArticleCategory::all();
        $languages = ArticleLanguage::all();
        return view('admin.article.add-article', compact('categories', 'languages'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // ✅ Validation
        $validated = $request->validate([
            'name'        => 'required|string|max:255',   // title required
            'description' => 'nullable|string',           // HTML + emojis allowed
            'category'    => 'required|integer|exists:article_categories,id',           // adjust rule if needed
            'language'    => 'required|integer|exists:article_languages,id',        // adjust rule if needed
            'type'        => 'required|integer|in:0,1,2'
        ]);

        // ✅ Clean description (remove scripts, keep HTML)
        $description = null;
        if (!empty($validated['description'])) {
            $description = Purifier::clean($validated['description']);
        }
        $admin_id = Auth::guard('admin')->user()->id;
        // ✅ Store article
        Article::create([
            'name'        => $validated['name'],   // emojis allowed
            'description' => $description,
            'article_category_id'  => $validated['category'],
            'article_language_id' => $validated['language'],
            'type' => $validated['type'],
            'admin_id' => $admin_id
            // slug & search_text handled automatically by model events
        ]);

        Cache::forget('article_categories');
        Cache::forget('article_languages');


        return back()->with('cus__success', 'Article created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $article = Article::find($id);
        return view('admin.article.view-article', compact('article'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $article = Article::find($id);

        if (!$article) {
            return back()->with('cus__error', 'article not found');
        }

        $categories = ArticleCategory::all();
        $languages = ArticleLanguage::all();

        return view('admin.article.edit-article', compact('article', 'categories', 'languages'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // 🔍 Find article (or 404)
        $article = Article::findOrFail($id);

        // ✅ Validation
        $validated = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'category'    => 'required|integer|exists:article_categories,id',
            'language'    => 'required|integer|exists:article_languages,id',
        ]);

        // ✅ Clean description (ALLOW HEADINGS)
        $description = null;
        if (!empty($validated['description'])) {
            $description = Purifier::clean(
                $validated['description'],
                [
                    'HTML.Allowed' =>
                    'p,h1,h2,h3,h4,h5,h6,ul,ol,li,strong,em,br,a[href],blockquote,table,thead,tbody,tr,th,td'
                ]
            );
        }

        // ✅ Update article
        $article->update([
            'name'                => $validated['name'],
            'description'         => $description,
            'article_category_id' => $validated['category'],
            'article_language_id' => $validated['language'],
        ]);

        // 🧹 Clear cache
        Cache::forget('article_categories');
        Cache::forget('article_languages');

        return back()->with('cus__success', 'Article updated successfully.');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // 🔐 Ensure admin is authenticated
        $admin = Auth::guard('admin')->user();
        if (!$admin) {
            abort(403, 'Unauthorized action.');
        }

        // 🔍 Find article (404 if not found)
        $article = Article::findOrFail($id);

        // 🗑 Soft delete article
        $article->delete();

        // 🧹 Clear related caches
        Cache::forget('article_categories');
        Cache::forget('article_languages');

        return redirect()
            ->back()
            ->with('cus__success', 'Article deleted successfully.');
    }


    /* showing bulk form */

    public function uploadDocx()
    {
        $categories = ArticleCategory::all();
        $languages = ArticleLanguage::all();
        return view('admin.article.upload.upload-article', compact('categories', 'languages'));
    }


    // public function import(Request $request)
    // {   
    //     $request->validate([
    //         'docx'      => 'required|file|mimes:docx|max:20480',
    //         'category'  => 'required|integer|exists:article_categories,id',
    //         'language'  => 'required|integer|exists:article_languages,id',
    //         'type'      => 'required|integer|in:0,1,2',
    //     ]);

    //     $admin = auth('admin')->user();
    //     if (!$admin) {
    //         abort(403);
    //     }

    //     /* -------------------------------------------------
    //  | 1️⃣ Load DOCX (REAL uploaded path)
    //  |--------------------------------------------------*/
    //     $uploadedFile = $request->file('docx');
    //     $docxPath = $uploadedFile->getRealPath();

    //     if (!$docxPath || !file_exists($docxPath)) {
    //         return back()->with('cus__error', 'Invalid DOCX file.');
    //     }

    //     /* -------------------------------------------------
    //  | 2️⃣ DOCX → HTML
    //  |--------------------------------------------------*/
    //     $phpWord = IOFactory::load($docxPath);
    //     $writer  = IOFactory::createWriter($phpWord, 'HTML');

    //     $htmlPath = storage_path('app/docx_preview.html');
    //     $writer->save($htmlPath);

    //     $rawHtml = file_get_contents($htmlPath);

    //     /* -------------------------------------------------
    //  | 3️⃣ Split articles
    //  |--------------------------------------------------*/
    //     $chunks = preg_split('/\*\*\s*article starts\s*\*\*/i', $rawHtml);

    //     $created = 0;

    //     foreach ($chunks as $chunk) {

    //         if (!str_contains(strtolower($chunk), '** title **')) {
    //             continue;
    //         }

    //         // 🔹 Extract title
    //         preg_match(
    //             '/\*\*\s*title\s*\*\*(.*?)\*\*\s*description\s*\*\*/is',
    //             $chunk,
    //             $titleMatch
    //         );

    //         $title = trim(strip_tags($titleMatch[1] ?? ''));

    //         if (!$title) {
    //             continue;
    //         }

    //         // 🔹 Extract description
    //         $descriptionHtml = preg_replace(
    //             '/.*?\*\*\s*description\s*\*\*/is',
    //             '',
    //             $chunk
    //         );

    //         /* -------------------------------------------------
    //      | 4️⃣ Sanitize HTML
    //      |--------------------------------------------------*/
    //         $cleanHtml = clean($descriptionHtml, [
    //             'HTML.Allowed' =>
    //             'p,h1,h2,h3,h4,h5,h6,ul,ol,li,table,thead,tbody,tr,th,td,a,strong,em,br'
    //         ]);

    //         /* -------------------------------------------------
    //      | 5️⃣ Normalize DOCX HTML
    //      |--------------------------------------------------*/
    //         $cleanHtml = $this->normalizeDocxHtml($cleanHtml);
    //         $cleanHtml = $this->mergeBrokenParagraphs($cleanHtml);
    //         $cleanHtml = $this->convertParagraphHeadings($cleanHtml);

    //         /* -------------------------------------------------
    //      | 6️⃣ Create article (ELOQUENT)
    //      |--------------------------------------------------*/
    //         Article::create([
    //             'name'                => $title,
    //             'description'         => $cleanHtml,
    //             'article_category_id' => $request->category,
    //             'article_language_id' => $request->language,
    //             'type'                => $request->type, // 1=file_upload
    //             'status'              => 0,
    //             'admin_id'            => $admin->id,
    //         ]);

    //         $created++;
    //     }

    //     @unlink($htmlPath);

    //     Cache::forget('article_categories');
    //     Cache::forget('article_languages');

    //     return back()->with(
    //         'cus__success',
    //         "{$created} articles created successfully."
    //     );
    // }

    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'docx'      => 'required|file|mimes:docx|max:20480',
    //         'category'  => 'required|integer|exists:article_categories,id',
    //         'language'  => 'required|integer|exists:article_languages,id',
    //         'type'      => 'required|integer|in:0,1,2',
    //     ]);

    //     $admin = auth('admin')->user();
    //     if (!$admin) {
    //         abort(403);
    //     }

    //     /* -------------------------------------------------
    //  | 1️⃣ Load DOCX
    //  |--------------------------------------------------*/
    //     $uploadedFile = $request->file('docx');
    //     $docxPath = $uploadedFile->getRealPath();

    //     if (!$docxPath || !file_exists($docxPath)) {
    //         return back()->with('cus__error', 'Invalid DOCX file.');
    //     }

    //     /* -------------------------------------------------
    //  | 2️⃣ DOCX → HTML
    //  |--------------------------------------------------*/
    //     $phpWord = IOFactory::load($docxPath);
    //     $writer  = IOFactory::createWriter($phpWord, 'HTML');

    //     $htmlPath = storage_path('app/docx_preview.html');
    //     $writer->save($htmlPath);

    //     $rawHtml = file_get_contents($htmlPath);

    //     /* -------------------------------------------------
    //  | 3️⃣ Preload existing titles (GLOBAL)
    //  |--------------------------------------------------*/
    //     $existingTitles = Article::pluck('name')
    //         ->map(fn($t) => mb_strtolower(trim($t)))
    //         ->toArray();

    //     /* -------------------------------------------------
    //  | 4️⃣ Split articles
    //  |--------------------------------------------------*/
    //     $chunks = preg_split('/\*\*\s*article starts\s*\*\*/i', $rawHtml);

    //     $created         = 0;
    //     $duplicates      = 0;
    //     $duplicateTitles = [];

    //     foreach ($chunks as $chunk) {

    //         if (!str_contains(strtolower($chunk), '** title **')) {
    //             continue;
    //         }

    //         // 🔹 Extract title
    //         preg_match(
    //             '/\*\*\s*title\s*\*\*(.*?)\*\*\s*description\s*\*\*/is',
    //             $chunk,
    //             $titleMatch
    //         );

    //         $title = trim(strip_tags($titleMatch[1] ?? ''));

    //         if (!$title) {
    //             continue;
    //         }

    //         $normalizedTitle = mb_strtolower($title);

    //         // 🚫 GLOBAL DUPLICATE CHECK
    //         if (in_array($normalizedTitle, $existingTitles, true)) {
    //             $duplicates++;
    //             $duplicateTitles[] = $title;
    //             continue;
    //         }

    //         // 🔹 Extract description
    //         $descriptionHtml = preg_replace(
    //             '/.*?\*\*\s*description\s*\*\*/is',
    //             '',
    //             $chunk
    //         );

    //         /* -------------------------------------------------
    //      | 5️⃣ Sanitize HTML
    //      |--------------------------------------------------*/
    //         $cleanHtml = clean($descriptionHtml, [
    //             'HTML.Allowed' =>
    //             'p,h1,h2,h3,h4,h5,h6,ul,ol,li,table,thead,tbody,tr,th,td,a,strong,em,br'
    //         ]);

    //         /* -------------------------------------------------
    //      | 6️⃣ Normalize DOCX HTML
    //      |--------------------------------------------------*/
    //         $cleanHtml = $this->normalizeDocxHtml($cleanHtml);
    //         $cleanHtml = $this->mergeBrokenParagraphs($cleanHtml);
    //         $cleanHtml = $this->convertParagraphHeadings($cleanHtml);

    //         /* -------------------------------------------------
    //      | 7️⃣ Create article
    //      |--------------------------------------------------*/
    //         Article::create([
    //             'name'                => $title,
    //             'description'         => $cleanHtml,
    //             'article_category_id' => $request->category,
    //             'article_language_id' => $request->language,
    //             'type'                => $request->type,
    //             'status'              => 0,
    //             'admin_id'            => $admin->id,
    //         ]);

    //         // Add to in-memory list to prevent same-file duplicates
    //         $existingTitles[] = $normalizedTitle;

    //         $created++;
    //     }

    //     @unlink($htmlPath);

    //     Cache::forget('article_categories');
    //     Cache::forget('article_languages');

    //     /* -------------------------------------------------
    //  | 8️⃣ User message
    //  |--------------------------------------------------*/
    //     $message = "{$created} articles uploaded successfully.";

    //     if ($duplicates > 0) {
    //         $message .= " {$duplicates} duplicate articles were skipped.<br><br>";
    //         $message .= "<strong>Duplicate Titles:</strong><ul>";

    //         foreach ($duplicateTitles as $title) {
    //             $message .= '<li>' . e($title) . '</li>';
    //         }

    //         $message .= '</ul>';
    //     }

    //     return back()->with('cus__success', $message);
    // }

    public function import(Request $request)
    {
        $request->validate([
            'docx'      => 'required|file|mimes:docx|max:20480',
            'category'  => 'required|integer|exists:article_categories,id',
            'language'  => 'required|integer|exists:article_languages,id',
            'type'      => 'required|integer|in:0,1,2',
        ]);

        $admin = auth('admin')->user();
        if (!$admin) {
            abort(403);
        }

        /* -------------------------------------------------
     | 1️⃣ Load DOCX
     |--------------------------------------------------*/
        $uploadedFile = $request->file('docx');
        $docxPath = $uploadedFile->getRealPath();

        if (!$docxPath || !file_exists($docxPath)) {
            return back()->with('cus__error', 'Invalid DOCX file.');
        }

        /* -------------------------------------------------
     | 2️⃣ DOCX → HTML
     |--------------------------------------------------*/
        $phpWord = IOFactory::load($docxPath);
        $writer  = IOFactory::createWriter($phpWord, 'HTML');

        $htmlPath = storage_path('app/docx_preview.html');
        $writer->save($htmlPath);

        $rawHtml = file_get_contents($htmlPath);

        /* -------------------------------------------------
     | 3️⃣ Preload EXISTING TITLES (GLOBAL)
     |--------------------------------------------------*/
        $existingTitles = Article::pluck('name')
            ->map(fn($t) => mb_strtolower(trim($t)))
            ->toArray();

        /* -------------------------------------------------
     | 4️⃣ Split articles
     |--------------------------------------------------*/
        $chunks = preg_split('/\*\*\s*article starts\s*\*\*/i', $rawHtml);

        $created         = 0;
        $duplicates      = 0;
        $duplicateTitles = [];

        foreach ($chunks as $chunk) {

            if (!str_contains(strtolower($chunk), '** title **')) {
                continue;
            }

            // 🔹 Extract title
            preg_match(
                '/\*\*\s*title\s*\*\*(.*?)\*\*\s*description\s*\*\*/is',
                $chunk,
                $titleMatch
            );

            $title = trim(strip_tags($titleMatch[1] ?? ''));

            if (!$title) {
                continue;
            }

            $normalizedTitle = mb_strtolower($title);

            /* -------------------------------------------------
         | 🚫 GLOBAL DUPLICATE CHECK
         |--------------------------------------------------*/
            if (in_array($normalizedTitle, $existingTitles, true)) {
                $duplicates++;
                $duplicateTitles[] = $title;
                continue;
            }

            // 🔹 Extract description
            $descriptionHtml = preg_replace(
                '/.*?\*\*\s*description\s*\*\*/is',
                '',
                $chunk
            );

            /* -------------------------------------------------
         | 5️⃣ Sanitize HTML
         |--------------------------------------------------*/
            $cleanHtml = clean($descriptionHtml, [
                'HTML.Allowed' =>
                'p,h1,h2,h3,h4,h5,h6,ul,ol,li,table,thead,tbody,tr,th,td,a,strong,em,br'
            ]);

            /* -------------------------------------------------
         | 6️⃣ Normalize DOCX HTML
         |--------------------------------------------------*/
            $cleanHtml = $this->normalizeDocxHtml($cleanHtml);
            $cleanHtml = $this->mergeBrokenParagraphs($cleanHtml);
            $cleanHtml = $this->convertParagraphHeadings($cleanHtml);

            /* -------------------------------------------------
         | 7️⃣ Create article
         |--------------------------------------------------*/
            Article::create([
                'name'                => $title,
                'description'         => $cleanHtml,
                'article_category_id' => $request->category,
                'article_language_id' => $request->language,
                'type'                => $request->type,
                'status'              => 0,
                'admin_id'            => $admin->id,
            ]);

            // Prevent same-file duplicates
            $existingTitles[] = $normalizedTitle;

            $created++;
        }

        @unlink($htmlPath);

        Cache::forget('article_categories');
        Cache::forget('article_languages');

        /* -------------------------------------------------
     | 8️⃣ SMART USER MESSAGE (GOOD UX)
     |--------------------------------------------------*/
        if ($created === 0 && $duplicates > 0) {
            $message  = "⚠️ No new articles were uploaded.<br>";
            $message .= "All {$duplicates} articles already exist in the system and were skipped.";
        } else {
            $message = "✅ {$created} articles uploaded successfully.";

            if ($duplicates > 0) {
                $message .= " {$duplicates} duplicate articles were skipped.";
            }
        }

        // Show duplicate titles (limited for UX)
        if ($duplicates > 0) {
            $message .= "<br><br><strong>Duplicate Titles (showing up to 10):</strong><ul>";

            foreach (array_slice($duplicateTitles, 0, 10) as $title) {
                $message .= '<li>' . e($title) . '</li>';
            }

            if (count($duplicateTitles) > 10) {
                $remaining = count($duplicateTitles) - 10;
                $message .= "<li><em>+ {$remaining} more titles not shown</em></li>";
            }

            $message .= "</ul>";
        }

        return back()->with('cus__success', $message);
    }




    /* =====================================================
     | 🧼 NORMALIZATION HELPERS
     |=====================================================*/

    private function normalizeDocxHtml(string $html): string
    {
        // Remove empty paragraphs
        $html = preg_replace('/<p>\s*<\/p>/i', '', $html);

        // Remove footnote numbers like <p>14</p>
        $html = preg_replace('/<p>\s*\d+\s*<\/p>/i', '', $html);

        // Remove unicode junk
        $html = str_replace(['&nbsp;', "\u{A0}"], ' ', $html);

        // Remove broken arrows or garbage
        $html = preg_replace('/▶|◀|\/\//', '', $html);

        // Normalize spaces
        $html = preg_replace('/\s{2,}/', ' ', $html);

        return trim($html);
    }

    private function mergeBrokenParagraphs(string $html): string
    {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $paragraphs = iterator_to_array($dom->getElementsByTagName('p'));

        for ($i = 0; $i < count($paragraphs) - 1; $i++) {
            $current = $paragraphs[$i];
            $next    = $paragraphs[$i + 1];

            $currentText = trim($current->textContent);
            $nextText    = trim($next->textContent);

            // Merge if sentence is broken
            if (
                !preg_match('/[.!?]$/', $currentText) &&
                strlen($currentText) < 120
            ) {
                $current->nodeValue = $currentText . ' ' . $nextText;
                $next->parentNode->removeChild($next);
            }
        }

        return $this->innerHtml($dom);
    }

    private function convertParagraphHeadings(string $html): string
    {
        return preg_replace_callback(
            '/<p>\s*([^<]{5,100})\s*<\/p>/',
            function ($match) {
                $text = trim($match[1]);

                // Heuristic: looks like a heading
                if (
                    preg_match('/^[A-Z].*$/', $text) &&
                    !preg_match('/[.!?]$/', $text)
                ) {
                    return '<h2>' . e($text) . '</h2>';
                }

                return $match[0];
            },
            $html
        );
    }

    private function innerHtml(\DOMDocument $dom): string
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        $html = '';

        foreach ($body->childNodes as $child) {
            $html .= $dom->saveHTML($child);
        }

        return trim($html);
    }

    /* =====================================================
     |  BULK DELETE X
     |=====================================================*/
    /** Bulk Category Delete */
    public function delete(Request $request)
    {
        $validated = $request->validate([
            'actions'  => 'required|integer|in:1',   // must be 1
            'bulk_ids' => 'required|string'          // "1,3,4"
        ]);

        // Convert string to array
        $ids = array_filter(explode(',', $validated['bulk_ids']));

        // dd($ids);

        // Check which IDs exist
        $validIds = Article::whereIn('id', $ids)->pluck('id')->toArray();

        // Detect missing IDs
        $missingIds = array_diff($ids, $validIds);

        if (!empty($missingIds)) {
            $message = count($missingIds) > 1
                ? ' ids are not found in database'
                : ' id is not found in database';

            return back()->with('cus__error', implode(',', $missingIds) . $message);
        }

        // BEGIN removing logic
        $query = Article::query();

        // If not super admin
        if (Auth::guard('admin')->user()->type != '0') {
            $query->where('admin_id', Auth::guard('admin')->user()->id);
        }

        // Delete only user's allowed categories
        $query->whereIn('id', $ids)->delete();
        // END removing logic

        return back()->with('cus__success', 'Selected article/articles are deleted successfully.');
    }
}
