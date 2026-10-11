<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillingPeriod;
use App\Models\Admin\LocalClientInstallment;
use App\Models\Admin\LocalClientRateList;
use App\Services\LocalClientBillingPeriodService;
use App\Services\LocalClientBillingReportService;
use App\Services\LocalClientBillingService;
use App\Services\LocalClientDeletionService;
use App\Services\LocalClientManualBillingService;
use App\Services\LocalClientPaymentService;
use App\Services\LocalClientPriceMatrixService;
use App\Support\CurrencyFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelWriter;

class LocalClientController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:local_clients.manage')->except(['estimate']);
    }

    public function index(Request $request)
    {
        $query = LocalClient::query();

        if ($request->filled('search')) {
            $search = '%'.trim($request->string('search')->toString()).'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('company_name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->string('status')->toString() === 'active');
        }

        $clients = $query->latest()->paginate(15)->appends($request->query());

        return view('admin.local-clients.index', compact('clients'));
    }

    public function create(LocalClientPriceMatrixService $matrixService)
    {
        $matrix = $matrixService->buildGrid();
        $currencies = CurrencyFormatter::supportedCodes();

        return view('admin.local-clients.form', [
            'client' => new LocalClient(['default_currency' => 'USD', 'is_active' => true]),
            'matrix' => $matrix,
            'currencies' => $currencies,
            'mode' => 'create',
            'rateLists' => $this->rateListsForForm(),
            'copyFromClients' => $this->customRateClients(),
            'rateListMatrices' => $this->rateListMatricesJson($matrixService),
            'customClientMatrices' => $this->customClientMatricesJson($matrixService),
        ]);
    }

    public function store(Request $request, LocalClientPriceMatrixService $matrixService)
    {
        $validated = $this->validateClient($request);

        $client = DB::transaction(function () use ($request, $validated, $matrixService) {
            $client = LocalClient::create([
                ...$validated,
                'created_by_admin_id' => Auth::guard('admin')->id(),
            ]);

            $this->syncClientRateSource($client, $request, $matrixService);

            return $client;
        });

        return redirect()
            ->route('admin.local-clients.show', $client)
            ->with('cus__success', 'Client created successfully.');
    }

    public function show(
        Request $request,
        LocalClient $localClient,
        LocalClientBillingReportService $reportService,
        LocalClientBillingPeriodService $periodService,
        LocalClientPaymentService $paymentService,
    ) {
        $campaigns = $reportService->campaignsForClient($localClient);
        $summary = $reportService->formatSummaryForCurrency($campaigns, $localClient->default_currency);
        $covered = Schema::hasTable('local_client_installment_allocations')
            ? $paymentService->installmentCoveredAmounts($localClient)
            : [];
        $periods = $periodService->periodsForClient($localClient, $campaigns)
            ->map(function (array $period) use ($campaigns, $paymentService, $covered) {
                $period['unpaid_candidates'] = $paymentService->unpaidCandidatesForPeriod($campaigns, $period, $covered);

                return $period;
            });
        $periods = $reportService->paginateCollection($periods, $request, 10, 'periods_page');
        $reportUrl = route('admin.local-client.billing.report', [
            'id' => $localClient->id,
            'token' => $localClient->billing_report_token,
        ]);

        $localClient->loadMissing('rateList:id,name');

        $openBalance = [
            'open_balance' => '0.00',
            'currency' => strtoupper((string) $localClient->default_currency),
            'other_balances' => [],
        ];

        if (Schema::hasTable('local_client_installments')) {
            $openBalance = $paymentService->openBalanceSummary($localClient);
        }

        $installmentFormToken = $this->installmentFormToken($localClient);

        return view('admin.local-clients.show', compact(
            'localClient',
            'summary',
            'reportUrl',
            'periods',
            'openBalance',
            'installmentFormToken',
        ));
    }

    public function markPeriodPaid(
        Request $request,
        LocalClient $localClient,
        LocalClientPaymentService $paymentService,
    ) {
        $validated = $request->validate([
            'billing_payment_note' => 'nullable|string|max:500',
        ]);

        $result = $paymentService->markOpenPeriodPaid(
            $localClient,
            Auth::guard('admin')->user(),
            $validated['billing_payment_note'] ?? null,
        );

        return back()->with(
            'cus__success',
            "Marked {$result['updated_count']} campaign(s) as paid for this billing period.",
        );
    }

    public function markPeriodUnpaid(
        Request $request,
        LocalClient $localClient,
        LocalClientPaymentService $paymentService,
    ) {
        $admin = Auth::guard('admin')->user();

        if (! $admin->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'paid_at' => 'required|date',
            'campaigns' => 'required|array|min:1',
            'campaigns.*' => 'required|string|max:120',
        ], [
            'campaigns.required' => 'Select at least one campaign.',
            'campaigns.min' => 'Select at least one campaign.',
        ]);

        $result = $paymentService->markPaidPeriodUnpaid(
            $localClient,
            Carbon::parse($validated['paid_at']),
            $admin,
            $validated['campaigns'],
        );

        return back()->with(
            'cus__success',
            "Marked {$result['updated_count']} campaign(s) as unpaid.",
        );
    }

    public function storeInstallment(
        Request $request,
        LocalClient $localClient,
        LocalClientPaymentService $paymentService,
    ) {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
            'form_token' => 'required|string',
        ]);

        $this->consumeInstallmentFormToken($request, $localClient);

        $result = $paymentService->recordInstallment(
            $localClient,
            $validated['amount'],
            Auth::guard('admin')->user(),
            $validated['note'] ?? null,
        );

        $formatted = CurrencyFormatter::format($result['amount'], $result['currency']);

        return back()->with(
            'cus__success',
            "Recorded {$formatted}. {$result['paid_count']} campaign(s) paid, {$result['semi_paid_count']} semi paid.",
        );
    }

    public function previewManualBilling(
        Request $request,
        LocalClient $localClient,
        LocalClientManualBillingService $manualBillingService,
    ): JsonResponse {
        $validated = $request->validate([
            'campaign_type' => 'required|string|in:'.implode(',', LocalClientManualBillingService::KINDS),
            'post_links' => 'nullable|string|max:50000',
        ]);

        return response()->json($manualBillingService->preview(
            $localClient,
            $validated['campaign_type'],
            $validated['post_links'] ?? null,
        ));
    }

    public function storeManualBilling(
        Request $request,
        LocalClient $localClient,
        LocalClientManualBillingService $manualBillingService,
    ) {
        $request->merge([
            'campaign_no' => trim($request->string('campaign_no')->toString()),
            'keyword' => trim($request->string('keyword')->toString()),
            'url' => trim($request->string('url')->toString()),
        ]);

        $validated = $request->validate([
            'campaign_no' => 'required|string|max:191',
            'campaign_type' => 'required|string|in:'.implode(',', LocalClientManualBillingService::KINDS),
            'payment_status' => 'required|string|in:paid,unpaid',
            'post_links' => 'required|string|max:50000',
            'keyword' => 'required|string|max:50000',
            'url' => 'required|string|max:50000',
        ]);

        $bill = $manualBillingService->create(
            $localClient,
            Auth::guard('admin')->user(),
            $validated,
        );

        $formatted = CurrencyFormatter::format($bill->billing_total, $bill->billing_currency);
        $statusLabel = $bill->billing_payment_status === 'paid' ? 'paid' : 'unpaid';

        return back()->with(
            'cus__success',
            "Manual campaign {$bill->campaign_no} saved as {$statusLabel} for {$formatted}.",
        );
    }

    public function voidLatestInstallment(
        Request $request,
        LocalClient $localClient,
        LocalClientPaymentService $paymentService,
    ) {
        $validated = $request->validate([
            'installment_id' => 'required|integer|min:1',
        ]);

        $result = $paymentService->voidLatestInstallment(
            $localClient,
            Auth::guard('admin')->user(),
            (int) $validated['installment_id'],
        );

        return back()->with(
            'cus__success',
            "Installment reverted. {$result['restored_count']} campaign(s) restored.",
        );
    }

    public function installments(
        Request $request,
        LocalClientPaymentService $paymentService,
        LocalClientBillingReportService $reportService,
    ) {
        $filters = $this->installmentFilters($request);
        $accountSummary = null;
        $selectedClient = null;

        if (! empty($filters['client_id'])) {
            $selectedClient = LocalClient::query()->find($filters['client_id']);
        }

        if ($selectedClient) {
            $accountSummary = $paymentService->installmentAccountSummary(
                $selectedClient,
                $reportService->campaignsForClient($selectedClient),
            );
        }

        $installments = LocalClientInstallment::query()
            ->forLedger($filters)
            ->with(['localClient:id,name', 'admin:id,name', 'allocations'])
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $reversibleIds = array_fill_keys(
            LocalClientInstallment::query()
                ->selectRaw('MAX(id) as id')
                ->groupBy('local_client_id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all(),
            true,
        );

        return view('admin.local-clients.installments', [
            'installments' => $installments,
            'filters' => $filters,
            'clients' => LocalClient::query()->orderBy('name')->get(['id', 'name']),
            'admins' => Admin::query()->orderBy('name')->get(['id', 'name']),
            'reversibleIds' => $reversibleIds,
            'accountSummary' => $accountSummary,
        ]);
    }

    public function exportInstallments(Request $request)
    {
        $filters = $this->installmentFilters($request);
        $installments = LocalClientInstallment::query()
            ->forLedger($filters)
            ->with(['localClient:id,name', 'admin:id,name', 'allocations'])
            ->orderByDesc('id')
            ->get();

        $stamp = now()->format('Y-m-d');

        if ($request->query('format') === 'pdf') {
            $clientName = null;
            $recordedByName = null;

            if (! empty($filters['client_id'])) {
                $clientName = LocalClient::query()->whereKey($filters['client_id'])->value('name');
            }

            if (! empty($filters['admin_id'])) {
                $recordedByName = Admin::query()->whereKey($filters['admin_id'])->value('name');
            }

            return Pdf::loadView('admin.local-clients.installments-export-pdf', [
                'installments' => $installments,
                'filters' => $filters,
                'clientName' => $clientName,
                'recordedByName' => $recordedByName,
            ])->setPaper('a4', 'portrait')->download('local-client-installments-'.$stamp.'.pdf');
        }

        $writer = SimpleExcelWriter::streamDownload('local-client-installments-'.$stamp.'.xlsx')
            ->addHeader(['Date', 'Client', 'Amount', 'Currency', 'Recorded by', 'Note', 'Type', 'Campaigns']);

        foreach ($installments as $installment) {
            $writer->addRow([
                $installment->created_at?->format('M d, Y g:i A'),
                $installment->localClient?->name,
                number_format((float) $installment->amount, 2, '.', ''),
                $installment->currency,
                $installment->admin?->name,
                $installment->note,
                $installment->sourceLabel(),
                $installment->allocationSummary(),
            ]);
        }

        $writer->toBrowser();
    }

    public function destroyBillingPeriod(LocalClient $localClient, LocalClientBillingPeriod $billingPeriod)
    {
        if ((int) $billingPeriod->local_client_id !== (int) $localClient->id) {
            abort(404);
        }

        $billingPeriod->delete();

        return back()->with('cus__success', 'Billing period record deleted.');
    }

    public function destroyBillingPeriodByPaidAt(Request $request, LocalClient $localClient)
    {
        $validated = $request->validate([
            'paid_at' => 'required|date',
        ]);

        $paidAt = Carbon::parse($validated['paid_at']);

        $deleted = LocalClientBillingPeriod::query()
            ->where('local_client_id', $localClient->id)
            ->whereBetween('paid_at', [
                $paidAt->copy()->subSecond(),
                $paidAt->copy()->addSecond(),
            ])
            ->delete();

        if ($deleted === 0) {
            return back()->with(
                'cus__error',
                'No stored billing period record was found for this row. Campaign payment status was not changed.',
            );
        }

        return back()->with('cus__success', 'Billing period record deleted.');
    }

    public function destroy(LocalClient $localClient, LocalClientDeletionService $deletionService)
    {
        $name = $localClient->name;

        $deletionService->delete($localClient);

        return redirect()
            ->route('admin.local-clients.index')
            ->with('cus__success', "Client \"{$name}\" deleted successfully.");
    }

    public function edit(LocalClient $localClient, LocalClientPriceMatrixService $matrixService)
    {
        $matrix = $localClient->rate_list_id
            ? $matrixService->buildGridForRateList($localClient->rateList)
            : $matrixService->buildGrid($localClient);
        $currencies = CurrencyFormatter::supportedCodes();

        return view('admin.local-clients.form', [
            'client' => $localClient,
            'matrix' => $matrix,
            'currencies' => $currencies,
            'mode' => 'edit',
            'rateLists' => $this->rateListsForForm($localClient),
            'copyFromClients' => $this->customRateClients($localClient->id),
            'rateListMatrices' => $this->rateListMatricesJson($matrixService),
            'customClientMatrices' => $this->customClientMatricesJson($matrixService, $localClient->id),
        ]);
    }

    public function update(Request $request, LocalClient $localClient, LocalClientPriceMatrixService $matrixService)
    {
        $validated = $this->validateClient($request, $localClient);

        DB::transaction(function () use ($request, $localClient, $validated, $matrixService) {
            $localClient->update($validated);
            $this->syncClientRateSource($localClient, $request, $matrixService);
        });

        return redirect()
            ->route('admin.local-clients.show', $localClient)
            ->with('cus__success', 'Client updated successfully.');
    }

    public function priceMatrix(LocalClient $localClient, LocalClientPriceMatrixService $matrixService): JsonResponse
    {
        if ($localClient->rate_list_id) {
            return response()->json([
                'success' => false,
                'message' => 'This client uses a shared rate list. Only custom-rate clients can be copied from.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'prices' => $matrixService->pricesKeyedByCategory($localClient),
        ]);
    }

    public function toggleActive(LocalClient $localClient)
    {
        $localClient->update(['is_active' => ! $localClient->is_active]);

        return back()->with('cus__success', 'Client status updated.');
    }

    public function regenerateToken(LocalClient $localClient)
    {
        $localClient->regenerateBillingReportToken();

        return back()->with('cus__success', 'Billing report link regenerated. Previous links are now invalid.');
    }

    public function estimate(
        Request $request,
        LocalClient $localClient,
        LocalClientBillingService $billingService,
    ): JsonResponse {
        $this->normalizeEstimateDomainIds($request);

        $validated = $request->validate([
            'campaign_type' => 'required|string|in:post,sidebar,hidden_links,sticky,schedule_post,schedule_sidebar',
            'domain_ids' => 'required|array|min:1',
            'domain_ids.*' => 'integer|exists:domains,id',
            'is_sticky' => 'nullable|boolean',
            'currency' => ['nullable', 'string', Rule::in(CurrencyFormatter::supportedCodes())],
        ]);

        $type = $billingService->resolveBillingType(
            $validated['campaign_type'],
            (bool) ($validated['is_sticky'] ?? false),
        );

        $result = $billingService->calculate($localClient, $type, $validated['domain_ids']);
        $currency = strtoupper($validated['currency'] ?? $localClient->default_currency);

        return response()->json([
            'success' => true,
            'total' => $result->total,
            'currency' => $currency,
            'formatted_total' => CurrencyFormatter::format($result->total, $currency),
            'lines' => collect($result->lines)->map(function (array $line) use ($currency) {
                return [
                    ...$line,
                    'formatted_unit_price' => CurrencyFormatter::format($line['unit_price'], $currency),
                    'formatted_line_total' => CurrencyFormatter::format($line['line_total'], $currency),
                ];
            })->values(),
        ]);
    }

    /**
     * One comma-separated field stays under live WAF argument limits (about 255).
     */
    private function normalizeEstimateDomainIds(Request $request): void
    {
        $raw = $request->input('domain_ids');

        if (! is_string($raw)) {
            return;
        }

        $ids = array_values(array_filter(
            array_map(static fn (string $part): int => (int) trim($part), explode(',', $raw)),
            static fn (int $id): bool => $id > 0,
        ));

        $request->merge(['domain_ids' => $ids]);
    }

    /** @return array<string, mixed> */
    private function validateClient(Request $request, ?LocalClient $client = null): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'company_name' => 'nullable|string|max:150',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
            'default_currency' => ['required', 'string', Rule::in(CurrencyFormatter::supportedCodes())],
            'is_active' => 'nullable|in:0,1',
            'rate_source' => [
                'required',
                'string',
                function (string $attribute, mixed $value, \Closure $fail) use ($client) {
                    if ($value === 'custom') {
                        return;
                    }
                    $ok = LocalClientRateList::query()
                        ->where('id', $value)
                        ->where(function ($q) use ($client) {
                            $q->where('is_active', true);
                            if ($client?->rate_list_id) {
                                $q->orWhere('id', $client->rate_list_id);
                            }
                        })
                        ->exists();
                    if (! $ok) {
                        $fail('Selected rate list is invalid or inactive.');
                    }
                },
            ],
        ]);

        $validated['is_active'] = filter_var($request->input('is_active', '1'), FILTER_VALIDATE_BOOLEAN);
        unset($validated['rate_source']);

        return $validated;
    }

    private function syncClientRateSource(
        LocalClient $client,
        Request $request,
        LocalClientPriceMatrixService $matrixService,
    ): void {
        $rateSource = (string) $request->input('rate_source', 'custom');

        if ($rateSource === 'custom') {
            $matrixService->validateCompleteMatrix($request->input('prices', []));
            $client->update(['rate_list_id' => null]);
            $matrixService->upsertPrices($client, $request->input('prices', []));

            return;
        }

        $rateListId = (int) $rateSource;
        $client->update(['rate_list_id' => $rateListId]);
        $matrixService->clearClientPrices($client);
    }

    /** @return \Illuminate\Support\Collection<int, LocalClientRateList> */
    private function rateListsForForm(?LocalClient $client = null)
    {
        return LocalClientRateList::query()
            ->where(function ($q) use ($client) {
                $q->where('is_active', true);
                if ($client?->rate_list_id) {
                    $q->orWhere('id', $client->rate_list_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    /** @return \Illuminate\Support\Collection<int, LocalClient> */
    private function customRateClients(?int $excludeId = null)
    {
        return LocalClient::query()
            ->whereNull('rate_list_id')
            ->where('is_active', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /** @return array<string, array<int, array<string, ?string>>> */
    private function rateListMatricesJson(LocalClientPriceMatrixService $matrixService): array
    {
        $out = [];
        foreach (LocalClientRateList::query()->get() as $list) {
            $out[(string) $list->id] = $matrixService->rateListPricesKeyedByCategory($list);
        }

        return $out;
    }

    /** @return array<string, array<int, array<string, ?string>>> */
    private function customClientMatricesJson(LocalClientPriceMatrixService $matrixService, ?int $excludeId = null): array
    {
        $out = [];
        foreach ($this->customRateClients($excludeId) as $customClient) {
            $out[(string) $customClient->id] = $matrixService->pricesKeyedByCategory($customClient);
        }

        return $out;
    }

    private function installmentFormToken(LocalClient $client): string
    {
        $key = $this->installmentTokenKey($client);
        $token = session($key);

        if (! is_string($token) || strlen($token) < 20) {
            $token = Str::random(40);
            session([$key => $token]);
        }

        return $token;
    }

    private function consumeInstallmentFormToken(Request $request, LocalClient $client): void
    {
        $key = $this->installmentTokenKey($client);
        $expected = session($key);
        $given = (string) $request->input('form_token');

        if (! is_string($expected) || $expected === '' || ! hash_equals($expected, $given)) {
            throw ValidationException::withMessages([
                'amount' => 'This installment was already recorded.',
            ]);
        }

        session()->forget($key);
    }

    private function installmentTokenKey(LocalClient $client): string
    {
        return 'installment_form_token.'.$client->id;
    }

    /**
     * @return array{client_id: ?int, admin_id: ?int, from: ?string, to: ?string}
     */
    private function installmentFilters(Request $request): array
    {
        $validated = $request->validate([
            'client_id' => 'nullable|integer|min:1',
            'admin_id' => 'nullable|integer|min:1',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $from = $validated['from'] ?? null;
        $to = $validated['to'] ?? null;

        if ($from && $to && $from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'client_id' => isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            'admin_id' => isset($validated['admin_id']) ? (int) $validated['admin_id'] : null,
            'from' => $from,
            'to' => $to,
        ];
    }
}
