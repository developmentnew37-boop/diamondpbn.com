<?php

namespace App\Services;

use App\Models\Admin\LocalClient;
use App\Models\Admin\LocalClientBillingPeriod;
use App\Support\CurrencyFormatter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LocalClientBillingPeriodService
{
    public function __construct(
        private readonly LocalClientBillingReportService $reportService,
    ) {}

    /**
     * @return Collection<int, array{
     *   id: ?int,
     *   can_delete: bool,
     *   period_start: Carbon,
     *   period_end: ?Carbon,
     *   campaign_count: int,
     *   total_amount: float,
     *   formatted_total: string,
     *   status: 'paid'|'unpaid',
     *   paid_at: ?Carbon,
     *   is_open: bool
     * }>
     */
    public function periodsForClient(LocalClient $client, ?Collection $campaigns = null): Collection
    {
        $campaigns ??= $this->reportService->campaignsForClient($client);
        $currency = $client->default_currency;

        $periods = collect();

        $unpaid = $campaigns->filter(
            fn (array $row) => ($row['billing_payment_status'] ?? '') !== 'paid',
        );

        if ($unpaid->isNotEmpty()) {
            $periods->push($this->buildOpenPeriod($unpaid, $currency));
        }

        $frozenPaidAtKeys = collect();

        if (Schema::hasTable('local_client_billing_periods')) {
            $frozenPaidAtKeys = LocalClientBillingPeriod::query()
                ->where('local_client_id', $client->id)
                ->pluck('paid_at')
                ->map(fn ($paidAt) => $this->normalizePaidAtKey($paidAt));

            foreach ($this->frozenPeriodsForClient($client) as $frozenPeriod) {
                $periods->push($frozenPeriod);
            }
        }

        $paid = $campaigns->filter(
            fn (array $row) => ($row['billing_payment_status'] ?? '') === 'paid',
        );

        foreach ($paid->groupBy(fn (array $row) => $this->normalizePaidAtKey($row['billing_paid_at'] ?? null)) as $paidAtKey => $group) {
            if ($paidAtKey !== '__unknown__' && $frozenPaidAtKeys->contains($paidAtKey)) {
                continue;
            }

            $periods->push($this->buildPaidPeriod($client, $group, $currency, $paidAtKey));
        }

        return $periods
            ->sortBy(function (array $period) {
                if ($period['is_open']) {
                    return [0, 0];
                }

                return [1, -($period['paid_at']?->timestamp ?? 0)];
            })
            ->values();
    }

    /**
     * @return Collection<int, array{
     *   period_start: Carbon,
     *   period_end: Carbon,
     *   campaign_count: int,
     *   total_amount: float,
     *   formatted_total: string,
     *   status: 'paid',
     *   paid_at: Carbon,
     *   is_open: false
     * }>
     */
    private function frozenPeriodsForClient(LocalClient $client): Collection
    {
        if (! Schema::hasTable('local_client_billing_periods')) {
            return collect();
        }

        return LocalClientBillingPeriod::query()
            ->where('local_client_id', $client->id)
            ->orderByDesc('paid_at')
            ->get()
            ->map(function (LocalClientBillingPeriod $period) {
                return [
                    'id' => $period->id,
                    'can_delete' => true,
                    'period_start' => $period->period_start->copy()->startOfDay(),
                    'period_end' => $period->period_end->copy()->startOfDay(),
                    'campaign_count' => (int) $period->campaign_count,
                    'total_amount' => (float) $period->total_amount,
                    'formatted_total' => CurrencyFormatter::format((float) $period->total_amount, $period->currency),
                    'status' => 'paid',
                    'paid_at' => $period->paid_at,
                    'is_open' => false,
                ];
            });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function buildPaidPeriod(LocalClient $client, Collection $rows, string $currency, string $paidAtKey): array
    {
        $total = $rows->sum(fn (array $row) => (float) ($row['billing_total'] ?? 0));
        $paidAt = $paidAtKey !== '__unknown__' ? Carbon::parse($paidAtKey) : null;
        $periodId = $paidAt ? $this->resolveFrozenPeriodId($client->id, $paidAt) : null;

        return [
            'id' => $periodId,
            'can_delete' => true,
            'period_start' => $this->earliestCreatedAt($rows),
            'period_end' => $this->latestCreatedAt($rows),
            'campaign_count' => $rows->count(),
            'total_amount' => $total,
            'formatted_total' => CurrencyFormatter::format($total, $currency),
            'status' => 'paid',
            'paid_at' => $paidAt,
            'is_open' => false,
        ];
    }

    private function resolveFrozenPeriodId(int $clientId, Carbon $paidAt): ?int
    {
        if (! Schema::hasTable('local_client_billing_periods')) {
            return null;
        }

        return LocalClientBillingPeriod::query()
            ->where('local_client_id', $clientId)
            ->whereBetween('paid_at', [
                $paidAt->copy()->subSecond(),
                $paidAt->copy()->addSecond(),
            ])
            ->value('id');
    }

    private function normalizePaidAtKey(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '__unknown__';
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{
     *   period_start: Carbon,
     *   period_end: ?Carbon,
     *   campaign_count: int,
     *   total_amount: float,
     *   formatted_total: string,
     *   status: 'unpaid',
     *   paid_at: null,
     *   is_open: true
     * }
     */
    private function buildOpenPeriod(Collection $rows, string $currency): array
    {
        $total = $rows->sum(fn (array $row) => (float) ($row['billing_total'] ?? 0));

        return [
            'id' => null,
            'can_delete' => false,
            'period_start' => $this->earliestCreatedAt($rows),
            'period_end' => null,
            'campaign_count' => $rows->count(),
            'total_amount' => $total,
            'formatted_total' => CurrencyFormatter::format($total, $currency),
            'status' => 'unpaid',
            'paid_at' => null,
            'is_open' => true,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function earliestCreatedAt(Collection $rows): Carbon
    {
        return $rows
            ->map(fn (array $row) => Carbon::parse($row['created_at']))
            ->sort()
            ->first();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function latestCreatedAt(Collection $rows): Carbon
    {
        return $rows
            ->map(fn (array $row) => Carbon::parse($row['created_at']))
            ->sortDesc()
            ->first();
    }
}
