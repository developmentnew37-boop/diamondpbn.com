<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin\ArticleCategory;
use Illuminate\Support\Facades\Auth;


class ArticleCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Validation
        $request->validate([
            'search' => 'nullable|string|max:150',
        ]);

        $parentcat = ArticleCategory::select('id', 'name')->get();
        $limit = 8;

        // Build Query
        $query = ArticleCategory::query();

        if ($request->filled('search')) {
            $keyword = "%{$request->search}%";
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', $keyword)
                    ->orWhere('slug', 'LIKE', $keyword)
                    ->orWhere('description', 'LIKE', $keyword);
            });
        }

        // Pagination + append filters
        $categories = $query->orderBy('id', 'desc')
            ->paginate($limit)
            ->appends($request->all());

        // OFFSET for table numbering
        $offset = ($categories->currentPage() - 1) * $limit;

        return view('admin.article.category.category', compact('parentcat', 'categories', 'offset'));
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
        $admin = Auth::guard('admin')->user();

        // Allow only type 0 or 1
        if (!in_array($admin->type, [0, 1])) {
            return back()->with('cus__error', 'You are not authorized to create categories.');
        }

        // Validate input
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:article_categories,name',
            'parent_id'   => 'nullable|exists:article_categories,id',
            'description' => 'nullable|string',
        ]);

        // Create category (slug will generate automatically)
        ArticleCategory::create([
            'name'        => $validated['name'],
            'parent_id'   => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'admin_id'    => $admin->id,
        ]);

        return back()->with('cus__success', 'Article Category created successfully.');
    }


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
      
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $category = ArticleCategory::find($id);
        $parentcat = ArticleCategory::select('id', 'name')->get();
        return  view('admin.article.category.edit-category', compact('parentcat', 'category'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $admin = Auth::guard('admin')->user();

        if (!in_array($admin->type, [0, 1])) {
            return back()->with('cus__error', 'You are not authorized to create categories.');
        }


        $category = ArticleCategory::find($id);

        if (!$category) {
            return back()->with('cus__error', 'Invalid Category Id');
        }

        // Allow only type 0 or 1

        // Validate input
        $validated = $request->validate([
            'name'        => 'required|string|max:255|unique:article_categories,name,' . $id,
            'parent_id'   => 'nullable|exists:article_categories,id',
            'description' => 'nullable|string',
        ]);

        // Create category (slug will generate automatically)
        $category->update([
            'name'        => $validated['name'],
            'parent_id'   => $validated['parent_id'] ?? null,
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('admin.articles.category.index')->with('cus__success', 'Article category updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Validate route ID (ensure number)
        if (!ctype_digit($id)) {
            return back()->with('cus__error', 'Invalid category ID.');
        }

        // Check category existence
        $category = ArticleCategory::find($id);

        if (!$category) {
            return back()->with('cus__error', 'category not found.');
        }

        $admin = Auth::guard('admin')->user();

        // If NOT super admin, restrict delete to own categories only
        if ($admin->type != '0' && $category->admin_id != $admin->id) {
            return back()->with('cus__error', 'You do not have permission to delete this category.');
        }

        // Perform delete
        $category->delete();

        return back()->with('cus__success', 'Article Category deleted successfully.');
    }

    /**
     * bulk delete */


    /** Bulk Category Delete */
    public function delete(Request $request)
    {
        $validated = $request->validate([
            'actions'  => 'required|integer|in:1',   // must be 1
            'bulk_ids' => 'required|string'          // "1,3,4"
        ]);

        // Convert string to array
        $ids = array_filter(explode(',', $validated['bulk_ids']));

        // Check which IDs exist
        $validIds = ArticleCategory::whereIn('id', $ids)->pluck('id')->toArray();

        // Detect missing IDs
        $missingIds = array_diff($ids, $validIds);

        if (!empty($missingIds)) {
            $message = count($missingIds) > 1
                ? ' ids are not found in database'
                : ' id is not found in database';

            return back()->with('cus__error', implode(',', $missingIds) . $message);
        }

        // BEGIN removing logic
        $query = ArticleCategory::query();

        // If not super admin
        if (Auth::guard('admin')->user()->type != '0') {
            $query->where('admin_id', Auth::guard('admin')->user()->id);
        }

        // Delete only user's allowed categories
        $query->whereIn('id', $ids)->delete();
        // END removing logic

        return back()->with('cus__success', 'Selected category/categories are deleted successfully.');
    }
}
