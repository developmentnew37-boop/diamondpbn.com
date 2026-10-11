<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillingPeriod;
use App\Models\Admin\LocalClientDeletedCampaignBilling;
use App\Models\Admin\LocalClientInstallment;
use App\Models\Admin\LocalClientInstallmentAllocation;
use App\Models\Admin\LocalClientManualBilling;
use App\Models\Admin\LocalClientPaymentEvent;
use App\Support\BillableCampaignRegistry;
use App\Support\CurrencyFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        if (! in_array($newStatus, ['paid', 'unpaid', 'semi_paid'], true)) {
            throw ValidationException::withMessages([
                'billing_payment_status' => 'Invalid payment status.',
            ]);
        }

        if ($newStatus === 'semi_paid' && ($campaign->billing_payment_status ?? '') !== 'semi_paid') {
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

        $oldStatus = (string) ($campaign->billing_payment_status ?? 'unpaid');

        if ($newStatus === 'unpaid' && $oldStatus !== 'unpaid') {
            if (! $admin->isSuperAdmin()) {
                throw ValidationException::withMessages([
                    'billing_payment_status' => 'Only a Super Admin can mark a campaign unpaid.',
                ]);
            }

            $paidAt = $campaign->billing_paid_at ? Carbon::parse($campaign->billing_paid_at) : null;
            $client = LocalClient::query()->find($campaign->local_client_id);

            if (! $client) {
                throw ValidationException::withMessages([
                    'billing_payment_status' => 'This campaign has no client billing.',
                ]);
            }

            return DB::transaction(function () use ($campaign, $client, $admin, $note, $paidAt) {
                $this->lockClient($client);
                $this->unmarkSelected($client, [$campaign], $admin, $note);

                if ($paidAt) {
                    $this->deleteFrozenPeriodAround($client, $paidAt);
                }

                return $campaign->fresh();
            });
        }

        return DB::transaction(function () use ($campaign, $newStatus, $admin, $note, $oldStatus) {
            $updates = [
                'billing_payment_status' => $newStatus,
                'billing_paid_at' => $newStatus === 'paid' ? now() : null,
                'billing_paid_by_admin_id' => $newStatus === 'paid' ? $admin->id : null,
                'billing_payment_note' => $note,
            ];

            if ($newStatus === 'paid' || $newStatus === 'unpaid') {
                $updates['billing_amount_paid'] = null;
            }

            $campaign->forceFill($updates)->save();

            LocalClientPaymentEvent::create([
                'billable_type' => $campaign::class,
                'billable_id' => $campaign->id,
                'local_client_id' => $campaign->local_client_id,
                'campaign_no' => $this->paymentEventCampaignNo($campaign->campaign_no ?? null),
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
        $archived = $this->unpaidDeletedCampaignsForClient($client);

        if ($campaigns->isEmpty() && $archived->isEmpty()) {
            throw ValidationException::withMessages([
                'billing_payment_status' => 'There are no unpaid campaigns for this client.',
            ]);
        }

        $paidAt = now();

        $createdAts = $campaigns
            ->map(fn (Model $campaign) => $campaign->created_at)
            ->merge($archived->map(fn (LocalClientDeletedCampaignBilling $record) => $record->campaign_created_at))
            ->filter();
        $periodStart = $createdAts->min();
        $periodEnd = $createdAts->max();
        $currency = strtoupper((string) (
            $campaigns->first()?->billing_currency
            ?? $archived->first()?->billing_currency
            ?? $client->default_currency
        ));

        $updatedCount = 0;
        $paidTotal = 0.0;

        DB::transaction(function () use ($campaigns, $archived, $admin, $note, $paidAt, $client, $periodStart, $periodEnd, $currency, &$updatedCount, &$paidTotal) {
            foreach ($campaigns as $campaign) {
                $due = LocalClientBillingService::balanceDue(
                    $campaign->billing_total,
                    $campaign->billing_amount_paid,
                    $campaign->billing_payment_status,
                );

                if ($this->markCampaignPaid($campaign, $admin, $note, $paidAt)) {
                    $updatedCount++;
                    $paidTotal += $due;
                }
            }

            foreach ($archived as $record) {
                $due = LocalClientBillingService::balanceDue(
                    $record->billing_total,
                    $record->billing_amount_paid,
                    $record->billing_payment_status,
                );

                $this->markDeletedCampaignPaid($record, $admin, $note, $paidAt);
                $updatedCount++;
                $paidTotal += $due;
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

    /**
     * Mark selected campaigns in a paid period unpaid.
     *
     * Null selection marks every campaign in the period. Installment cash stays
     * on its installment and is moved onto the other open campaigns.
     *
     * @param  list<string>|null  $selectionKeys
     * @return array{updated_count: int, skipped_count: int}
     */
    public function markPaidPeriodUnpaid(LocalClient $client, Carbon $paidAt, Admin $admin, ?array $selectionKeys = null): array
    {
        $from = $paidAt->copy()->subSecond();
        $to = $paidAt->copy()->addSecond();
        $updatedCount = 0;

        DB::transaction(function () use ($client, $admin, $paidAt, $from, $to, $selectionKeys, &$updatedCount) {
            $this->lockClient($client);

            $records = $this->paidBillablesInWindow($client, $from, $to);

            if ($selectionKeys !== null) {
                $wanted = array_fill_keys($selectionKeys, true);
                $records = array_values(array_filter(
                    $records,
                    fn (Model $record) => isset($wanted[$this->recordSelectionKey($record)]),
                ));
            }

            if ($records === []) {
                throw ValidationException::withMessages([
                    'billing_payment_status' => 'No paid campaigns were found for this billing period.',
                ]);
            }

            $this->unmarkSelected($client, $records, $admin, null);
            $this->deleteFrozenPeriodAround($client, $paidAt);
            $updatedCount = count($records);
        });

        return [
            'updated_count' => $updatedCount,
            'skipped_count' => 0,
        ];
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $campaigns
     * @param  array<string, int>  $coveredCents
     * @return list<array<string, mixed>>
     */
    public function unpaidCandidatesForPeriod(iterable $campaigns, array $period, array $coveredCents): array
    {
        if (! empty($period['is_open']) || empty($period['paid_at'])) {
            return [];
        }

        $from = $period['paid_at']->copy()->subSecond();
        $to = $period['paid_at']->copy()->addSecond();
        $rows = [];

        foreach ($campaigns as $row) {
            if (($row['billing_payment_status'] ?? '') !== 'paid' || empty($row['billing_paid_at'])) {
                continue;
            }

            $rowPaidAt = Carbon::parse($row['billing_paid_at']);
            if ($rowPaidAt->lt($from) || $rowPaidAt->gt($to)) {
                continue;
            }

            $covered = $coveredCents[$this->billableKeyForCampaignRow($row)] ?? 0;
            $currency = (string) ($row['billing_currency'] ?: 'USD');
            $rows[] = $row + [
                'selection_key' => $this->selectionKeyForCampaignRow($row),
                'installment_cents' => $covered,
                'installment_formatted' => $covered > 0
                    ? CurrencyFormatter::format(self::fromCents($covered), $currency)
                    : null,
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, int>
     */
    public function installmentCoveredAmounts(LocalClient $client): array
    {
        if (! Schema::hasTable('local_client_installment_allocations') || ! Schema::hasTable('local_client_installments')) {
            return [];
        }

        $totals = [];
        $rows = LocalClientInstallmentAllocation::query()
            ->whereIn('installment_id', LocalClientInstallment::query()
                ->where('local_client_id', $client->id)
                ->select('id'))
            ->get(['billable_type', 'billable_id', 'amount']);

        foreach ($rows as $row) {
            $key = $row->billable_type.'#'.$row->billable_id;
            $totals[$key] = ($totals[$key] ?? 0) + self::toCents($row->amount);
        }

        return $totals;
    }

    public function selectionKeyForCampaignRow(array $row): string
    {
        $type = (string) ($row['type'] ?? '');
        $id = (int) ($row['id'] ?? 0);

        if (! empty($row['campaign_deleted'])) {
            return 'deleted:'.$type.':'.$id;
        }

        if (str_starts_with($type, 'manual_')) {
            return 'manual:'.$id;
        }

        return 'live:'.$type.':'.$id;
    }

    public function billableKeyForCampaignRow(array $row): string
    {
        $type = (string) ($row['type'] ?? '');
        $id = (int) ($row['id'] ?? 0);

        if (str_starts_with($type, 'manual_')) {
            return LocalClientManualBilling::class.'#'.$id;
        }

        $class = BillableCampaignRegistry::TYPES[$type]['class'] ?? null;

        return $class ? $class.'#'.$id : '';
    }

    private function markCampaignPaid(Model $campaign, Admin $admin, ?string $note, \Illuminate\Support\Carbon $paidAt): bool
    {
        if (! $campaign->local_client_id || ! $campaign->billing_snapshot) {
            return false;
        }

        $oldStatus = $campaign->billing_payment_status;

        $campaign->forceFill([
            'billing_payment_status' => 'paid',
            'billing_amount_paid' => null,
            'billing_paid_at' => $paidAt,
            'billing_paid_by_admin_id' => $admin->id,
            'billing_payment_note' => $note,
        ])->save();

        LocalClientPaymentEvent::create([
            'billable_type' => $campaign::class,
            'billable_id' => $campaign->id,
            'local_client_id' => $campaign->local_client_id,
            'campaign_no' => $this->paymentEventCampaignNo($campaign->campaign_no ?? null),
            'old_status' => $oldStatus,
            'new_status' => 'paid',
            'note' => $note,
            'admin_id' => $admin->id,
            'created_at' => $paidAt,
        ]);

        return true;
    }

    /**
     * @return Collection<int, LocalClientDeletedCampaignBilling>
     */
    private function unpaidDeletedCampaignsForClient(LocalClient $client): Collection
    {
        if (! Schema::hasTable('local_client_deleted_campaign_billings')) {
            return collect();
        }

        return LocalClientDeletedCampaignBilling::query()
            ->where('local_client_id', $client->id)
            ->where(function ($query) {
                $query->whereNull('billing_payment_status')
                    ->orWhere('billing_payment_status', '!=', 'paid');
            })
            ->get();
    }

    private function markDeletedCampaignPaid(
        LocalClientDeletedCampaignBilling $record,
        Admin $admin,
        ?string $note,
        Carbon $paidAt,
    ): void {
        $oldStatus = $record->billing_payment_status;
        $campaignNo = $record->campaign_no;

        $record->forceFill([
            'billing_payment_status' => 'paid',
            'billing_amount_paid' => null,
            'billing_paid_at' => $paidAt,
        ])->save();

        LocalClientPaymentEvent::create([
            'billable_type' => $record->billable_type,
            'billable_id' => $record->billable_id,
            'local_client_id' => $record->local_client_id,
            'campaign_no' => $campaignNo === null || $campaignNo === ''
                ? null
                : mb_substr((string) $campaignNo, 0, 64),
            'old_status' => $oldStatus,
            'new_status' => 'paid',
            'note' => $note,
            'admin_id' => $admin->id,
            'created_at' => $paidAt,
        ]);
    }

    /**
     * Apply a client payment to open campaigns, oldest first.
     *
     * @return array{installment_id: int, amount: string, currency: string, paid_count: int, semi_paid_count: int}
     */
    public function recordInstallment(
        LocalClient $client,
        float|string $amount,
        Admin $admin,
        ?string $note = null,
        string $source = 'payment',
        bool $capToOpenBalance = false,
    ): array {
        $currency = strtoupper((string) $client->default_currency);
        $amountCents = self::toCents($amount);

        if ($source === 'payment' && $amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Enter an installment amount greater than zero.',
            ]);
        }

        return DB::transaction(function () use ($client, $admin, $note, $source, $capToOpenBalance, $currency, $amountCents) {
            $this->lockClient($client);

            $items = $this->openItemsForCurrency($client, $currency);
            $openCents = array_sum(array_column($items, 'due_cents'));

            if ($openCents <= 0 || $amountCents <= 0) {
                if ($source === 'reallocation') {
                    return [
                        'installment_id' => 0,
                        'amount' => '0.00',
                        'currency' => $currency,
                        'paid_count' => 0,
                        'semi_paid_count' => 0,
                    ];
                }

                throw ValidationException::withMessages([
                    'amount' => 'There is no open balance in '.$currency.' for this client.',
                ]);
            }

            if ($amountCents > $openCents) {
                if (! $capToOpenBalance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Installment cannot be more than the open '.$currency.' balance of '.self::fromCents($openCents).'.',
                    ]);
                }

                $amountCents = $openCents;
            }

            $paidAt = now();
            $installment = LocalClientInstallment::query()->create([
                'local_client_id' => $client->id,
                'amount' => self::fromCents($amountCents),
                'currency' => $currency,
                'source' => $source,
                'note' => $note,
                'admin_id' => $admin->id,
                'created_at' => $paidAt,
            ]);

            $remaining = $amountCents;
            $paidCount = 0;
            $semiPaidCount = 0;

            foreach ($items as $item) {
                if ($remaining <= 0) {
                    break;
                }

                $apply = min($remaining, $item['due_cents']);
                if ($apply <= 0) {
                    continue;
                }

                $resulting = $item['collected_cents'] + $apply;
                $newStatus = $resulting >= $item['total_cents'] ? 'paid' : 'semi_paid';

                $this->applyCollectedState(
                    $item['model'],
                    $newStatus === 'paid' ? $item['total_cents'] : $resulting,
                    $item['total_cents'],
                    $paidAt,
                    $admin->id,
                    $note,
                );

                LocalClientInstallmentAllocation::query()->create([
                    'installment_id' => $installment->id,
                    'billable_type' => $item['billable_type'],
                    'billable_id' => $item['billable_id'],
                    'deleted_campaign_billing_id' => $item['deleted_campaign_billing_id'],
                    'campaign_no' => $item['campaign_no'],
                    'amount' => self::fromCents($apply),
                    'previous_collected_amount' => self::fromCents($item['collected_cents']),
                    'resulting_collected_amount' => self::fromCents($newStatus === 'paid' ? $item['total_cents'] : $resulting),
                    'billing_total' => self::fromCents($item['total_cents']),
                    'old_status' => $item['status'],
                    'new_status' => $newStatus,
                    'created_at' => $paidAt,
                ]);

                $this->writePaymentEvent(
                    $item['billable_type'],
                    $item['billable_id'],
                    (int) $client->id,
                    $item['campaign_no'],
                    $item['status'],
                    $newStatus,
                    $note,
                    $admin->id,
                    $paidAt,
                );

                if ($newStatus === 'paid') {
                    $paidCount++;
                } else {
                    $semiPaidCount++;
                }

                $remaining -= $apply;
            }

            return [
                'installment_id' => (int) $installment->id,
                'amount' => self::fromCents($amountCents),
                'currency' => $currency,
                'paid_count' => $paidCount,
                'semi_paid_count' => $semiPaidCount,
            ];
        });
    }

    /**
     * Move installment money that no longer fits on a campaign after its bill decreases.
     */
    public function reallocateInstallmentSurplus(Model $campaign, int $surplusCents, ?Admin $admin = null): void
    {
        if ($surplusCents <= 0 || ! $this->campaignHasInstallmentAllocation($campaign)) {
            return;
        }

        $clientId = $campaign->getAttribute('local_client_id');
        if (! $clientId) {
            return;
        }

        $client = $campaign->relationLoaded('localClient')
            ? $campaign->getRelation('localClient')
            : LocalClient::query()->find($clientId);

        if (! $client) {
            return;
        }

        $admin ??= Auth::guard('admin')->user();

        if (! $admin && $campaign->getAttribute('billing_paid_by_admin_id')) {
            $admin = Admin::query()->find($campaign->getAttribute('billing_paid_by_admin_id'));
        }

        if (! $admin && $client->created_by_admin_id) {
            $admin = Admin::query()->find($client->created_by_admin_id);
        }

        if (! $admin) {
            Log::warning('Installment surplus was not moved because no admin could be recorded.', [
                'campaign_type' => $campaign::class,
                'campaign_id' => $campaign->getKey(),
                'surplus_cents' => $surplusCents,
            ]);

            return;
        }

        $this->recordInstallment(
            $client,
            self::fromCents($surplusCents),
            $admin,
            'Leftover installment moved after the campaign bill decreased.',
            'reallocation',
            true,
        );
    }

    /**
     * Reverse the newest customer installment.
     *
     * When an id is given, that row must still be the newest. A second click
     * that names an older row is refused so two payments are not undone.
     * Each campaign is restored once, from the amount collected before this
     * installment first touched it.
     *
     * @return array{restored_count: int}
     */
    public function voidLatestInstallment(LocalClient $client, Admin $admin, ?int $installmentId = null): array
    {
        return DB::transaction(function () use ($client, $admin, $installmentId) {
            $this->lockClient($client);

            $installment = LocalClientInstallment::query()
                ->where('local_client_id', $client->id)
                ->latest('id')
                ->with(['allocations' => fn ($query) => $query->orderBy('id')])
                ->first();

            if (! $installment) {
                throw ValidationException::withMessages([
                    'installment' => 'There is no installment to void.',
                ]);
            }

            if ($installmentId !== null && (int) $installment->id !== $installmentId) {
                throw ValidationException::withMessages([
                    'installment' => 'This installment is no longer the latest and was not voided.',
                ]);
            }

            if ($installment->source !== 'payment') {
                throw ValidationException::withMessages([
                    'installment' => 'The latest entry moved leftover installment money after a billing change and cannot be voided.',
                ]);
            }

            $targets = [];

            foreach ($installment->allocations as $allocation) {
                $target = $this->resolveAllocationTarget($allocation, (int) $client->id);
                $currentTotal = self::toCents($target->getAttribute('billing_total'));
                $recordedTotal = self::toCents($allocation->billing_total);

                if ($currentTotal !== $recordedTotal) {
                    throw ValidationException::withMessages([
                        'installment' => 'This installment cannot be voided because a campaign bill changed after it was recorded.',
                    ]);
                }

                $key = $allocation->billable_type.'#'.$allocation->billable_id;

                if (isset($targets[$key])) {
                    continue;
                }

                $targets[$key] = [$allocation, $target];
            }

            $restoredAt = now();
            $restoredCount = 0;

            foreach ($targets as [$allocation, $target]) {
                $previousCents = self::toCents($allocation->previous_collected_amount);
                $totalCents = self::toCents($allocation->billing_total);
                $oldStatus = (string) ($target->getAttribute('billing_payment_status') ?: $allocation->new_status);
                $newStatus = $this->applyCollectedState(
                    $target,
                    $previousCents,
                    $totalCents,
                    null,
                    null,
                    'Installment voided',
                );

                $this->writePaymentEvent(
                    (string) $allocation->billable_type,
                    (int) $allocation->billable_id,
                    (int) $client->id,
                    $allocation->campaign_no,
                    $oldStatus,
                    $newStatus,
                    'Installment voided',
                    $admin->id,
                    $restoredAt,
                );

                $restoredCount++;
            }

            $installment->allocations()->delete();
            $installment->delete();

            return ['restored_count' => $restoredCount];
        });
    }

    /**
     * Account figures for the installment ledger. Date filters do not apply.
     *
     * @param  iterable<int, array<string, mixed>>  $campaigns
     * @return array{
     *   currency: string,
     *   month_label: string,
     *   still_to_pay: string,
     *   received: string,
     *   month_billed: string,
     *   month_received: string
     * }
     */
    public function installmentAccountSummary(LocalClient $client, iterable $campaigns): array
    {
        $currency = strtoupper((string) $client->default_currency);
        $monthStart = now()->copy()->startOfMonth();
        $monthEnd = now()->copy()->endOfMonth();
        $monthKey = $monthStart->format('Y-m');

        $receivedCents = 0;
        $monthReceivedCents = 0;

        $payments = LocalClientInstallment::query()
            ->where('local_client_id', $client->id)
            ->where('source', 'payment')
            ->where('currency', $currency)
            ->get(['amount', 'created_at']);

        foreach ($payments as $payment) {
            $cents = self::toCents($payment->amount);
            $receivedCents += $cents;

            if ($payment->created_at && $payment->created_at->between($monthStart, $monthEnd)) {
                $monthReceivedCents += $cents;
            }
        }

        $monthBilledCents = 0;

        foreach ($campaigns as $row) {
            if (empty($row['created_at']) || Carbon::parse($row['created_at'])->format('Y-m') !== $monthKey) {
                continue;
            }

            $rowCurrency = strtoupper((string) ($row['billing_currency'] ?? $currency));
            if ($rowCurrency !== $currency) {
                continue;
            }

            $monthBilledCents += self::toCents($row['billing_total'] ?? 0);
        }

        $open = $this->openBalanceSummary($client);

        return [
            'currency' => $currency,
            'month_label' => $monthStart->format('F Y'),
            'still_to_pay' => CurrencyFormatter::format($open['open_balance'], $currency),
            'received' => CurrencyFormatter::format(self::fromCents($receivedCents), $currency),
            'month_billed' => CurrencyFormatter::format(self::fromCents($monthBilledCents), $currency),
            'month_received' => CurrencyFormatter::format(self::fromCents($monthReceivedCents), $currency),
        ];
    }

    /**
     * @return array{open_balance: string, currency: string, other_balances: array<string, string>}
     */
    public function openBalanceSummary(LocalClient $client): array
    {
        $default = strtoupper((string) $client->default_currency);
        $byCurrency = [];

        foreach ($this->collectOpenItems($client) as $item) {
            $byCurrency[$item['currency']] = ($byCurrency[$item['currency']] ?? 0) + $item['due_cents'];
        }

        $other = [];
        foreach ($byCurrency as $currency => $cents) {
            if ($currency === $default || $cents <= 0) {
                continue;
            }

            $other[$currency] = self::fromCents($cents);
        }

        return [
            'open_balance' => self::fromCents($byCurrency[$default] ?? 0),
            'currency' => $default,
            'other_balances' => $other,
        ];
    }

    public static function toCents(float|string|null $amount): int
    {
        $normalized = number_format((float) ($amount ?? 0), 2, '.', '');
        $negative = str_starts_with($normalized, '-');
        $normalized = ltrim($normalized, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalized), 2, '0');

        $cents = ((int) $whole * 100) + (int) substr(str_pad($fraction, 2, '0'), 0, 2);

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '').intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function campaignHasInstallmentAllocation(Model $campaign): bool
    {
        return $this->billableHasInstallmentAllocation($campaign::class, (int) $campaign->getKey());
    }

    private function billableHasInstallmentAllocation(string $billableType, int $billableId): bool
    {
        if ($billableId <= 0 || ! Schema::hasTable('local_client_installment_allocations')) {
            return false;
        }

        return LocalClientInstallmentAllocation::query()
            ->where('billable_type', $billableType)
            ->where('billable_id', $billableId)
            ->exists();
    }

    /**
     * @return list<Model>
     */
    private function paidBillablesInWindow(LocalClient $client, Carbon $from, Carbon $to): array
    {
        $classes = collect(BillableCampaignRegistry::TYPES)
            ->pluck('class')
            ->push(\App\Models\Admin\LocalClientManualBilling::class)
            ->push(LocalClientDeletedCampaignBilling::class)
            ->unique()
            ->values();

        $records = [];

        foreach ($classes as $class) {
            $model = new $class;

            if (! Schema::hasTable($model->getTable()) || ! Schema::hasColumn($model->getTable(), 'billing_paid_at')) {
                continue;
            }

            $matches = $class::query()
                ->where('local_client_id', $client->id)
                ->where('billing_payment_status', 'paid')
                ->whereBetween('billing_paid_at', [$from, $to])
                ->get();

            foreach ($matches as $record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * @param  list<Model>  $records
     */
    private function unmarkSelected(LocalClient $client, array $records, Admin $admin, ?string $note): void
    {
        $excluded = [];
        $identities = [];

        foreach ($records as $record) {
            [$type, $id] = $this->billableIdentity($record);
            $identities[] = [$type, $id];
            $excluded[$type.'#'.$id] = true;
        }

        $allocations = collect();

        if ($identities !== [] && Schema::hasTable('local_client_installment_allocations')) {
            $allocations = LocalClientInstallmentAllocation::query()
                ->whereIn('installment_id', LocalClientInstallment::query()
                    ->where('local_client_id', $client->id)
                    ->select('id'))
                ->where(function ($query) use ($identities) {
                    foreach ($identities as [$type, $id]) {
                        $query->orWhere(function ($match) use ($type, $id) {
                            $match->where('billable_type', $type)->where('billable_id', $id);
                        });
                    }
                })
                ->get();
        }

        if ($allocations->isNotEmpty()) {
            $items = array_values(array_filter(
                $this->collectOpenItems($client),
                fn (array $item) => ! isset($excluded[$item['billable_type'].'#'.$item['billable_id']]),
            ));
            $paidAt = now();

            foreach ($allocations->groupBy('installment_id')->sortKeys() as $installmentId => $rows) {
                $installment = LocalClientInstallment::query()->find($installmentId);

                if (! $installment || (int) $installment->local_client_id !== (int) $client->id) {
                    throw ValidationException::withMessages([
                        'billing_payment_status' => 'This installment cash has no other unpaid campaign to move onto. Revert that installment on the Installments page.',
                    ]);
                }

                $cents = 0;
                foreach ($rows as $allocation) {
                    $cents += self::toCents($allocation->amount);
                }

                if ($cents > 0) {
                    $this->placeInstallmentCents($installment, $cents, $items, $admin, $paidAt);
                }

                foreach ($rows as $allocation) {
                    $allocation->delete();
                }
            }
        }

        foreach ($records as $record) {
            [$type, $id] = $this->billableIdentity($record);
            $this->markBillUnpaid($record, $admin, $type, $id, $note);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function placeInstallmentCents(
        LocalClientInstallment $installment,
        int $cents,
        array &$items,
        Admin $admin,
        Carbon $paidAt,
    ): void {
        $currency = strtoupper((string) $installment->currency);
        $remaining = $cents;

        foreach ($items as $index => $item) {
            if ($remaining <= 0) {
                break;
            }

            if ($item['currency'] !== $currency || $item['due_cents'] <= 0) {
                continue;
            }

            $apply = min($remaining, $item['due_cents']);
            $previous = $item['collected_cents'];
            $resulting = $previous + $apply;
            $newStatus = $this->applyCollectedState(
                $item['model'],
                $resulting,
                $item['total_cents'],
                $paidAt,
                $admin->id,
                'Installment moved after a campaign was marked unpaid.',
            );

            LocalClientInstallmentAllocation::query()->create([
                'installment_id' => $installment->id,
                'billable_type' => $item['billable_type'],
                'billable_id' => $item['billable_id'],
                'deleted_campaign_billing_id' => $item['deleted_campaign_billing_id'],
                'campaign_no' => $item['campaign_no'],
                'amount' => self::fromCents($apply),
                'previous_collected_amount' => self::fromCents($previous),
                'resulting_collected_amount' => self::fromCents($newStatus === 'paid' ? $item['total_cents'] : $resulting),
                'billing_total' => self::fromCents($item['total_cents']),
                'old_status' => $item['status'],
                'new_status' => $newStatus,
                'created_at' => $paidAt,
            ]);

            $this->writePaymentEvent(
                $item['billable_type'],
                $item['billable_id'],
                (int) $installment->local_client_id,
                $item['campaign_no'],
                $item['status'],
                $newStatus,
                'Installment moved after a campaign was marked unpaid.',
                $admin->id,
                $paidAt,
            );

            $collected = $newStatus === 'paid' ? $item['total_cents'] : $resulting;
            $items[$index]['collected_cents'] = $collected;
            $items[$index]['due_cents'] = $item['total_cents'] - $collected;
            $items[$index]['status'] = $newStatus;
            $remaining -= $apply;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'billing_payment_status' => 'This installment cash has no other unpaid campaign to move onto. Revert that installment on the Installments page.',
            ]);
        }
    }

    private function deleteFrozenPeriodAround(LocalClient $client, Carbon $paidAt): void
    {
        if (! Schema::hasTable('local_client_billing_periods')) {
            return;
        }

        LocalClientBillingPeriod::query()
            ->where('local_client_id', $client->id)
            ->whereBetween('paid_at', [$paidAt->copy()->subSecond(), $paidAt->copy()->addSecond()])
            ->delete();
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function billableIdentity(Model $record): array
    {
        if ($record instanceof LocalClientDeletedCampaignBilling) {
            return [(string) $record->billable_type, (int) $record->billable_id];
        }

        return [$record::class, (int) $record->getKey()];
    }

    private function recordSelectionKey(Model $record): string
    {
        if ($record instanceof LocalClientDeletedCampaignBilling) {
            return 'deleted:'.$record->campaign_type.':'.$record->billable_id;
        }

        if ($record instanceof LocalClientManualBilling) {
            return 'manual:'.$record->getKey();
        }

        return 'live:'.BillableCampaignRegistry::typeForModel($record).':'.$record->getKey();
    }

    private function markBillUnpaid(Model $record, Admin $admin, string $billableType, int $billableId, ?string $note = null): void
    {
        $oldStatus = $record->getAttribute('billing_payment_status');
        $updates = [
            'billing_payment_status' => 'unpaid',
            'billing_paid_at' => null,
        ];
        $table = $record->getTable();

        if (Schema::hasColumn($table, 'billing_amount_paid')) {
            $updates['billing_amount_paid'] = null;
        }

        if (Schema::hasColumn($table, 'billing_paid_by_admin_id')) {
            $updates['billing_paid_by_admin_id'] = null;
        }

        if (Schema::hasColumn($table, 'billing_payment_note')) {
            $updates['billing_payment_note'] = $note;
        }

        $record->forceFill($updates)->save();

        $campaignNo = $record->getAttribute('campaign_no');

        LocalClientPaymentEvent::create([
            'billable_type' => $billableType,
            'billable_id' => $billableId,
            'local_client_id' => $record->getAttribute('local_client_id'),
            'campaign_no' => $this->paymentEventCampaignNo($campaignNo),
            'old_status' => $oldStatus,
            'new_status' => 'unpaid',
            'note' => $note ?: 'Marked unpaid',
            'admin_id' => $admin->id,
            'created_at' => now(),
        ]);
    }

    private function lockClient(LocalClient $client): void
    {
        LocalClient::query()->whereKey($client->id)->lockForUpdate()->first();
    }

    /**
     * @return list<array{
     *   model: Model,
     *   created_at: ?Carbon,
     *   billable_type: string,
     *   billable_id: int,
     *   deleted_campaign_billing_id: ?int,
     *   campaign_no: ?string,
     *   currency: string,
     *   total_cents: int,
     *   collected_cents: int,
     *   due_cents: int,
     *   status: string
     * }>
     */
    private function openItemsForCurrency(LocalClient $client, string $currency): array
    {
        return array_values(array_filter(
            $this->collectOpenItems($client),
            fn (array $item) => $item['currency'] === $currency && $item['due_cents'] > 0,
        ));
    }

    /**
     * @return list<array{
     *   model: Model,
     *   created_at: ?Carbon,
     *   billable_type: string,
     *   billable_id: int,
     *   deleted_campaign_billing_id: ?int,
     *   campaign_no: ?string,
     *   currency: string,
     *   total_cents: int,
     *   collected_cents: int,
     *   due_cents: int,
     *   status: string
     * }>
     */
    private function collectOpenItems(LocalClient $client): array
    {
        $items = [];

        foreach ($this->reportService->unpaidBillableCampaignsForClient($client) as $campaign) {
            $item = $this->openItemFromBill(
                $campaign,
                $campaign::class,
                (int) $campaign->getKey(),
                null,
                $campaign->campaign_no ?? null,
                $campaign->created_at,
                $campaign->billing_total,
                $campaign->billing_amount_paid,
                $campaign->billing_currency,
                (string) ($campaign->billing_payment_status ?? 'unpaid'),
            );

            if ($item !== null) {
                $items[] = $item;
            }
        }

        foreach ($this->unpaidDeletedCampaignsForClient($client) as $record) {
            if ($this->liveBillableExists((string) $record->billable_type, (int) $record->billable_id)) {
                continue;
            }

            $item = $this->openItemFromBill(
                $record,
                (string) $record->billable_type,
                (int) $record->billable_id,
                (int) $record->id,
                $record->campaign_no,
                $record->campaign_created_at,
                $record->billing_total,
                $record->billing_amount_paid,
                $record->billing_currency,
                (string) ($record->billing_payment_status ?? 'unpaid'),
            );

            if ($item !== null) {
                $items[] = $item;
            }
        }

        usort($items, function (array $left, array $right): int {
            $leftTime = $left['created_at'] instanceof Carbon ? $left['created_at']->getTimestamp() : PHP_INT_MAX;
            $rightTime = $right['created_at'] instanceof Carbon ? $right['created_at']->getTimestamp() : PHP_INT_MAX;

            if ($leftTime !== $rightTime) {
                return $leftTime <=> $rightTime;
            }

            $typeOrder = strcmp($left['billable_type'], $right['billable_type']);
            if ($typeOrder !== 0) {
                return $typeOrder;
            }

            return $left['billable_id'] <=> $right['billable_id'];
        });

        return $items;
    }

    /**
     * @return array{
     *   model: Model,
     *   created_at: ?Carbon,
     *   billable_type: string,
     *   billable_id: int,
     *   deleted_campaign_billing_id: ?int,
     *   campaign_no: ?string,
     *   currency: string,
     *   total_cents: int,
     *   collected_cents: int,
     *   due_cents: int,
     *   status: string
     * }|null
     */
    private function openItemFromBill(
        Model $model,
        string $billableType,
        int $billableId,
        ?int $deletedId,
        ?string $campaignNo,
        mixed $createdAt,
        mixed $total,
        mixed $amountPaid,
        mixed $currency,
        string $status,
    ): ?array {
        $totalCents = self::toCents($total);
        if ($totalCents <= 0 || $status === 'paid') {
            return null;
        }

        $collectedCents = 0;
        if ($amountPaid !== null && $amountPaid !== '') {
            $collectedCents = min($totalCents, max(0, self::toCents($amountPaid)));
        }

        $dueCents = $totalCents - $collectedCents;
        if ($dueCents <= 0) {
            return null;
        }

        $currencyCode = strtoupper(trim((string) $currency));
        if ($currencyCode === '') {
            return null;
        }

        return [
            'model' => $model,
            'created_at' => $createdAt instanceof Carbon ? $createdAt : ($createdAt ? Carbon::parse($createdAt) : null),
            'billable_type' => $billableType,
            'billable_id' => $billableId,
            'deleted_campaign_billing_id' => $deletedId,
            'campaign_no' => $campaignNo,
            'currency' => $currencyCode,
            'total_cents' => $totalCents,
            'collected_cents' => $collectedCents,
            'due_cents' => $dueCents,
            'status' => $status,
        ];
    }

    private function liveBillableExists(string $class, int $id): bool
    {
        if ($id <= 0 || ! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return false;
        }

        $model = new $class;

        if (! Schema::hasTable($model->getTable())) {
            return false;
        }

        return $class::query()->whereKey($id)->exists();
    }

    private function applyCollectedState(
        Model $record,
        int $collectedCents,
        int $totalCents,
        ?Carbon $paidAt,
        ?int $adminId,
        ?string $note,
    ): string {
        $isLive = ! $record instanceof LocalClientDeletedCampaignBilling;

        if ($collectedCents <= 0) {
            $status = 'unpaid';
            $updates = [
                'billing_payment_status' => 'unpaid',
                'billing_amount_paid' => null,
                'billing_paid_at' => null,
            ];
        } elseif ($collectedCents >= $totalCents) {
            $status = 'paid';
            $updates = [
                'billing_payment_status' => 'paid',
                'billing_amount_paid' => null,
                'billing_paid_at' => $paidAt ?? now(),
            ];
        } else {
            $status = 'semi_paid';
            $updates = [
                'billing_payment_status' => 'semi_paid',
                'billing_amount_paid' => self::fromCents($collectedCents),
                'billing_paid_at' => null,
            ];
        }

        if ($isLive) {
            $updates['billing_payment_note'] = $note;
            $updates['billing_paid_by_admin_id'] = $status === 'paid' ? $adminId : null;
        }

        $record->forceFill($updates)->save();

        return $status;
    }

    private function resolveAllocationTarget(LocalClientInstallmentAllocation $allocation, int $clientId): Model
    {
        $class = (string) $allocation->billable_type;

        if (class_exists($class) && is_subclass_of($class, Model::class)) {
            $model = new $class;

            if (Schema::hasTable($model->getTable())) {
                $live = $class::query()->find($allocation->billable_id);

                if ($live && (int) $live->getAttribute('local_client_id') === $clientId) {
                    return $live;
                }
            }
        }

        $archived = LocalClientDeletedCampaignBilling::query()
            ->where('local_client_id', $clientId)
            ->where('billable_type', $allocation->billable_type)
            ->where('billable_id', $allocation->billable_id)
            ->first();

        if ($archived) {
            return $archived;
        }

        throw ValidationException::withMessages([
            'installment' => 'This installment cannot be voided because a campaign bill is missing.',
        ]);
    }

    private function paymentEventCampaignNo(?string $campaignNo): ?string
    {
        if ($campaignNo === null || $campaignNo === '') {
            return null;
        }

        return mb_substr($campaignNo, 0, 64);
    }

    private function writePaymentEvent(
        string $billableType,
        int $billableId,
        int $clientId,
        ?string $campaignNo,
        ?string $oldStatus,
        string $newStatus,
        ?string $note,
        int $adminId,
        Carbon $createdAt,
    ): void {
        LocalClientPaymentEvent::query()->create([
            'billable_type' => $billableType,
            'billable_id' => $billableId,
            'local_client_id' => $clientId,
            'campaign_no' => $this->paymentEventCampaignNo($campaignNo),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'note' => $note,
            'admin_id' => $adminId,
            'created_at' => $createdAt,
        ]);
    }
}
