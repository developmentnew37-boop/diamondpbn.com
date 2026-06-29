<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\DomainCategory;
use App\Models\Admin\PendingDomain;
use App\Services\PendingDomainTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class TransferDomainController extends Controller
{
    public function __construct(
        private readonly PendingDomainTransferService $transferService
    ) {}

    /**
     * Step 1: Show pending domains for selection.
     */
    public function step1(Request $request): View
    {
        $query = PendingDomain::query()
            ->select(['id', 'domain_name', 'api_key', 'viewed', 'webhook_secret_id', 'created_at'])
            ->with('webhookSecret:id,name')
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc');

        if ($request->filled('extension')) {
            $extension = strtolower(preg_replace('/[^a-z0-9]/', '', $request->extension));
            $query->where('domain_name', 'like', '%.'.$extension);
        }

        $pendingDomains = $query->get();

        $allExtensions = PendingDomain::query()
            ->where('status', 'pending')
            ->pluck('domain_name');

        $extensionCounts = $allExtensions
            ->map(fn ($name) => extractDomainExtension($name))
            ->filter()
            ->countBy();

        $extensions = $extensionCounts->keys()->sort()->values();

        return view('admin.domains.transfer.step1', compact('pendingDomains', 'extensions', 'extensionCounts'));
    }

    /**
     * Step 2: Select or create domain category.
     */
    public function step2(Request $request): View|RedirectResponse
    {
        $request->validate([
            'domain_ids' => 'sometimes|array|min:1|max:500',
            'domain_ids.*' => 'integer|exists:pending_domains,id',
        ]);

        $domainIds = $this->resolveDomainIds($request);

        if ($domainIds === []) {
            return redirect()
                ->route('admin.transfer-domains.step1')
                ->with('error', 'No valid pending domains selected');
        }

        $selectedCount = PendingDomain::query()
            ->whereIn('id', $domainIds)
            ->where('status', 'pending')
            ->count();

        if ($selectedCount === 0) {
            return redirect()
                ->route('admin.transfer-domains.step1')
                ->with('error', 'No valid pending domains selected');
        }

        $categories = DomainCategory::withCount('domains')->orderBy('name')->get();

        return view('admin.domains.transfer.step2', compact('categories', 'domainIds', 'selectedCount'));
    }

    /**
     * Create new category via AJAX during transfer.
     */
    public function createCategory(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:domain_categories,name',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $category = DomainCategory::create([
                'name' => $request->name,
                'description' => $request->description,
                'admin_id' => Auth::guard('admin')->id(),
            ]);

            Log::info('Domain category created during transfer', [
                'category_id' => $category->id,
                'name' => $category->name,
                'admin' => auth('admin')->user()->email,
            ]);

            return response()->json([
                'success' => true,
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'domains_count' => 0,
                ],
                'message' => 'Category created successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create category during transfer', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create category: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process the transfer from pending to active domains.
     */
    public function process(Request $request): RedirectResponse
    {
        $request->validate([
            'domain_ids' => 'sometimes|array|min:1|max:500',
            'domain_ids.*' => 'integer|exists:pending_domains,id',
            'domain_ids_payload' => 'sometimes|string',
            'domain_category_id' => 'required|exists:domain_categories,id',
        ]);

        $domainIds = $this->resolveDomainIds($request);

        if ($domainIds === []) {
            return redirect()
                ->back()
                ->with('error', 'No domains selected for transfer')
                ->withInput();
        }

        $adminId = Auth::guard('admin')->id();

        try {
            $result = $this->transferService->bulkTransfer(
                $domainIds,
                (int) $request->domain_category_id,
                $adminId,
                'Transferred to domains via bulk transfer'
            );

            $message = "Successfully transferred {$result['success']} domain(s)";

            if ($result['failed'] > 0) {
                $message .= ", {$result['failed']} failed";
            }

            if ($result['success'] > 0) {
                $message .= '. Plugin connection checks are running in the background.';
            }

            if (! empty($result['errors'])) {
                session()->flash('transfer_errors', $result['errors']);
            }

            return redirect()
                ->route('admin.domain.index', ['category_id' => $request->domain_category_id])
                ->with('cus__success', $message);
        } catch (\Exception $e) {
            Log::error('Bulk domain transfer failed', [
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->with('error', 'Transfer failed: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * @return array<int>
     */
    private function resolveDomainIds(Request $request): array
    {
        if ($request->filled('domain_ids')) {
            return array_values(array_unique(array_map('intval', $request->domain_ids)));
        }

        if ($request->filled('domain_ids_payload')) {
            $ids = array_filter(array_map('intval', explode(',', $request->domain_ids_payload)));

            return array_values(array_unique($ids));
        }

        return [];
    }
}
