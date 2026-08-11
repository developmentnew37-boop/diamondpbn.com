<?php

namespace App\Services;

use App\Data\BillingResult;
use App\Models\Admin\BillingCampaignType;
use App\Models\Admin\Domain;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillLine;
use App\Models\Admin\LocalClientDomainCategoryPrice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocalClientBillingService
{
    public function resolveBillingType(string $campaignKind, bool $isSticky = false): BillingCampaignType
    {
        if ($isSticky) {
            return BillingCampaignType::Sticky;
        }

        return match ($campaignKind) {
            'post', 'schedule_post' => BillingCampaignType::Post,
            'sidebar', 'schedule_sidebar' => BillingCampaignType::Sidebar,
            'hidden_links' => BillingCampaignType::HiddenLinks,
            'sticky' => BillingCampaignType::Sticky,
            default => throw ValidationException::withMessages([
                'local_client_id' => "Unsupported campaign kind for billing: {$campaignKind}",
            ]),
        };
    }

    /**
     * @param  list<int>  $domainIds
     */
    public function calculate(LocalClient $client, BillingCampaignType $type, array $domainIds): BillingResult
    {
        $domainIds = array_values(array_unique(array_map('intval', $domainIds)));

        if ($domainIds === []) {
            throw ValidationException::withMessages([
                'local_client_id' => 'At least one domain is required for billing.',
            ]);
        }

        $domains = Domain::query()
            ->with('domainCategory:id,name')
            ->whereIn('id', $domainIds)
            ->get()
            ->keyBy('id');

        if ($domains->count() !== count($domainIds)) {
            throw ValidationException::withMessages([
                'campaigns_domains' => 'One or more selected domains could not be found.',
            ]);
        }

        $categoryIds = $domains->pluck('domain_category_id')->unique()->filter()->values();

        $prices = LocalClientDomainCategoryPrice::query()
            ->where('local_client_id', $client->id)
            ->when($categoryIds->isNotEmpty(), fn ($query) => $query->whereIn('domain_category_id', $categoryIds))
            ->get()
            ->keyBy('domain_category_id');

        $lines = [];
        $total = 0.0;

        foreach ($domainIds as $domainId) {
            /** @var Domain $domain */
            $domain = $domains->get($domainId);
            $categoryId = (int) $domain->domain_category_id;
            $categoryName = $domain->domainCategory?->name ?? 'Unknown';

            $priceRow = $prices->get($categoryId);
            $unitPrice = $priceRow?->priceForType($type);

            if ($unitPrice === null || $unitPrice === '') {
                throw ValidationException::withMessages([
                    'local_client_id' => sprintf(
                        'Client "%s" has no %s price for domain category "%s".',
                        $client->name,
                        $type->label(),
                        $categoryName
                    ),
                ]);
            }

            $unit = round((float) $unitPrice, 2);
            $total += $unit;

            $lines[] = [
                'domain_id' => $domainId,
                'domain_name' => $domain->name ?? (string) $domainId,
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'billing_campaign_type' => $type->value,
                'unit_price' => number_format($unit, 2, '.', ''),
                'line_total' => number_format($unit, 2, '.', ''),
            ];
        }

        return new BillingResult(
            billingType: $type,
            total: number_format($total, 2, '.', ''),
            lines: $lines,
        );
    }

    public function applyToCampaign(Model $campaign, BillingResult $result, LocalClient $client, string $currency): void
    {
        $currency = strtoupper($currency);
        $snapshot = $result->toSnapshot($currency);

        $campaign->forceFill([
            'local_client_id' => $client->id,
            'billing_total' => $result->total,
            'billing_currency' => $currency,
            'billing_snapshot' => $snapshot,
            'billing_payment_status' => 'unpaid',
            'billing_paid_at' => null,
            'billing_paid_by_admin_id' => null,
            'billing_payment_note' => null,
        ])->save();

        $now = now();
        $billLines = collect($result->lines)->map(function (array $line) use ($client, $currency, $campaign, $now) {
            return [
                'local_client_id' => $client->id,
                'domain_id' => $line['domain_id'],
                'domain_category_id' => $line['category_id'],
                'billing_campaign_type' => $line['billing_campaign_type'],
                'unit_price' => $line['unit_price'],
                'line_total' => $line['line_total'],
                'currency' => $currency,
                'billable_type' => $campaign::class,
                'billable_id' => $campaign->id,
                'campaign_no' => $campaign->campaign_no ?? null,
                'snapshot' => json_encode($line),
                'created_at' => $now,
            ];
        })->all();

        if ($billLines !== []) {
            LocalClientBillLine::insert($billLines);
        }
    }

    /**
     * @param  list<int>  $domainIds
     */
    public function applyIfRequested(
        Model $campaign,
        ?int $localClientId,
        string $campaignKind,
        array $domainIds,
        bool $isSticky = false,
        ?string $currencyOverride = null,
    ): void {
        if (! $localClientId) {
            return;
        }

        $client = LocalClient::query()
            ->where('id', $localClientId)
            ->where('is_active', true)
            ->firstOrFail();

        $type = $this->resolveBillingType($campaignKind, $isSticky);
        $currency = strtoupper($currencyOverride ?: $client->default_currency);
        $result = $this->calculate($client, $type, $domainIds);

        DB::transaction(fn () => $this->applyToCampaign($campaign, $result, $client, $currency));
    }

    /**
     * @param  list<int>  $domainIds
     */
    public function applyFromRequest(
        Request $request,
        Model $campaign,
        array $domainIds,
        string $campaignKind,
        bool $isSticky = false,
    ): void {
        if (! $request->filled('local_client_id')) {
            return;
        }

        $domainIds = array_values(array_unique(array_filter(array_map('intval', $domainIds))));

        if ($domainIds === []) {
            throw ValidationException::withMessages([
                'local_client_id' => 'Selected client requires valid domains for billing. Resolve manual domains before submitting.',
            ]);
        }

        $this->applyIfRequested(
            $campaign,
            (int) $request->input('local_client_id'),
            $campaignKind,
            $domainIds,
            $isSticky,
            $request->input('billing_currency'),
        );

        $campaign->refresh();

        if (! $campaign->local_client_id || empty($campaign->billing_snapshot)) {
            throw ValidationException::withMessages([
                'local_client_id' => 'Client billing could not be applied to this campaign.',
            ]);
        }
    }
}
