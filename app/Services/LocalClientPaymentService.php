<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillingPeriod;
use App\Models\Admin\LocalClientPaymentEvent;
use App\Support\BillableCampaignRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class LocalClientPaymentService
{
    public function __construct(
        private readonly LocalClientBillingReportService $reportService,
    ) {}

    public function authorizeToggle(Admin $admin, Model $campaign): void
    {
        if ($admin->isSuperAdmin()) {
            return;
        }

        $type = BillableCampaignRegistry::typeForModel($campaign);
        $adminColumn = BillableCampaignRegistry::TYPES[$type]['admin_column'];

        if ((int) $campaign->{$adminColumn} !== (int) $admin->id) {
            throw ValidationException::withMessages([
                'billing_payment_status' => 'You can only update payment status on your own campaigns.',
            ]);
        }
    }

    public function toggle(
        Model $campaign,
        string $newStatus,
        Admin $admin,
        ?string $note = null,
    ): Model {
        if (! in_array($newStatus, ['paid', 'unpaid'], true)) {
            throw ValidationException::withMessages([
                'billing_payment_status' => 'Invalid payment status.',
            ]);
        }

        if (! $campaign->local_client_id || ! $campaign->billing_snapshot) {
            throw ValidationException::withMessages([
                'billing_payment_status' => 'This campaign has no client billing.',
            ]);
        }

        $this->authorizeToggle($admin, $campaign);

        $oldStatus = $campaign->billing_payment_status;

        return DB::transaction(function () use ($campaign, $newStatus, $admin, $note, $oldStatus) {
            $campaign->forceFill([
                'billing_payment_status' => $newStatus,
                'billing_paid_at' => $newStatus === 'paid' ? now() : null,
                'billing_paid_by_admin_id' => $newStatus === 'paid' ? $admin->id : null,
                'billing_payment_note' => $note,
            ])->save();

            LocalClientPaymentEvent::create([
                'billable_type' => $campaign::class,
                'billable_id' => $campaign->id,
                'local_client_id' => $campaign->local_client_id,
                'campaign_no' => $campaign->campaign_no ?? null,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'note' => $note,
                'admin_id' => $admin->id,
                'created_at' => now(),
            ]);

            return $campaign->fresh();
        });
    }

    /**
     * @return array{updated_count: int}
     */
    public function markOpenPeriodPaid(LocalClient $client, Admin $admin, ?string $note = null): array
    {
        $campaigns = $this->reportService->unpaidBillableCampaignsForClient($client);

        if ($campaigns->isEmpty()) {
            throw ValidationException::withMessages([
                'billing_payment_status' => 'There are no unpaid campaigns for this client.',
            ]);
        }

        $paidAt = now();

        $periodStart = $campaigns
            ->map(fn (Model $campaign) => $campaign->created_at)
            ->filter()
            ->min();
        $periodEnd = $campaigns
            ->map(fn (Model $campaign) => $campaign->created_at)
            ->filter()
            ->max();
        $currency = strtoupper((string) ($campaigns->first()->billing_currency ?? $client->default_currency));

        $updatedCount = 0;
        $paidTotal = 0.0;

        DB::transaction(function () use ($campaigns, $admin, $note, $paidAt, $client, $periodStart, $periodEnd, $currency, &$updatedCount, &$paidTotal) {
            foreach ($campaigns as $campaign) {
                if ($this->markCampaignPaid($campaign, $admin, $note, $paidAt)) {
                    $updatedCount++;
                    $paidTotal += (float) ($campaign->billing_total ?? 0);
                }
            }

            if ($updatedCount === 0) {
                throw ValidationException::withMessages([
                    'billing_payment_status' => 'No billable campaigns could be marked paid. Ensure campaigns have billing snapshots.',
                ]);
            }

            if ($periodStart && $periodEnd && Schema::hasTable('local_client_billing_periods')) {
                LocalClientBillingPeriod::create([
                    'local_client_id' => $client->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'campaign_count' => $updatedCount,
                    'total_amount' => number_format($paidTotal, 2, '.', ''),
                    'currency' => $currency,
                    'paid_at' => $paidAt,
                    'paid_by_admin_id' => $admin->id,
                    'payment_note' => $note,
                ]);
            }
        });

        return ['updated_count' => $updatedCount];
    }

    private function markCampaignPaid(Model $campaign, Admin $admin, ?string $note, \Illuminate\Support\Carbon $paidAt): bool
    {
        if (! $campaign->local_client_id || ! $campaign->billing_snapshot) {
            return false;
        }

        $oldStatus = $campaign->billing_payment_status;

        $campaign->forceFill([
            'billing_payment_status' => 'paid',
            'billing_paid_at' => $paidAt,
            'billing_paid_by_admin_id' => $admin->id,
            'billing_payment_note' => $note,
        ])->save();

        LocalClientPaymentEvent::create([
            'billable_type' => $campaign::class,
            'billable_id' => $campaign->id,
            'local_client_id' => $campaign->local_client_id,
            'campaign_no' => $campaign->campaign_no ?? null,
            'old_status' => $oldStatus,
            'new_status' => 'paid',
            'note' => $note,
            'admin_id' => $admin->id,
            'created_at' => $paidAt,
        ]);

        return true;
    }
}
