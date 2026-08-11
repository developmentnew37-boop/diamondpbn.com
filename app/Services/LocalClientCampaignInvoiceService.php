<?php

namespace App\Services;

use App\Models\Admin\LocalClient;
use App\Support\BillableCampaignRegistry;
use App\Support\CurrencyFormatter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Symfony\Component\HttpFoundation\Response;

class LocalClientCampaignInvoiceService
{
    public function buildInvoiceData(Model $campaign): array
    {
        if (! $campaign->local_client_id || ! $campaign->billing_snapshot) {
            abort(404, 'No billing data for this campaign.');
        }

        $client = LocalClient::findOrFail($campaign->local_client_id);
        $snapshot = is_array($campaign->billing_snapshot)
            ? $campaign->billing_snapshot
            : json_decode((string) $campaign->billing_snapshot, true);

        $currency = strtoupper((string) ($campaign->billing_currency ?? $client->default_currency));
        $typeLabel = BillableCampaignRegistry::labelForModel($campaign);

        $items = $this->groupLinesForInvoice($snapshot['lines'] ?? [], $typeLabel);

        $subtotal = (float) ($snapshot['total'] ?? $campaign->billing_total ?? 0);

        return [
            'invoice_number' => 'INV-'.($campaign->campaign_no ?? $campaign->id),
            'invoice_date' => now()->format('Y-m-d'),
            'campaign_no' => $campaign->campaign_no ?? null,
            'company_name' => config('invoice.company_name'),
            'company_email' => config('invoice.company_email'),
            'company_phone' => config('invoice.company_phone'),
            'client_name' => $client->name,
            'client_address' => trim(implode("\n", array_filter([
                $client->company_name,
                $client->address,
            ]))),
            'client_email' => $client->email,
            'items' => $items,
            'currency' => $currency,
            'final_total' => sprintf(
                '%s %s',
                $currency,
                number_format($subtotal, CurrencyFormatter::decimals($currency), '.', ',')
            ),
            'payment_status' => $campaign->billing_payment_status,
        ];
    }

    public function downloadPdf(Model $campaign): Response
    {
        $data = $this->buildInvoiceData($campaign);
        $pdf = Pdf::loadView('admin.invoice.campaign-billing-pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'invoice-'.($campaign->campaign_no ?? $campaign->id).'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Aggregate per-domain billing lines into category groups for a compact invoice.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array{title: string, price: float, quantity: int, subtotal: float}>
     */
    public function groupLinesForInvoice(array $lines, string $typeLabel): array
    {
        $groups = [];

        foreach ($lines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $categoryName = trim((string) ($line['category_name'] ?? 'Category'));
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
            $categoryId = (string) ($line['category_id'] ?? $categoryName);
            $key = $categoryId.'|'.number_format($unitPrice, 2, '.', '');

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'title' => sprintf('%s — %s', $typeLabel, $categoryName),
                    'price' => $unitPrice,
                    'quantity' => 0,
                    'subtotal' => 0.0,
                    'sort' => $categoryName,
                ];
            }

            $groups[$key]['quantity']++;
            $groups[$key]['subtotal'] += round((float) ($line['line_total'] ?? $unitPrice), 2);
        }

        $items = array_values($groups);

        usort($items, fn (array $a, array $b) => strcasecmp($a['sort'], $b['sort']));

        return array_map(function (array $item) {
            unset($item['sort']);

            return [
                'title' => $item['title'],
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'subtotal' => round($item['subtotal'], 2),
            ];
        }, $items);
    }
}
