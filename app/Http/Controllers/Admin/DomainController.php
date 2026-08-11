<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\Domain;
use App\Models\Admin\DomainCategory;
use App\Rules\UniqueCredential;
use App\Services\CredentialBlindIndex;
use App\Services\WordPressAgentStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelWriter;

class DomainController extends Controller
{
    public function __construct(
        private readonly WordPressAgentStatusService $agentStatusService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|integer|exists:domain_categories,id',
            'search' => 'nullable|string|max:150',
        ]);

        $limit = 100;
        $domainCategories = DomainCategory::all();

        $query = Domain::query();

        if ($request->filled('category_id')) {
            $query->where('domain_category_id', $request->category_id);
        }

        if ($request->filled('search')) {
            $search = "%{$request->search}%";
            $query->where('name', 'LIKE', $search);
        }

        $domains = $query->paginate($limit)->appends($request->all());
        $offset = ($domains->currentPage() - 1) * $limit;

        return view('admin.domains.domains', compact('domains', 'domainCategories', 'offset'));
    }

    /**
     * Redirect function
     * */
    public function redirect__func(Request $request)
    {
        $validate = $request->validate([
            'category' => 'required|integer|exists:domain_categories,id',
        ]);

        return redirect()->route('admin.domain.index', [
            'category_id' => $validate['category'],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        $domainCategories = DomainCategory::all();

        return view('admin.domains.add-domains', compact('domainCategories'));
    }

    /**
     * making redirect based on domain category
     * **/
    public function selectDomainCategory(Request $request)
    {
        $domainCategories = DomainCategory::all();

        return view('admin.domains.select-category', compact('domainCategories'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validate = $request->validate([
            'name' => [
                'required',
                'unique:domains,name',
                'regex:/^(?!https?:\/\/)([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/',
            ], // only domain.com format
            'domain_category_id' => 'required|exists:domain_categories,id', // fixed space issue
            'da' => 'nullable|numeric|min:0|max:100',
            'dr' => 'nullable|numeric|min:0|max:100',
            'tf' => 'nullable|numeric|min:0|max:100',
            'ss' => 'nullable|numeric|min:0|max:100',
            'ip' => 'nullable|string',
            'api_key' => [
                'nullable',
                'string',
                new UniqueCredential('domains', 'api_key_lookup_hash', CredentialBlindIndex::DOMAIN_API_KEY, credentialColumn: 'api_key'),
            ],
        ]);

        // Normalize hostname (lowercase, strip scheme/path) for consistent storage
        $domain = normalizeDomainName($validate['name']);

        $statusResult = $this->agentStatusService->probeWithAuthFallback(
            $domain,
            $validate['api_key'] ?? null
        );

        Domain::create($statusResult->healthAttributes() + [
            'name' => $domain,
            'domain_category_id' => $validate['domain_category_id'],
            'da' => $validate['da'],
            'dr' => $validate['dr'],
            'tf' => $validate['tf'],
            'ss' => $validate['ss'],
            'ip' => $validate['ip'],
            'api_key' => $validate['api_key'],
            'admin_id' => Auth::guard('admin')->id(),
        ]);

        return back()->with('cus__success', 'Domain added successfully');
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

        $domain = Domain::find($id);
        $domainCategories = DomainCategory::all();

        if (! $domain) {
            return back()->with('cus__error', 'the product with ('.$id.') that you are trying to edit is not found in database');
        }

        return view('admin.domains.edit-domains', compact('domain', 'domainCategories'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $domain = Domain::findOrFail($id);

        // Validation
        $validate = $request->validate([
            'name' => [
                'required',
                'regex:/^(?!https?:\/\/)([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}$/',
                'unique:domains,name,'.$id,   // ← important for update
            ],
            'domain_category_id' => 'required|exists:domain_categories,id',
            'da' => 'nullable|numeric|min:1|max:100',
            'dr' => 'nullable|numeric|min:1|max:100',
            'tf' => 'nullable|numeric|min:1|max:100',
            'ss' => 'nullable|numeric|min:1|max:100',
            'ip' => 'nullable|string',
            'api_key' => [
                'nullable',
                'string',
                new UniqueCredential('domains', 'api_key_lookup_hash', CredentialBlindIndex::DOMAIN_API_KEY, (int) $id, 'api_key'),
            ],
        ]);

        // domain.com format
        $domainName = normalizeDomainName($validate['name']);
        $existingApiKey = '';
        try {
            $existingApiKey = trim((string) ($domain->api_key ?? ''));
        } catch (\Throwable) {
            $existingApiKey = '';
        }
        $statusResult = $this->agentStatusService->probeWithAuthFallback(
            $domainName,
            ! empty($validate['api_key']) ? $validate['api_key'] : $existingApiKey
        );

        // Update DB
        $attributes = $statusResult->healthAttributes($domain->last_seen_at) + [
            'name' => $domainName,
            'domain_category_id' => $validate['domain_category_id'],
            'da' => $validate['da'],
            'dr' => $validate['dr'],
            'tf' => $validate['tf'],
            'ss' => $validate['ss'],
            'ip' => $validate['ip'],
            'admin_id' => Auth::guard('admin')->id(), // optional if admin change only
        ];

        if (! empty($validate['api_key'])) {
            $attributes['api_key'] = $validate['api_key'];
        }

        $domain->update($attributes);

        return back()->with('cus__success', 'Domain updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Validate route ID (ensure number)
        if (! ctype_digit($id)) {
            return back()->with('cus__error', 'Invalid domain ID.');
        }

        // Check category existence
        $domain = Domain::find($id);

        if (! $domain) {
            return back()->with('cus__error', 'domain not found.');
        }

        $admin = Auth::guard('admin')->user();

        // If NOT super admin, restrict delete to own categories only
        if ($admin->type != '0' && $domain->admin_id != $admin->id) {
            return back()->with('cus__error', 'You do not have permission to delete this domain.');
        }

        // Perform delete
        $domain->delete();

        return back()->with('cus__success', 'Category deleted successfully.');
    }

    /** Bulk Category Delete */
    public function delete(Request $request)
    {
        $validated = $request->validate([
            'actions' => 'required|integer|in:1',   // must be 1
            'bulk_ids' => 'required|string',          // "1,3,4"
        ]);

        // Convert string to array
        $ids = array_filter(explode(',', $validated['bulk_ids']));

        // Check which IDs exist
        $validIds = Domain::whereIn('id', $ids)->pluck('id')->toArray();

        // Detect missing IDs
        $missingIds = array_diff($ids, $validIds);

        if (! empty($missingIds)) {
            $message = count($missingIds) > 1
                ? ' ids are not found in database'
                : ' id is not found in database';

            return back()->with('cus__error', implode(',', $missingIds).$message);
        }

        // BEGIN removing logic
        $query = Domain::query();

        // If not super admin
        if (Auth::guard('admin')->user()->type != '0') {
            $query->where('admin_id', Auth::guard('admin')->user()->id);
        }

        // Delete only user's allowed categories
        $query->whereIn('id', $ids)->delete();
        // END removing logic

        return back()->with('cus__success', 'Selected domain/domains are deleted successfully.');
    }

    /**
     * Export domains by selected category as Excel.
     */
    public function extractByCategory(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|integer|exists:domain_categories,id',
        ]);

        $categoryId = (int) $validated['category_id'];
        $category = DomainCategory::query()
            ->select(['id', 'name'])
            ->findOrFail($categoryId);

        $fileName = 'domains-'.Str::slug((string) $category->name).'-'.now()->format('Ymd_His').'.xlsx';

        $writer = SimpleExcelWriter::streamDownload($fileName)->addHeader([
            'Domain',
            'Category',
            'DA',
            'TF',
            'DR',
            'SS',
            'IP',
            'Status',
            'Created At',
        ]);

        Domain::query()
            ->select(['id', 'name', 'da', 'tf', 'dr', 'ss', 'ip', 'status', 'created_at'])
            ->where('domain_category_id', $categoryId)
            ->orderBy('id')
            ->chunkById(500, function ($domains) use ($writer, $category) {
                foreach ($domains as $domain) {
                    $writer->addRow([
                        'Domain' => (string) ($domain->name ?? '-'),
                        'Category' => (string) ($category->name ?? '-'),
                        'DA' => (int) ($domain->da ?? 0),
                        'TF' => (int) ($domain->tf ?? 0),
                        'DR' => (int) ($domain->dr ?? 0),
                        'SS' => (int) ($domain->ss ?? 0),
                        'IP' => (string) ($domain->ip ?? '-'),
                        'Status' => ((int) ($domain->status ?? 0) === 1) ? 'Connected' : 'Disconnected',
                        'Created At' => optional($domain->created_at)->format('d M Y H:i'),
                    ]);
                }
            }, 'id');

        return $writer->toBrowser();
    }
}
