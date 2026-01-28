<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\DomainSet;
use Dom\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DomainSetController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     //
    //     $domainCategories = DomainCategory::all();
    //     $sets = DomainSet::orderBy('id', 'desc')->paginate(20);
    //     return view('admin.domains.domain-set', compact('domainCategories', 'sets'));
    // }

    public function index(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|integer|exists:domain_categories,id',
            'search' => 'nullable|string|max:150'
        ]);

        $limit = 10;
        $domainCategories = DomainCategory::all();

        $query = DomainSet::query();

        if ($request->filled('category_id')) {
            $query->where('domain_category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = "%{$request->search}%";
            $query->where('name', 'LIKE', $search);
        }

        $query->orderBy('id', 'desc');

        $sets = $query->paginate($limit)->appends($request->all());
        $offset = ($sets->currentPage() - 1) * $limit;

        return view('admin.domains.domain-set', compact('sets', 'domainCategories', 'offset'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $id = $request->id;

        if (!is_numeric($id)) {
            return redirect()
                ->route('admin.set.index')
                ->with('cus__error', 'Invalid Set ID');
        }

        $set = DomainSet::find($id);

        if (!$set) {
            return redirect()
                ->route('admin.set.index')
                ->with('cus__error', `the set with {$id} is not found in database`);
        }

        $domains = Domain::select('*')->where('domain_category_id', $set->domain_category_id)->get();

        return view('admin.domains.create-domain-set', compact('set', 'domains'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'set'    => 'required|exists:domain_sets,id',
            'method' => 'required|in:0,1',
        ]);

        $set = DomainSet::findOrFail($request->set);
        $setCategory = $set->domain_category_id;


        /* ---------------------------------------------------------
        | METHOD 0 → IDs provided from select (comma separated)
         ----------------------------------------------------------*/
        if ($request->method == '0') {

            if (!$request->filled('domains')) {
                return back()->with('cus__error', 'Please select domains.');
            }

            // Convert "1,2,3" → [1,2,3]
            $ids = array_filter(array_map('intval', explode(',', $request->domains)));

            // Validate IDs exist in DB same category
            $validIDs = Domain::whereIn('id', $ids)
                ->where('domain_category_id', $setCategory)
                ->pluck('id')->toArray();

            if (count($validIDs) != count($ids)) {
                return back()->with('cus__error', 'Some domains do not belong to this category.');
            }

            // Save ONLY given valid ids (overwrite)
            $set->domains = json_encode($validIDs);
            $set->qty = count($validIDs);
            $set->save();

            return redirect()->route('admin.set.index')->with('cus__success', count($validIDs) . ' domains assigned successfully.');
        }



        /* ---------------------------------------------------------
        | METHOD 1 → Manual text input
        ----------------------------------------------------------*/
        if ($request->method == '1') {

            $request->validate([
                'manual_domains' => 'required'
            ]);

            $domains = preg_split("/\r\n|\n|\r/", trim($request->manual_domains));
            $domains = array_unique(array_filter(array_map('trim', $domains)));

            // Validate formats
            $validFormat = [];
            $invalidFormat = [];

            foreach ($domains as $d) {
                if (preg_match('/^(?!https?:\/\/)([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', $d)) {
                    $validFormat[] = strtolower($d);
                } else {
                    $invalidFormat[] = $d;
                }
            }

            if ($invalidFormat) {
                return back()->with('cus__error', 'Invalid format: ' . implode(', ', $invalidFormat));
            }

            // Check existing domains in DB under category
            $existing = Domain::whereIn('name', $validFormat)
                ->where('domain_category_id', $setCategory)
                ->pluck('id')->toArray();

            if (count($existing) != count($validFormat)) {

                $existingNames = Domain::whereIn('name', $validFormat)->pluck('name')->toArray();
                $notFound = array_diff($validFormat, $existingNames);

                return back()->with(
                    'cus__error',
                    "These domains do not exist in this category: " . implode(', ', $notFound)
                );
            }

            // Save ONLY validated IDs
            $set->domains = json_encode($existing);
            $set->qty = count($existing);
            $set->save();

            return redirect()->route('admin.set.index')->with('cus__success', count($existing) . ' domains assigned successfully.');
        }

        return back()->with('cus__error', 'Invalid method');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $limit = 10;
        $set = DomainSet::find($id);
        $domains = json_decode($set->domains, true);

        if (!$set) {
            return back()->with('cus__error', 'invalid set id');
        }
        $domains = Domain::whereIn('id', $domains)
            ->where('domain_category_id', $set->domain_category_id)
            ->paginate($limit);
        $offset = ($domains->currentPage() - 1) * $limit;


        return view('admin.domains.domain-set-detail', compact('set', 'domains', 'offset'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //

        $set = DomainSet::find($id);
        $domains = Domain::where('domain_category_id', $set->domain_category_id)->get();
        return view('admin.domains.edit-domain-set', compact('set', 'domains'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'method' => 'required|in:0,1',
        ]);

        $set = DomainSet::findOrFail($id);
        $setCategory = $set->domain_category_id;


        /* ---------------------------------------------------------
        | METHOD 0 → IDs (comma separated)
        ----------------------------------------------------------*/
        if ($request->method == '0') {

            if (!$request->filled('domains')) {
                return back()->with('cus__error', 'Please select domains.');
            }

            $ids = array_filter(array_map('intval', explode(',', $request->domains)));

            $validIDs = Domain::whereIn('id', $ids)
                ->where('domain_category_id', $setCategory)
                ->pluck('id')->toArray();

            if (count($validIDs) < count($ids)) {
                return back()->with('cus__error', 'Some selected domains are invalid or not in this category');
            }

            // 🚀 MASS UPDATE
            $set->update([
                'domains' => json_encode($validIDs),
                'qty'     => count($validIDs),
            ]);

            return back()->with('cus__success', 'Domains updated successfully.');
        }



        /* ---------------------------------------------------------
        | METHOD 1 → Manual text input
        ----------------------------------------------------------*/
        if ($request->method == '1') {

            $request->validate(['manual_domains' => 'required']);

            $domains = preg_split("/\r\n|\n|\r/", trim($request->manual_domains));
            $domains = array_unique(array_filter(array_map('trim', $domains)));

            $validFormat = [];
            $invalidFormat = [];

            foreach ($domains as $d) {
                if (preg_match('/^(?!https?:\/\/)([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/', $d)) {
                    $validFormat[] = strtolower($d);
                } else {
                    $invalidFormat[] = $d;
                }
            }

            if ($invalidFormat) {
                return back()->with('cus__error', 'Invalid format: ' . implode(', ', $invalidFormat));
            }

            $records = Domain::whereIn('name', $validFormat)
                ->where('domain_category_id', $setCategory)
                ->get(['id', 'name']);

            $existing     = $records->pluck('id')->toArray();
            $existingNames = $records->pluck('name')->toArray();

            if (count($existing) < count($validFormat)) {
                $notFound = array_diff($validFormat, $existingNames);
                return back()->with('cus__error', 'These do not exist: ' . implode(', ', $notFound));
            }

            // 🚀 MASS UPDATE
            $set->update([
                'domains' => json_encode($existing),
                'qty'     => count($existing),
            ]);

            return back()->with('cus__success', 'Domains updated successfully.');
        }

        return back()->with('cus__error', 'Invalid method');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Validate route ID (ensure number)
        if (!ctype_digit($id)) {
            return back()->with('cus__error', 'Invalid set ID.');
        }

        // Check category existence
        $set = DomainSet::find($id);

        if (!$set) {
            return back()->with('cus__error', 'set not found.');
        }

        $admin = Auth::guard('admin')->user();

        // If NOT super admin, restrict delete to own categories only
        if ($admin->type != '0' && $set->admin_id != $admin->id) {
            return back()->with('cus__error', 'You do not have permission to delete this set.');
        }

        // Perform delete
        $set->delete();

        return back()->with('cus__success', 'Domain Set deleted successfully.');
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
        $validIds = DomainSet::whereIn('id', $ids)->pluck('id')->toArray();

        // Detect missing IDs
        $missingIds = array_diff($ids, $validIds);

        if (!empty($missingIds)) {
            $message = count($missingIds) > 1
                ? ' ids are not found in database'
                : ' id is not found in database';

            return back()->with('cus__error', implode(',', $missingIds) . $message);
        }

        // BEGIN removing logic
        $query = DomainSet::query();

        // If not super admin
        if (Auth::guard('admin')->user()->type != '0') {
            $query->where('admin_id', Auth::guard('admin')->user()->id);
        }

        // Delete only user's allowed categories
        $query->whereIn('id', $ids)->delete();
        // END removing logic

        return back()->with('cus__success', 'Selected domain set/sets are deleted successfully.');
    }
}
