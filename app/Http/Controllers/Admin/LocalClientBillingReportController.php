<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\LocalClient;
use App\Services\LocalClientBillingReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LocalClientBillingReportController extends Controller
{
    public function show(int $id, string $token, Request $request, LocalClientBillingReportService $reportService)
    {
        $payload = $this->buildReportPayload($id, $token, $request, $reportService);

        return view('admin.local-clients.billing-report-public', $payload);
    }

    public function export(int $id, string $token, Request $request, LocalClientBillingReportService $reportService)
    {
        $payload = $this->buildReportPayload($id, $token, $request, $reportService);
        $client = $payload['client'];
        $campaigns = $reportService->filterCampaigns($payload['allCampaigns'], $payload['filters']);
        $summary = $payload['summary'];
        $filters = $payload['filters'];
        $format = $request->string('format', 'pdf')->toString();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('admin.local-clients.billing-report-export-pdf', compact('client', 'campaigns', 'summary', 'filters'));
            $pdf->setPaper('A4', 'portrait');

            return $pdf->download('client-billing-'.$client->id.'.pdf');
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="client-billing-'.$client->id.'.csv"',
        ];

        $callback = function () use ($client, $campaigns, $filters) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Client', $client->name]);
            fputcsv($handle, ['Currency', $client->default_currency]);

            if ($filters['is_active'] ?? false) {
                fputcsv($handle, ['Filter', $filters['label'] ?? 'Custom range']);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Campaign No', 'Type', 'Date', 'Total', 'Currency', 'Status', 'Report URL']);

            foreach ($campaigns as $row) {
                fputcsv($handle, [
                    $row['campaign_no'],
                    $row['type_label'],
                    $row['created_at'],
                    $row['billing_total'],
                    $row['billing_currency'],
                    $row['billing_payment_status'],
                    $row['report_url'] ?? '',
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return array{
     *   client: LocalClient,
     *   campaigns: \Illuminate\Support\Collection,
     *   allCampaigns: \Illuminate\Support\Collection,
     *   summary: array<string, mixed>,
     *   filters: array<string, mixed>,
     *   availableMonths: \Illuminate\Support\Collection
     * }
     */
    private function buildReportPayload(int $id, string $token, Request $request, LocalClientBillingReportService $reportService): array
    {
        $client = $this->resolveClient($id, $token);
        $allCampaigns = $reportService->campaignsForClient($client);
        $filters = $reportService->resolveFilters($request);
        $filteredCampaigns = $reportService->filterCampaigns($allCampaigns, $filters);
        $summary = $reportService->formatSummaryForCurrency($filteredCampaigns, $client->default_currency);
        $campaigns = $reportService->paginateCollection($filteredCampaigns, $request, 15);
        $filteredTotal = $filteredCampaigns->count();
        $availableYears = $reportService->availableYears($allCampaigns);
        $exportPdfParams = $reportService->exportQueryParams($filters, 'pdf');
        $exportCsvParams = $reportService->exportQueryParams($filters, 'csv');
        $statusFilterOptions = $reportService->statusFilterOptions();
        $typeFilterOptions = $reportService->typeFilterOptions();
        $monthFilterOptions = $reportService->monthFilterOptions();

        return compact(
            'client',
            'campaigns',
            'allCampaigns',
            'filteredTotal',
            'summary',
            'filters',
            'availableYears',
            'exportPdfParams',
            'exportCsvParams',
            'statusFilterOptions',
            'typeFilterOptions',
            'monthFilterOptions',
        );
    }

    private function resolveClient(int $id, string $token): LocalClient
    {
        return LocalClient::query()
            ->where('id', $id)
            ->where('billing_report_token', $token)
            ->firstOrFail();
    }
}
