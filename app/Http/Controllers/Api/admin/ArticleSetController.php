<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Article;
use App\Models\Admin\ArticleSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ArticleSetController extends Controller
{
    //

    public function initialize(Request $request)
    {
        // Validate request
        $request->validate([
            'name'      => 'required|string|max:255',
            'language'  => 'required|integer|exists:article_languages,id'
        ]);

        // Check duplicate domain set name
        if (ArticleSet::where('name', $request->name)->exists()) {
            return response()->json([
                "status"  => false,
                "message" => "Article set with this name already exists!"
            ], 201); // conflict status code
        }

        $articles = Article::where('article_language_id', $request->language)->get();

        if (count($articles) <= 0) {
            return response()->json([
                "status"  => false,
                "message" =>  "There are no articles related to the selected category"
            ], 201); // conflict status code
        }

        // Create domain set
        $set = ArticleSet::create([
            'name'                => $request->name,
            'article_language_id'  => $request->language,
            'admin_id'            => Auth::guard('admin')->id(),
        ]);

        return response()->json([
            "status"  => true,
            "message" => "Article set initialized successfully",
            "data"    => $set
        ], 201);
    }

    // public function setArticles(Request $request, $id)
    // {

    //     // Validate route ID
    //     if (!ctype_digit((string) $id)) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => "Invalid article set ID."
    //         ], 400);
    //     }

    //     $admin = Auth::guard('admin')->user();

    //     // Only fetch sets created by logged-in admin
    //     $articleSet = ArticleSet::withCount('articles')
    //         ->where('admin_id', $admin->id)
    //         ->find($id);

    //     if (!$articleSet) {
    //         return response()->json([
    //             "status"  => false,
    //             "message" => "Article set not found."
    //         ], 404);
    //     }

    //     // Get all articles in this set
    //     $articles = $articleSet->articles()
    //         ->select([
    //             'articles.id',
    //             'articles.name',
    //             'articles.slug',
    //             'articles.article_category_id',
    //             'articles.article_language_id',
    //             'articles.created_at',
    //         ])
    //         ->orderBy('articles.created_at', 'desc')
    //         ->get();

    //     return response()->json([
    //         "status" => true,
    //         "data"   => [
    //             "article_set" => [
    //                 "id"    => $articleSet->id,
    //                 "name"  => $articleSet->name,
    //                 "count" => $articleSet->articles_count
    //             ],
    //             "articles" => $articles
    //         ]
    //     ], 200);
    // }

    public function setArticles(Request $request, $id)
    {
        // ✅ Validate route ID
        if (!ctype_digit((string) $id)) {
            return response()->json([
                "status"  => false,
                "message" => "Invalid article set ID."
            ], 400);
        }

        $admin = Auth::guard('admin')->user();

        // ✅ Fetch article set (admin-owned only)
        $articleSet = ArticleSet::withCount('articles')
            ->where('admin_id', $admin->id)
            ->find($id);

        if (!$articleSet) {
            return response()->json([
                "status"  => false,
                "message" => "Article set not found."
            ], 404);
        }

        // ✅ Per page handling (MAX 100)
        $perPage = min(
            (int) $request->get('per_page', 10), // default 10
            100                                  // hard limit
        );

        // ✅ Paginated articles
        $articles = $articleSet->articles()
            ->whereNull('lock_at')
            ->select([
                'articles.id',
                'articles.name',
                'articles.slug',
                'articles.article_category_id',
                'articles.article_language_id',
                'articles.created_at',
            ])
            ->orderBy('articles.created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString(); // 🔥 IMPORTANT

        return response()->json([
            "status" => true,
            "data"   => [
                "article_set" => [
                    "id"    => $articleSet->id,
                    "name"  => $articleSet->name,
                    "count" => $articleSet->articles_count
                ],
                "articles" => $articles
            ]
        ], 200);
    }
}
