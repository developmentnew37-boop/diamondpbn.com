<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ArticleController extends Controller
{
    //
    // public function search(Request $request)
    // {
    //     // -----------------------------
    //     // Validate request payload
    //     // -----------------------------
    //     $validator = Validator::make($request->all(), [
    //         'search'     => 'required|string|max:150',
    //         'searchItem' => 'required|in:in_title,in_article',
    //         'searchType' => 'required|in:1,2', // 1 = similar, 2 = exact
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => $validator->errors()->first()
    //         ], 422); // ✅ correct
    //     }

    //     $admin   = Auth::guard('admin')->user();
    //     $keyword = trim($request->search);

    //     // -----------------------------
    //     // Base query
    //     // -----------------------------
    //     $query = Article::query()
    //         ->select([
    //             'articles.id',
    //             'articles.name',
    //             'articles.slug',
    //             'articles.article_category_id',
    //             'articles.article_language_id',
    //             'articles.created_at',
    //         ])
    //         ->where('articles.status', 0)
    //         ->whereNull('articles.deleted_at')
    //         ->where('articles.admin_id', $admin->id);

    //     // -----------------------------
    //     // SEARCH IN TITLE
    //     // -----------------------------
    //     if ($request->searchItem === 'in_title') {
    //         if ($request->searchType === '2') {
    //             $query->where('articles.name', $keyword);
    //         } else {
    //             $query->where('articles.name', 'LIKE', '%' . $keyword . '%');
    //         }
    //     }

    //     // -----------------------------
    //     // SEARCH IN ARTICLE CONTENT
    //     // -----------------------------
    //     if ($request->searchItem === 'in_article') {
    //         $query->whereRaw(
    //             "MATCH(articles.search_text) AGAINST(? IN BOOLEAN MODE)",
    //             [$keyword]
    //         );
    //     }

    //     // -----------------------------
    //     // Ordering
    //     // -----------------------------
    //     if ($request->searchItem === 'in_title' && $request->searchType === '1') {
    //         $query->orderByRaw(
    //             "CASE WHEN articles.name LIKE ? THEN 0 ELSE 1 END",
    //             [$keyword . '%']
    //         );
    //     }

    //     $articles = $query
    //         ->orderByDesc('articles.created_at')
    //         ->limit(200)
    //         ->get();

    //     // -----------------------------
    //     // SUCCESS (even if empty)
    //     // -----------------------------
    //     return response()->json([
    //         "status" => true,
    //         "data"   => [
    //             "meta" => [
    //                 "search"      => $keyword,
    //                 "search_in"   => $request->searchItem,
    //                 "search_type" => $request->searchType,
    //                 "total"       => $articles->count()
    //             ],
    //             "articles" => $articles
    //         ]
    //     ], 200);
    // }

    // **** ---------- *****

    // public function search(Request $request)
    // {
    //     // -----------------------------
    //     // Validate request payload
    //     // -----------------------------
    //     $validator = Validator::make($request->all(), [
    //         'search'              => 'required|string|max:150',
    //         'searchItem'          => 'required|in:in_title,in_article',
    //         'searchType'          => 'required|in:1,2',
    //         'article_category_id' => 'nullable|integer|exists:article_categories,id',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => $validator->errors()->first()
    //         ], 422);
    //     }

    //     $admin   = Auth::guard('admin')->user();
    //     $keyword = trim($request->search);

    //     // -----------------------------
    //     // Base query
    //     // -----------------------------
    //     $query = Article::query()
    //         ->select([
    //             'articles.id',
    //             'articles.name',
    //             'articles.slug',
    //             'articles.article_category_id',
    //             'articles.article_language_id',
    //             'articles.created_at',
    //         ])
    //         ->where('articles.status', 0)
    //         ->whereNull('articles.deleted_at')
    //         ->where('articles.admin_id', $admin->id);

    //     // -----------------------------
    //     // OPTIONAL CATEGORY FILTER
    //     // -----------------------------
    //     if (!is_null($request->article_category_id)) {
    //         $query->where(
    //             'articles.article_category_id',
    //             $request->article_category_id
    //         );
    //     }

    //     // -----------------------------
    //     // SEARCH IN TITLE
    //     // -----------------------------
    //     if ($request->searchItem === 'in_title') {

    //         if ($request->searchType === '2') {
    //             // exact title
    //             $query->where('articles.name', $keyword);
    //         } else {
    //             // similar title
    //             $query->where('articles.name', 'LIKE', '%' . $keyword . '%');
    //         }
    //     }

    //     // -----------------------------
    //     // SEARCH IN ARTICLE CONTENT
    //     // -----------------------------
    //     if ($request->searchItem === 'in_article') {
    //         $query->whereRaw(
    //             "MATCH(articles.search_text) AGAINST(? IN BOOLEAN MODE)",
    //             [$keyword]
    //         );
    //     }

    //     // -----------------------------
    //     // ORDERING (relevance)
    //     // -----------------------------
    //     if ($request->searchItem === 'in_title' && $request->searchType === '1') {
    //         $query->orderByRaw(
    //             "CASE WHEN articles.name LIKE ? THEN 0 ELSE 1 END",
    //             [$keyword . '%']
    //         );
    //     }

    //     $articles = $query
    //         ->orderByDesc('articles.created_at')
    //         ->limit(200)
    //         ->get();

    //     // -----------------------------
    //     // SUCCESS RESPONSE
    //     // -----------------------------
    //     return response()->json([
    //         "status" => true,
    //         "data"   => [
    //             "meta" => [
    //                 "search"      => $keyword,
    //                 "search_in"   => $request->searchItem,
    //                 "search_type" => $request->searchType,
    //                 "category_id" => $request->article_category_id,
    //                 "total"       => $articles->count(),
    //             ],
    //             "articles" => $articles
    //         ]
    //     ], 200);
    // }

    // public function search(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'search'              => 'required|string|max:150',
    //         'searchItem'          => 'required|in:in_title,in_article',
    //         'searchType'          => 'required|in:1,2',
    //         'article_category_id' => 'nullable|integer|exists:article_categories,id',
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => $validator->errors()->first()
    //         ], 422);
    //     }

    //     $admin   = Auth::guard('admin')->user();
    //     $keyword = trim($request->search);

    //     $query = Article::query()
    //         ->select([
    //             'articles.id',
    //             'articles.name',
    //             'articles.slug',
    //             'articles.article_category_id',
    //             'articles.article_language_id',
    //             'articles.created_at',
    //         ])
    //         ->where('articles.status', 0)
    //         ->whereNull('articles.deleted_at')
    //         ->where('articles.admin_id', $admin->id);

    //     if ($request->article_category_id) {
    //         $query->where('articles.article_category_id', $request->article_category_id);
    //     }

    //     if ($request->searchItem === 'in_title') {
    //         if ($request->searchType === '2') {
    //             $query->where('articles.name', $keyword);
    //         } else {
    //             $query->where('articles.name', 'LIKE', '%' . $keyword . '%');
    //         }
    //     }

    //     if ($request->searchItem === 'in_article') {
    //         $query->whereRaw(
    //             "MATCH(articles.search_text) AGAINST(? IN BOOLEAN MODE)",
    //             [$keyword]
    //         );
    //     }

    //     if ($request->searchItem === 'in_title' && $request->searchType === '1') {
    //         $query->orderByRaw(
    //             "CASE WHEN articles.name LIKE ? THEN 0 ELSE 1 END",
    //             [$keyword . '%']
    //         );
    //     }

    //     // ✅ PAGINATION (KEY CHANGE)
    //     $articles = $query
    //         ->orderByDesc('articles.created_at')
    //         ->paginate(30);

    //     return response()->json([
    //         "status" => true,
    //         "data"   => [
    //             "meta" => [
    //                 "search"      => $keyword,
    //                 "search_in"   => $request->searchItem,
    //                 "search_type" => $request->searchType,
    //                 "category_id" => $request->article_category_id,
    //                 "total"       => $articles->total(),
    //             ],
    //             "articles" => $articles
    //         ]
    //     ], 200);
    // }

    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'search' => 'required|string|max:150',
            'searchItem' => 'required|in:in_title,in_article',
            'searchType' => 'required|in:1,2',
            'article_category_id' => 'nullable|integer|exists:article_categories,id',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $keyword = trim($request->search);
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        $query = Article::query()
            ->select([
                'articles.id',
                'articles.name',
                'articles.slug',
                'articles.article_category_id',
                'articles.article_language_id',
                'articles.created_at',
            ])
            ->where('articles.status', 0)
            ->whereNull('articles.lock_at');

        if ($request->article_category_id) {
            $query->where('articles.article_category_id', $request->article_category_id);
        }

        if ($request->searchItem === 'in_title') {
            if ($request->searchType === '2') {
                $query->where('articles.name', $keyword);
            } else {
                $query->where('articles.name', 'LIKE', '%'.$keyword.'%');
                $query->orderByRaw(
                    'CASE WHEN articles.name LIKE ? THEN 0 ELSE 1 END',
                    [$keyword.'%']
                );
            }
        }

        if ($request->searchItem === 'in_article') {
            $boolean = $this->toFullTextBoolean($keyword);
            $query->whereRaw(
                'MATCH(articles.search_text) AGAINST(? IN BOOLEAN MODE)',
                [$boolean]
            );
        }

        $articles = $query
            ->orderByDesc('articles.created_at')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => [
                'meta' => [
                    'search' => $keyword,
                    'search_in' => $request->searchItem,
                    'search_type' => $request->searchType,
                    'category_id' => $request->article_category_id,
                    'total' => $articles->total(),
                ],
                'articles' => $articles,
            ],
        ], 200);
    }

    /**
     * Get articles by language ID
     */
    public function getByLanguage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'language_id' => 'required|integer|exists:article_languages,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $languageId = (int) $request->language_id;
        $perPage = min(max((int) $request->input('per_page', 50), 1), 100);

        // SoftDeletes already adds deleted_at IS NULL — avoid duplicate filters.
        $articles = Article::query()
            ->select([
                'articles.id',
                'articles.name',
                'articles.slug',
                'articles.article_category_id',
                'articles.article_language_id',
                'articles.created_at',
            ])
            ->where('articles.status', 0)
            ->whereNull('articles.lock_at')
            ->where('articles.article_language_id', $languageId)
            ->orderByDesc('articles.created_at')
            ->paginate($perPage);

        $total = $articles->total();

        return response()->json([
            'status' => true,
            'data' => [
                'meta' => [
                    'language_id' => $languageId,
                    'total' => $total,
                    'available' => $total,
                    'current_page' => $articles->currentPage(),
                    'last_page' => $articles->lastPage(),
                    'per_page' => $articles->perPage(),
                ],
                'articles' => $articles->items(),
            ],
        ], 200);
    }

    /**
     * Build a safer FULLTEXT boolean query from free text.
     */
    private function toFullTextBoolean(string $keyword): string
    {
        $tokens = preg_split('/\s+/u', trim($keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];

        foreach ($tokens as $token) {
            $clean = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $token);
            if ($clean === null || $clean === '') {
                continue;
            }

            $parts[] = '+'.$clean.'*';
        }

        return $parts !== [] ? implode(' ', $parts) : $keyword;
    }
}
