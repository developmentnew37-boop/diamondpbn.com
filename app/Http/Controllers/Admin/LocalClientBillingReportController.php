<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientManualBilling;
use App\Services\LocalClientBillingReportService;
use App\Services\LocalClientPriceMatrixService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\SimpleExcel\SimpleExcelWriter;

class LocalClientBillingReportController extends Controller
{
    public function manual(int $manualBilling, string $token): View
    {
        $bill = $this->manualBill($manualBilling, $token);

        return view('admin.local-clients.manual-billing-report', [
            'bill' => $bill,
            'lines' => $bill->reportLines(),
        ]);
    }

    public function exportManual(int $manualBilling, string $token)
    {
        $bill = $this->manualBill($manualBilling, $token);
        $lines = $bill->reportLines();
        $date = $bill->created_at?->format('d M Y') ?? '-';
        $filename = 'campaign-report-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $bill->campaign_no).'.xlsx';

        $writer = SimpleExcelWriter::streamDownload($filename)->addHeader([
            'S.No',
            'Domain',
            'BlogPost',
            'Keyword',
            'URL',
            'Status',
            'Date',
        ]);

        foreach ($lines as $index => $line) {
            $writer->addRow([
                'S.No' => $index + 1,
                'Domain' => $line['domain'] !== '' ? $line['domain'] : '-',
                'BlogPost' => $line['live'] !== '' ? $line['live'] : '-',
                'Keyword' => $line['keyword'] !== '' ? $line['keyword'] : '-',
                'URL' => $line['url'] !== '' ? $line['url'] : '-',
                'Status' => 'Live',
                'Date' => $date,
            ]);
        }

        $writer->toBrowser();
    }

    private function manualBill(int $manualBilling, string $token): LocalClientManualBilling
    {
        return LocalClientManualBilling::query()
            ->whereKey($manualBilling)
            ->where('report_token', $token)
            ->firstOrFail();
    }

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
        $overallOutstanding = $payload['overallOutstanding'];
        $filters = $payload['filters'];
        $format = $request->string('format', 'pdf')->toString();

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('admin.local-clients.billing-report-export-pdf', compact('client', 'campaigns', 'summary', 'overallOutstanding', 'filters'));
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
            fputcsv($handle, ['Campaign No', 'Type', 'Date', 'Total', 'Already Paid', 'Due Now', 'Currency', 'Status', 'Report URL']);

            foreach ($campaigns as $row) {
                fputcsv($handle, [
                    $row['campaign_no'],
                    $row['type_label'],
                    $row['created_at'],
                    $row['billing_total'],
                    ! empty($row['billing_has_credit']) ? $row['billing_amount_paid'] : '',
                    ! empty($row['billing_has_credit']) ? $row['billing_balance_due'] : '',
                    $row['billing_currency'],
                    $row['billing_payment_status'],
                    ! empty($row['campaign_deleted']) ? 'Deleted' : ($row['report_url'] ?? ''),
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
     *   overallOutstanding: array<string, mixed>,
     *   filters: array<string, mixed>,
     *   rateMatrix: \Illuminate\Support\Collection,
     *   rateSourceLabel: string
     * }
     */
    private function buildReportPayload(int $id, string $token, Request $request, LocalClientBillingReportService $reportService): array
    {
        $client = $this->resolveClient($id, $token);
        $client->loadMissing('rateList:id,name');

        $allCampaigns = $reportService->campaignsForClient($client);
        $filters = $reportService->resolveFilters($request);
        $filteredCampaigns = $reportService->filterCampaigns($allCampaigns, $filters);
        $scopeCampaigns = $reportService->filterCampaigns(
            $allCampaigns,
            $reportService->filtersWithoutStatus($filters)
        );
        // All → full month/type scope; Paid/Unpaid → match the table rows.
        $summaryCampaigns = ($filters['status_selection'] ?? 'unpaid') === 'all'
            ? $scopeCampaigns
            : $filteredCampaigns;
        $summary = $reportService->formatSummaryForCurrency($summaryCampaigns, $client->default_currency);
        $overallOutstanding = $reportService->formatOverallUnpaidSummary($allCampaigns, $client->default_currency);
        $statusCounts = $reportService->statusCounts($scopeCampaigns);
        $campaigns = $reportService->paginateCollection($filteredCampaigns, $request, 50);
        $filteredTotal = $filteredCampaigns->count();
        $availableYears = $reportService->availableYears($allCampaigns);
        $exportPdfParams = $reportService->exportQueryParams($filters, 'pdf');
        $exportCsvParams = $reportService->exportQueryParams($filters, 'csv');
        $statusFilterOptions = $reportService->statusFilterOptions();
        $typeFilterOptions = $reportService->typeFilterOptions();
        $monthFilterOptions = $reportService->monthFilterOptions();

        $matrixService = app(LocalClientPriceMatrixService::class);
        $rateMatrix = $client->rate_list_id && $client->rateList
            ? $matrixService->buildGridForRateList($client->rateList)
            : $matrixService->buildGrid($client);
        $rateSourceLabel = $client->rateList?->name ?? 'Custom rates';

        return compact(
            'client',
            'campaigns',
            'allCampaigns',
            'filteredTotal',
            'summary',
            'overallOutstanding',
            'statusCounts',
            'filters',
            'availableYears',
            'exportPdfParams',
            'exportCsvParams',
            'statusFilterOptions',
            'typeFilterOptions',
            'monthFilterOptions',
            'rateMatrix',
            'rateSourceLabel',
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
