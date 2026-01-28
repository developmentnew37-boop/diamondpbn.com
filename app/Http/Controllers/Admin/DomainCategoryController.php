<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DomainCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class DomainCategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        //
        $query = DomainCategory::query();

        $Admin = Auth::guard('admin')->user();

        // If normal admin → show only their categories
        if ($Admin->type == '1') {
            $query->where('admin_id', $Admin->id);
        }

        // 🔍 SEARCH FILTER
        if ($request->filled('search')) {
            $search = $request->search;

            $query->where('name', 'LIKE', "%{$search}%");
        }

        // Latest order
        $query->latest();

        // Pagination with preserved URL params
        $categories = $query->paginate(8)->appends($request->query());

        return view('admin.domains.domain-category', compact('categories'));
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
        $validated = $request->validate([
            'name' => 'required|max:100|unique:domain_categories,name',
            'description' => 'nullable|string'
        ]);

        DomainCategory::create([
            ...$validated,
            'admin_id' => Auth::guard('admin')->user()->id,
        ]);
        return back()->with('cus__success', 'sucessfully added the category');
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
        // Validate route ID (ensure number)
        if (!ctype_digit($id)) {
            return back()->with('cus__error', 'Invalid category ID.');
        }

        // Check category existence
        $category = DomainCategory::find($id);

        if (!$category) {
            return back()->with('cus__error', 'Category not found.');
        }

        $admin = Auth::guard('admin')->user();

        // If NOT super admin, restrict delete to own categories only
        if ($admin->type != '0' && $category->admin_id != $admin->id) {
            return back()->with('cus__error', 'You do not have permission to delete this category.');
        }

        // Perform delete
        $category->delete();

        return back()->with('cus__success', 'Category deleted successfully.');
    }


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
        $validIds = DomainCategory::whereIn('id', $ids)->pluck('id')->toArray();

        // Detect missing IDs
        $missingIds = array_diff($ids, $validIds);

        if (!empty($missingIds)) {
            $message = count($missingIds) > 1
                ? ' ids are not found in database'
                : ' id is not found in database';

            return back()->with('cus__error', implode(',', $missingIds) . $message);
        }

        // BEGIN removing logic
        $query = DomainCategory::query();

        // If not super admin
        if (Auth::guard('admin')->user()->type != '0') {
            $query->where('admin_id', Auth::guard('admin')->user()->id);
        }

        // Delete only user's allowed categories
        $query->whereIn('id', $ids)->delete();
        // END removing logic

        return back()->with('cus__success', 'Selected categories deleted successfully.');
    }
}
