<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillingPeriod;
use App\Services\LocalClientBillingPeriodService;
use App\Services\LocalClientBillingReportService;
use App\Services\LocalClientBillingService;
use App\Services\LocalClientDeletionService;
use App\Services\LocalClientPaymentService;
use App\Services\LocalClientPriceMatrixService;
use App\Support\CurrencyFormatter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        ]);
    }

    public function store(Request $request, LocalClientPriceMatrixService $matrixService)
    {
        $validated = $this->validateClient($request);

        $client = LocalClient::create([
            ...$validated,
            'created_by_admin_id' => Auth::guard('admin')->id(),
        ]);

        $matrixService->upsertPrices($client, $request->input('prices', []));

        return redirect()
            ->route('admin.local-clients.show', $client)
            ->with('cus__success', 'Client created successfully.');
    }

    public function show(
        Request $request,
        LocalClient $localClient,
        LocalClientBillingReportService $reportService,
        LocalClientBillingPeriodService $periodService,
    ) {
        $campaigns = $reportService->campaignsForClient($localClient);
        $summary = $reportService->formatSummaryForCurrency($campaigns, $localClient->default_currency);
        $periods = $periodService->periodsForClient($localClient, $campaigns);
        $periods = $reportService->paginateCollection($periods, $request, 10, 'periods_page');
        $reportUrl = route('admin.local-client.billing.report', [
            'id' => $localClient->id,
            'token' => $localClient->billing_report_token,
        ]);

        return view('admin.local-clients.show', compact('localClient', 'summary', 'reportUrl', 'periods'));
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
        $matrix = $matrixService->buildGrid($localClient);
        $currencies = CurrencyFormatter::supportedCodes();

        return view('admin.local-clients.form', [
            'client' => $localClient,
            'matrix' => $matrix,
            'currencies' => $currencies,
            'mode' => 'edit',
        ]);
    }

    public function update(Request $request, LocalClient $localClient, LocalClientPriceMatrixService $matrixService)
    {
        $validated = $this->validateClient($request, $localClient);
        $localClient->update($validated);
        $matrixService->upsertPrices($localClient, $request->input('prices', []));

        return redirect()
            ->route('admin.local-clients.show', $localClient)
            ->with('cus__success', 'Client updated successfully.');
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
        ]);

        $validated['is_active'] = filter_var($request->input('is_active', '1'), FILTER_VALIDATE_BOOLEAN);

        return $validated;
    }
}
