<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\ArticleLanguage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ArticleLanguageController extends Controller
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

        $limit = 8;

        // Build Query
        $query = ArticleLanguage::query();

        if ($request->filled('search')) {
            $keyword = "%{$request->search}%";
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', $keyword)
                    ->orWhere('slug', 'LIKE', $keyword);
            });
        }

        // Pagination + append filters
        $languages = $query->orderBy('id', 'desc')
            ->paginate($limit)
            ->appends($request->all());

        // OFFSET for table numbering
        $offset = ($languages->currentPage() - 1) * $limit;

        return view('admin.article.language.language', compact('languages', 'offset'));
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
        // Get logged in admin
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return back()->with('cus__error', 'Unauthorized request.');
        }

        // Validation
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:article_languages,name',
        ]);

        // Create language
        ArticleLanguage::create([
            'name'      => $validated['name'],
            'admin_id'  => $admin->id,
        ]);

        return back()->with('cus__success', 'Language added successfully.');
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
        $language = ArticleLanguage::find($id);
        return view('admin.article.language.edit-language', compact('language'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        // Get logged-in admin
        $admin = Auth::guard('admin')->user();

        if (!$admin) {
            return back()->with('cus__error', 'Unauthorized request.');
        }

        // Find record
        $language = ArticleLanguage::findOrFail($id);

        // Validation (unique except current id)
        $validated = $request->validate([
            'name' => "required|string|max:255|unique:article_languages,name,{$id}",
        ]);

        // Update language
        $language->update([
            'name'      => $validated['name'],
            'admin_id'  => $admin->id,   // if you want to update creator, else remove this line
        ]);

        return redirect()
            ->route('admin.articles.language.index')
            ->with('cus__success', 'Language updated successfully.');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Validate route ID (ensure number)
        if (!ctype_digit($id)) {
            return back()->with('cus__error', 'Invalid language ID.');
        }

        // Check category existence
        $language = ArticleLanguage::find($id);

        if (!$language) {
            return back()->with('cus__error', 'language not found.');
        }

        $admin = Auth::guard('admin')->user();

        // If NOT super admin, restrict delete to own categories only
        if ($admin->type != '0' && $language->admin_id != $admin->id) {
            return back()->with('cus__error', 'You do not have permission to delete this language.');
        }

        // Perform delete
        $language->delete();

        return back()->with('cus__success', 'Article language deleted successfully.');
    }
}
