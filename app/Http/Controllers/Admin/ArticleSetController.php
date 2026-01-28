<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Admin\ArticleLanguage;
use App\Models\Admin\ArticleSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Mews\Purifier\Facades\Purifier;
use App\Models\Admin\Article;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ArticleSetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     //
    //     $user = Auth::guard('admin')->user();

    //     $articleSets = ArticleSet::withCount('articles')
    //         ->when($user->type != 0, function ($query) use ($user) {
    //             $query->where('admin_id', $user->id);
    //         })
    //         ->orderBy('id', 'desc')
    //         ->paginate(10);
    //     $languages = ArticleLanguage::all();
    //     $users = Admin::all();
    //     return view('admin.article.article-set', compact('languages', 'users', 'articleSets'));
    // }

    public function index(Request $request)
    {
        $user = Auth::guard('admin')->user();

        // ✅ read filters from query string
        $filterUser     = $request->query('user');      // admin_id
        $filterLanguage = $request->query('language');  // article_language_id
        $search         = $request->query('search');    // set name / slug etc.

        $articleSets = ArticleSet::query()
            ->withCount('articles')

            // ✅ permission: non-super admin sees only own sets
            ->when((int) $user->type !== 0, fn($q) => $q->where('admin_id', $user->id))

            // ✅ filter: selected user (only if super admin)
            ->when((int) $user->type === 0 && filled($filterUser), function ($q) use ($filterUser) {
                $q->where('admin_id', (int) $filterUser);
            })

            // ✅ filter: selected language
            ->when(filled($filterLanguage), function ($q) use ($filterLanguage) {
                $q->where('article_language_id', (int) $filterLanguage);
            })

            // ✅ filter: search (name/slug)
            ->when(filled($search), function ($q) use ($search) {
                $q->where(function ($qq) use ($search) {
                    $qq->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                });
            })

            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString(); // ✅ keep filters on pagination links

        $languages = ArticleLanguage::select('id', 'name')->orderBy('name')->get();

        // ✅ Only super admin can filter by users; otherwise keep empty collection (optional)
        $users = ((int) $user->type === 0)
            ? Admin::select('id', 'name')->orderBy('name')->get()
            : collect();

        return view('admin.article.article-set', compact('languages', 'users', 'articleSets'));
    }

    /**
     * showing options to create and add articles sets
     */

    public function option(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|exists:article_sets,id'
        ]);

        $articleSet = ArticleSet::with('articles')->findOrFail($validated['id']);

        $articles = $articleSet->articles()
            ->orderBy('articles.id', 'desc')
            ->get();


        return view(
            'admin.article.article-set.article-set-option',
            compact('articles', 'articleSet')
        );
    }

    /* add articles in article set controller */



    public function createArticles(Request $request)
    {
        // 1️⃣ Validate input
        $validator = Validator::make($request->all(), [
            'id'          => 'required|integer|exists:article_sets,id',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'language'    => 'required|integer|exists:article_languages,id',
            'type'        => 'required|integer|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.articles.set.create.options', [
                    'id'   => $request->input('id'),
                    'type' => match ((int) $request->input('type')) {
                        0 => 'manual',
                        1 => 'file',
                        2 => 'ai',
                    },
                ])
                ->withErrors($validator)
                ->withInput()
                ->with('cus__error', 'Enter the data properly when adding articles manually');
        }

        // 2️⃣ Get validated data
        $validated = $validator->validated();
        $adminId   = Auth::guard('admin')->id();

        // 3️⃣ Clean description (HTML allowed)
        $description = $validated['description']
            ? Purifier::clean($validated['description'])
            : null;

        // 4️⃣ Check for existing article (duplicate prevention)
        $article = Article::where('admin_id', $adminId)
            ->where('article_language_id', $validated['language'])
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($validated['name']))])
            ->first();

        $isDuplicateArticle = false;

        // 5️⃣ Create article only if not exists
        if (!$article) {
            $article = Article::create([
                'name'                => trim($validated['name']),
                'description'         => $description,
                'article_language_id' => $validated['language'],
                'type'                => $validated['type'],
                'admin_id'            => $adminId,
                'lock_at' => now(),
            ]);
        } else {
            $isDuplicateArticle = true;
        }

        // 6️⃣ Attach article to set safely (no duplicate pivot)
        $articleSet = ArticleSet::findOrFail($validated['id']);

        $isAlreadyAttached = $articleSet
            ->articles()
            ->where('articles.id', $article->id)
            ->exists();

        if (! $isAlreadyAttached) {
            $articleSet->articles()->attach($article->id);
        }

        // 7️⃣ Build messages
        if ($isDuplicateArticle && $isAlreadyAttached) {
            $mainMessage = '⚠️ Article already exists and was skipped.';
        } elseif ($isDuplicateArticle && ! $isAlreadyAttached) {
            $mainMessage = 'ℹ️ Article already existed and was attached to this set.';
        } else {
            $mainMessage = '✅ Article created and attached successfully.';
        }

        // 8️⃣ Redirect with BOTH messages
        return redirect()
            ->route('admin.articles.set.create.options', [
                'id'   => $validated['id'],
                'type' => match ((int) $validated['type']) {
                    0 => 'manual',
                    1 => 'file',
                    2 => 'ai',
                },
            ])
            ->with('cus__success', $mainMessage)
            ->with('manual_success', 'Manual article process completed successfully.');
    }




    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $articleSet = ArticleSet::with('articles')->findOrFail($id);
        $articles = $articleSet->articles()
            ->orderBy('articles.id', 'desc')
            ->get();
        return view('admin.article.article-set.articleset-articles', compact('articles', 'articleSet'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $articleSet = ArticleSet::with('articles:id')->findOrFail($id);

        $articleIds = $articleSet->articles->pluck('id')->all();

        DB::transaction(function () use ($articleSet, $articleIds) {

            // ✅ remove pivot links for this set
            $articleSet->articles()->detach();

            // ✅ delete set
            $articleSet->delete();

            // ✅ delete only those articles which are not linked to ANY other set
            if (!empty($articleIds)) {
                $deletableIds = Article::whereIn('id', $articleIds)
                    ->whereDoesntHave('articleSets') // no more relations
                    ->pluck('id')
                    ->all();

                if (!empty($deletableIds)) {
                    Article::whereIn('id', $deletableIds)->delete();
                }
            }
        });

        return back()->with('cus__success', 'Set deleted. Orphan articles were also deleted.');
    }

    public function articleDestroy(Request $request, string $id)
    {
        // $id = article ID
        $request->validate([
            'set_id' => 'required|integer|exists:article_sets,id',
        ]);

        $articleSet = ArticleSet::findOrFail($request->set_id);

        // ✅ Detach article from this set ONLY
        $articleSet->articles()->detach($id);

        return back()->with(
            'cus__success',
            'Article removed from the set successfully.'
        );
    }

    /** bulk deleting the articles of set **/

    // public function deleteSetArticles(Request $request)
    // {
    //     $validated = $request->validate([
    //         'actions'        => 'required|integer|in:1',
    //         'bulk_ids'       => 'required|string', // "1,3,4"
    //         'article_set_id' => 'required|integer|exists:article_sets,id',
    //     ]);

    //     $admin = Auth::guard('admin')->user();

    //     // "1, 3,4" => [1,3,4]
    //     $ids = collect(explode(',', $validated['bulk_ids']))
    //         ->map(fn($v) => (int) trim($v))
    //         ->filter(fn($v) => $v > 0)
    //         ->unique()
    //         ->values()
    //         ->all();

    //     if (empty($ids)) {
    //         return back()->with('cus__error', 'No valid IDs provided.');
    //     }

    //     $articleSet = ArticleSet::findOrFail($validated['article_set_id']);

    //     // ✅ Eloquent relationship query (NOT DB::table)
    //     $articlesQuery = $articleSet->articles()->whereIn('articles.id', $ids);

    //     // Permission: non-super admin can delete only their own articles
    //     if ((int) $admin->type !== 0) {
    //         $articlesQuery->where('articles.admin_id', $admin->id);
    //     }

    //     // Only articles that are in THIS set and allowed
    //     $articles = $articlesQuery->get(['articles.id']);

    //     $allowedIds = $articles->pluck('id')->all();

    //     // Detect missing/forbidden ids
    //     $missingOrForbidden = array_values(array_diff($ids, $allowedIds));
    //     if (!empty($missingOrForbidden)) {
    //         return back()->with(
    //             'cus__error',
    //             'These IDs are not found in this set or not allowed: ' . implode(',', $missingOrForbidden)
    //         );
    //     }

    //     DB::transaction(function () use ($articleSet, $allowedIds) {

    //         // ✅ remove from pivot for THIS set
    //         $articleSet->articles()->detach($allowedIds);

    //         // ✅ delete from DB
    //         Article::destroy($allowedIds); // Eloquent delete by PKs
    //     });

    //     return back()->with('cus__success', 'Selected articles removed from set and deleted successfully.');
    // }

    // public function deleteSetArticles(Request $request)
    // {
    //     $validated = $request->validate([
    //         'actions'        => 'required|integer|in:1',
    //         'bulk_ids'       => 'required|string',
    //         'article_set_id' => 'required|integer|exists:article_sets,id',
    //     ]);

    //     $admin = Auth::guard('admin')->user();

    //     // Normalize IDs
    //     $ids = collect(explode(',', $validated['bulk_ids']))
    //         ->map(fn($v) => (int) trim($v))
    //         ->filter(fn($v) => $v > 0)
    //         ->unique()
    //         ->values()
    //         ->all();

    //     if (empty($ids)) {
    //         return back()->with('cus__error', 'No valid IDs provided.');
    //     }

    //     $articleSet = ArticleSet::findOrFail($validated['article_set_id']);

    //     // Only articles that belong to THIS set
    //     $articlesQuery = $articleSet->articles()->whereIn('articles.id', $ids);

    //     // Permission: non-super admin can delete only own articles
    //     if ((int) $admin->type !== 0) {
    //         $articlesQuery->where('articles.admin_id', $admin->id);
    //     }

    //     $allowedIds = $articlesQuery->pluck('articles.id')->all();

    //     $missingOrForbidden = array_diff($ids, $allowedIds);
    //     if (!empty($missingOrForbidden)) {
    //         return back()->with(
    //             'cus__error',
    //             'These IDs are not found in this set or not allowed: ' . implode(',', $missingOrForbidden)
    //         );
    //     }

    //     DB::transaction(function () use ($articleSet, $allowedIds) {

    //         // 1️⃣ Detach from THIS article set
    //         $articleSet->articles()->detach($allowedIds);

    //         // 2️⃣ Find orphan articles (not linked to ANY other set)
    //         $orphanIds = Article::whereIn('id', $allowedIds)
    //             ->whereDoesntHave('articleSets')
    //             ->pluck('id')
    //             ->all();

    //         if (!empty($orphanIds)) {

    //             // 🔓 UNLOCK orphan articles
    //             Article::whereIn('id', $orphanIds)
    //                 ->update(['lock_at' => null]);

    //             // 🗑️ DELETE orphan articles (hard delete)
    //             Article::whereIn('id', $orphanIds)->forceDelete();
    //         }
    //     });

    //     return back()->with(
    //         'cus__success',
    //         'Selected articles removed from set. Orphan articles were unlocked and deleted.'
    //     );
    // }

    public function deleteSetArticles(Request $request)
    {
        $validated = $request->validate([
            'actions'        => 'required|integer|in:1',
            'bulk_ids'       => 'required|string',
            'article_set_id' => 'required|integer|exists:article_sets,id',
        ]);

        $admin = Auth::guard('admin')->user();

        $ids = collect(explode(',', $validated['bulk_ids']))
            ->map(fn($v) => (int) trim($v))
            ->filter(fn($v) => $v > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return back()->with('cus__error', 'No valid IDs provided.');
        }

        $articleSet = ArticleSet::findOrFail($validated['article_set_id']);

        $articlesQuery = $articleSet->articles()->whereIn('articles.id', $ids);

        if ((int) $admin->type !== 0) {
            $articlesQuery->where('articles.admin_id', $admin->id);
        }

        $allowedIds = $articlesQuery->pluck('articles.id')->all();

        if (count($allowedIds) !== count($ids)) {
            return back()->with(
                'cus__error',
                'Some articles are not found in this set or not allowed.'
            );
        }

        DB::transaction(function () use ($articleSet, $allowedIds) {

            // 1️⃣ Detach
            $articleSet->articles()->detach($allowedIds);
        });

        // 🔥 IMPORTANT: NEW QUERY OUTSIDE TRANSACTION
        $orphanIds = Article::whereIn('id', $allowedIds)
            ->whereDoesntHave('articleSets')
            ->pluck('id')
            ->all();

        if (!empty($orphanIds)) {

            // 🔓 Unlock
            Article::whereIn('id', $orphanIds)
                ->update(['lock_at' => null]);

            // 🗑️ HARD DELETE
            Article::whereIn('id', $orphanIds)
                ->forceDelete();
        }

        return back()->with(
            'cus__success',
            'Selected articles removed from set. Orphan articles were unlocked and deleted.'
        );
    }



    /*xxxxxxxxxxxxxxxxxxxx --- xxxxxxxxxxxxxxxxxxxxxxxxxxxxx*/

    // public function import(Request $request)
    // {
    //     // ✅ Resolve popup type (for UI) early (works even if validation fails)
    //     $typeKey = (int) $request->input('type', 0);

    //     $popupType = match ($typeKey) {
    //         0 => 'manual',
    //         1 => 'file',
    //         2 => 'ai',
    //         default => 'manual',
    //     };

    //     // ✅ Manual validator (so we can redirect with query params)
    //     $validator = Validator::make($request->all(), [
    //         'id'        => 'required|integer|exists:article_sets,id',
    //         'docx'      => 'required|file|mimes:docx|max:20480',
    //         'language'  => 'required|integer|exists:article_languages,id',
    //         'type'      => 'required|integer|in:0,1,2',
    //     ]);

    //     // ❌ Validation fail → go back to options page WITH id + type (popup control)
    //     if ($validator->fails()) {
    //         return redirect()
    //             ->route('admin.articles.set.create.options', [
    //                 'id'   => $request->input('id'),
    //                 'type' => $popupType,
    //             ])
    //             ->withErrors($validator)
    //             ->withInput()
    //             ->with('cus__error', 'Please fix the errors and try again.')
    //             ->with('bulk__error', 'please upload the docx file carefully.');
    //     }

    //     $admin = auth('admin')->user();
    //     if (!$admin) {
    //         abort(403);
    //     }

    //     $validated = $validator->validated();

    //     // ✅ Get article set once
    //     $articleSet = ArticleSet::findOrFail($validated['id']);

    //     // ✅ Load DOCX (REAL uploaded path)
    //     $uploadedFile = $request->file('docx');
    //     $docxPath = $uploadedFile?->getRealPath();

    //     if (!$docxPath || !file_exists($docxPath)) {
    //         return redirect()
    //             ->route('admin.articles.set.create.options', [
    //                 'id'   => $validated['id'],
    //                 'type' => $popupType,
    //             ])
    //             ->with('cus__error', 'Invalid DOCX file.');
    //     }

    //     // ✅ DOCX → HTML
    //     $phpWord = IOFactory::load($docxPath);
    //     $writer  = IOFactory::createWriter($phpWord, 'HTML');

    //     // Note: this filename can collide if 2 admins import at same time.
    //     // Keep it unique:
    //     $htmlPath = storage_path('app/docx_preview_' . uniqid() . '.html');

    //     $writer->save($htmlPath);
    //     $rawHtml = file_get_contents($htmlPath);

    //     // ✅ Split articles
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

    //         // ✅ Sanitize HTML
    //         $cleanHtml = clean($descriptionHtml, [
    //             'HTML.Allowed' =>
    //             'p,h1,h2,h3,h4,h5,h6,ul,ol,li,table,thead,tbody,tr,th,td,a,strong,em,br'
    //         ]);

    //         // ✅ Normalize DOCX HTML (your helpers)
    //         $cleanHtml = $this->normalizeDocxHtml($cleanHtml);
    //         $cleanHtml = $this->mergeBrokenParagraphs($cleanHtml);
    //         $cleanHtml = $this->convertParagraphHeadings($cleanHtml);

    //         // ✅ Create article
    //         $article = Article::create([
    //             'name'                => $title,
    //             'description'         => $cleanHtml,
    //             'article_language_id' => $validated['language'],
    //             'type'                => $validated['type'], // 0/1/2 stored in DB
    //             'status'              => 0,
    //             'admin_id'            => $admin->id,
    //         ]);

    //         // ✅ Pivot insert (attach article to set)
    //         $articleSet->articles()->syncWithoutDetaching([$article->id]);

    //         $created++;
    //     }

    //     @unlink($htmlPath);

    //     // ✅ Success → redirect WITH id + type so popup can open again (if you want)
    //     return redirect()
    //         ->route('admin.articles.set.create.options', [
    //             'id'   => $validated['id'],
    //             'type' => $popupType,
    //         ])
    //         ->with('cus__success', "{$created} articles created & attached to set successfully.")
    //         ->with('bulk__success', "Articles uplaoded successfully.");
    // }

    // public function import(Request $request)
    // {
    //     // ✅ Resolve popup type (for UI)
    //     $typeKey = (int) $request->input('type', 0);

    //     $popupType = match ($typeKey) {
    //         0 => 'manual',
    //         1 => 'file',
    //         2 => 'ai',
    //         default => 'manual',
    //     };

    //     // ✅ Manual validator
    //     $validator = Validator::make($request->all(), [
    //         'id'        => 'required|integer|exists:article_sets,id',
    //         'docx'      => 'required|file|mimes:docx|max:20480',
    //         'language'  => 'required|integer|exists:article_languages,id',
    //         'type'      => 'required|integer|in:0,1,2',
    //     ]);

    //     if ($validator->fails()) {
    //         return redirect()
    //             ->route('admin.articles.set.create.options', [
    //                 'id'   => $request->input('id'),
    //                 'type' => $popupType,
    //             ])
    //             ->withErrors($validator)
    //             ->withInput()
    //             ->with('cus__error', 'Please fix the errors and try again.')
    //             ->with('bulk__error', 'Please upload the DOCX file carefully.');
    //     }

    //     $admin = auth('admin')->user();
    //     if (!$admin) {
    //         abort(403);
    //     }

    //     $validated = $validator->validated();

    //     // ✅ Get article set once
    //     $articleSet = ArticleSet::findOrFail($validated['id']);

    //     // ✅ Load DOCX
    //     $uploadedFile = $request->file('docx');
    //     $docxPath = $uploadedFile?->getRealPath();

    //     if (!$docxPath || !file_exists($docxPath)) {
    //         return redirect()
    //             ->route('admin.articles.set.create.options', [
    //                 'id'   => $validated['id'],
    //                 'type' => $popupType,
    //             ])
    //             ->with('cus__error', 'Invalid DOCX file.');
    //     }

    //     // ✅ DOCX → HTML
    //     $phpWord = IOFactory::load($docxPath);
    //     $writer  = IOFactory::createWriter($phpWord, 'HTML');

    //     $htmlPath = storage_path('app/docx_preview_' . uniqid() . '.html');
    //     $writer->save($htmlPath);

    //     $rawHtml = file_get_contents($htmlPath);

    //     /* -------------------------------------------------
    //  | 🔒 PRELOAD EXISTING TITLES (GLOBAL, CASE-INSENSITIVE)
    //  |--------------------------------------------------*/
    //     $existingTitles = Article::pluck('name')
    //         ->map(fn($t) => mb_strtolower(trim($t)))
    //         ->toArray();

    //     // ✅ Split articles
    //     $chunks = preg_split('/\*\*\s*article starts\s*\*\*/i', $rawHtml);

    //     $created    = 0;
    //     $duplicates = 0;

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

    //         /* -------------------------------------------------
    //      | 🚫 GLOBAL DUPLICATE BLOCK (HARD STOP)
    //      |--------------------------------------------------*/
    //         if (in_array($normalizedTitle, $existingTitles, true)) {
    //             $duplicates++;
    //             continue; // ❌ DO NOT CREATE OR ATTACH
    //         }

    //         // 🔹 Extract description
    //         $descriptionHtml = preg_replace(
    //             '/.*?\*\*\s*description\s*\*\*/is',
    //             '',
    //             $chunk
    //         );

    //         // ✅ Sanitize HTML
    //         $cleanHtml = clean($descriptionHtml, [
    //             'HTML.Allowed' =>
    //             'p,h1,h2,h3,h4,h5,h6,ul,ol,li,table,thead,tbody,tr,th,td,a,strong,em,br'
    //         ]);

    //         // ✅ Normalize DOCX HTML
    //         $cleanHtml = $this->normalizeDocxHtml($cleanHtml);
    //         $cleanHtml = $this->mergeBrokenParagraphs($cleanHtml);
    //         $cleanHtml = $this->convertParagraphHeadings($cleanHtml);

    //         // ✅ Create article (ONLY IF UNIQUE)
    //         $article = Article::create([
    //             'name'                => $title,
    //             'description'         => $cleanHtml,
    //             'article_language_id' => $validated['language'],
    //             'type'                => $validated['type'],
    //             'status'              => 0,
    //             'lock_at' => now(),
    //             'admin_id'            => $admin->id
    //         ]);

    //         // ✅ Attach to article set
    //         $articleSet->articles()->syncWithoutDetaching([$article->id]);

    //         // ✅ Prevent same-file duplicates
    //         $existingTitles[] = $normalizedTitle;

    //         $created++;
    //     }

    //     @unlink($htmlPath);

    //     /* -------------------------------------------------
    //  | ✅ FINAL REDIRECT + MESSAGE
    //  |--------------------------------------------------*/
    //     $message = "{$created} articles created and attached to the set successfully.";

    //     if ($duplicates > 0) {
    //         $message .= " {$duplicates} duplicate articles were skipped.";
    //     }

    //     return redirect()
    //         ->route('admin.articles.set.create.options', [
    //             'id'   => $validated['id'],
    //             'type' => $popupType,
    //         ])
    //         ->with('cus__success', $message)
    //         ->with('bulk__success', 'Article import completed.');
    // }

    public function import(Request $request)
    {
        // ✅ Resolve popup type (UI)
        $typeKey = (int) $request->input('type', 0);
        $popupType = match ($typeKey) {
            0 => 'manual',
            1 => 'file',
            2 => 'ai',
            default => 'manual',
        };

        // ✅ Validation
        $validator = Validator::make($request->all(), [
            'id'        => 'required|integer|exists:article_sets,id',
            'docx'      => 'required|file|mimes:docx|max:20480',
            'language'  => 'required|integer|exists:article_languages,id',
            'type'      => 'required|integer|in:0,1,2',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.articles.set.create.options', [
                    'id'   => $request->input('id'),
                    'type' => $popupType,
                ])
                ->withErrors($validator)
                ->withInput()
                ->with('cus__error', 'Please fix the errors and try again.')
                ->with('bulk__error', 'Please upload the DOCX file carefully.');
        }

        $admin = auth('admin')->user();
        abort_if(!$admin, 403);

        $validated = $validator->validated();
        $articleSet = ArticleSet::findOrFail($validated['id']);

        // ✅ Load DOCX
        $docxPath = $request->file('docx')->getRealPath();
        if (!file_exists($docxPath)) {
            return back()->with('cus__error', 'Invalid DOCX file.');
        }

        // ✅ DOCX → HTML
        $phpWord = IOFactory::load($docxPath);
        $writer  = IOFactory::createWriter($phpWord, 'HTML');

        $htmlPath = storage_path('app/docx_preview_' . uniqid() . '.html');
        $writer->save($htmlPath);

        $rawHtml = file_get_contents($htmlPath);

        /* -------------------------------------------------
     | 🔒 PRELOAD SLUG COUNTS (INCLUDING SOFT-DELETED)
     |--------------------------------------------------*/
        $slugStats = Article::withTrashed()
            ->selectRaw('slug, COUNT(*) as total')
            ->groupBy('slug')
            ->pluck('total', 'slug')
            ->toArray();

        // ✅ Split articles
        $chunks = preg_split('/\*\*\s*article starts\s*\*\*/i', $rawHtml);

        $created    = 0;
        $duplicates = 0;

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

            $baseSlug = Str::slug($title);

            if ($baseSlug === '') {
                $duplicates++;
                continue;
            }

            /* -------------------------------------------------
         | 🚫 SCENARIO 1: ACTIVE ARTICLE EXISTS → SKIP
         |--------------------------------------------------*/
            $activeExists = Article::where('slug', $baseSlug)
                ->whereNull('deleted_at')
                ->exists();

            if ($activeExists) {
                $duplicates++;
                continue;
            }

            /* -------------------------------------------------
         | ✅ SCENARIO 2: ONLY SOFT-DELETED EXISTS → NEW SLUG
         |--------------------------------------------------*/
            $finalSlug = $baseSlug;

            if (isset($slugStats[$baseSlug])) {
                $finalSlug = $baseSlug . '-' . $slugStats[$baseSlug];
            }

            // increment slug usage
            $slugStats[$baseSlug] = ($slugStats[$baseSlug] ?? 0) + 1;

            // 🔹 Extract description
            $descriptionHtml = preg_replace(
                '/.*?\*\*\s*description\s*\*\*/is',
                '',
                $chunk
            );

            // ✅ Clean HTML
            $cleanHtml = clean($descriptionHtml, [
                'HTML.Allowed' =>
                'p,h1,h2,h3,h4,h5,h6,ul,ol,li,table,thead,tbody,tr,th,td,a,strong,em,br'
            ]);

            // Normalize DOCX HTML
            $cleanHtml = $this->normalizeDocxHtml($cleanHtml);
            $cleanHtml = $this->mergeBrokenParagraphs($cleanHtml);
            $cleanHtml = $this->convertParagraphHeadings($cleanHtml);

            // ✅ CREATE ARTICLE
            $article = Article::create([
                'name'                => $title,
                'slug'                => $finalSlug, // 🔥 explicit
                'description'         => $cleanHtml,
                'article_language_id' => $validated['language'],
                'type'                => $validated['type'],
                'status'              => 0,
                'lock_at'             => now(),
                'admin_id'            => $admin->id,
            ]);

            // ✅ Attach to set
            $articleSet->articles()->syncWithoutDetaching([$article->id]);

            $created++;
        }

        @unlink($htmlPath);

        /* -------------------------------------------------
     | ✅ FINAL MESSAGE
     |--------------------------------------------------*/
        $message = "{$created} articles created and attached successfully.";

        if ($duplicates > 0) {
            $message .= " {$duplicates} articles were skipped.";
        }

        return redirect()
            ->route('admin.articles.set.create.options', [
                'id'   => $validated['id'],
                'type' => $popupType,
            ])
            ->with('cus__success', $message)
            ->with('bulk__success', 'Article import completed.');
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
}
