<?php

namespace App\Services;

use App\Models\Admin\Campaign;
use App\Models\Admin\HiddenLinksCampaign;
use App\Models\Admin\LocalClient;
use App\Models\Admin\ScheduleCampaign;
use App\Models\Admin\ScheduleSidebarCampaign;
use App\Models\Admin\SidebarCampaign;
use App\Support\BillableCampaignRegistry;
use App\Support\CurrencyFormatter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class LocalClientBillingReportService
{
    /** @var list<string> */
    private const BILLING_REPORT_COLUMNS = [
        'id',
        'campaign_no',
        'created_at',
        'billing_total',
        'billing_amount_paid',
        'billing_currency',
        'billing_payment_status',
        'billing_paid_at',
        'report_token',
    ];

    /** @var list<string> */
    private const PAYMENT_OPERATION_COLUMNS = [
        'id',
        'campaign_no',
        'admin_id',
        'created_at',
        'billing_total',
        'billing_amount_paid',
        'billing_currency',
        'billing_snapshot',
        'billing_payment_status',
        'billing_paid_at',
        'billing_paid_by_admin_id',
        'billing_payment_note',
        'local_client_id',
        'report_token',
    ];

    /** @var array<string, array<int, string>> */
    public const TYPE_FILTER_MAP = [
        'post' => ['campaign', 'schedule_campaign'],
        'sidebar' => ['sidebar_campaign', 'schedule_sidebar_campaign'],
        'hidden' => ['hidden_links_campaign'],
        'sticky' => ['sticky_campaign', 'schedule_sticky_campaign'],
    ];

    /**
     * @return array<string, string>
     */
    public function typeFilterOptions(): array
    {
        return [
            'post' => 'Post',
            'sidebar' => 'Sidebar',
            'hidden' => 'Hidden Links',
            'sticky' => 'Sticky',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function monthFilterOptions(): array
    {
        return [
            '01' => 'January',
            '02' => 'February',
            '03' => 'March',
            '04' => 'April',
            '05' => 'May',
            '06' => 'June',
            '07' => 'July',
            '08' => 'August',
            '09' => 'September',
            '10' => 'October',
            '11' => 'November',
            '12' => 'December',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function statusFilterOptions(): array
    {
        return [
            'paid' => 'Paid',
            'unpaid' => 'Unpaid',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function statusQuickFilterOptions(): array
    {
        return [
            'all' => 'All',
            'paid' => 'Paid',
            'unpaid' => 'Unpaid',
        ];
    }

    /**
     * @return Collection<int, array{
     *   id: int,
     *   type: string,
     *   type_label: string,
     *   campaign_no: string,
     *   created_at: ?string,
     *   billing_total: ?string,
     *   billing_currency: ?string,
     *   billing_payment_status: ?string,
     *   billing_paid_at: ?string,
     *   report_url: ?string
     * }>
     */
    public function campaignsForClient(LocalClient $client): Collection
    {
        $rows = collect();

        $this->appendCampaignRows($rows, Campaign::class, 'campaign', $client, fn ($q) => $q->where('is_sticky_campaign', false));
        $this->appendCampaignRows($rows, Campaign::class, 'sticky_campaign', $client, fn ($q) => $q->where('is_sticky_campaign', true));
        $this->appendCampaignRows($rows, SidebarCampaign::class, 'sidebar_campaign', $client);
        $this->appendCampaignRows($rows, HiddenLinksCampaign::class, 'hidden_links_campaign', $client);
        $this->appendCampaignRows($rows, ScheduleCampaign::class, 'schedule_campaign', $client, fn ($q) => $q->where('is_sticky_campaign', false));
        $this->appendCampaignRows($rows, ScheduleCampaign::class, 'schedule_sticky_campaign', $client, fn ($q) => $q->where('is_sticky_campaign', true));
        $this->appendCampaignRows($rows, ScheduleSidebarCampaign::class, 'schedule_sidebar_campaign', $client);

        return $rows->sortByDesc('created_at')->values();
    }

    /**
     * @return array{
     *   year: ?string,
     *   month_num: ?string,
     *   month: ?string,
     *   from_date: ?string,
     *   to_date: ?string,
     *   status: ?string,
     *   status_selection: string,
     *   campaign_type: ?string,
     *   is_default_month: bool,
     *   is_active: bool,
     *   active_count: int,
     *   label: ?string
     * }
     */
    public function resolveFilters(Request $request): array
    {
        $year = trim($request->string('filter_year')->toString());
        $monthNum = trim($request->string('filter_month')->toString());
        $legacyMonth = trim($request->string('month')->toString());
        $fromDate = trim($request->string('from_date')->toString());
        $toDate = trim($request->string('to_date')->toString());
        $campaignType = trim($request->string('campaign_type')->toString());

        if ($legacyMonth !== '' && preg_match('/^(\d{4})-(\d{2})$/', $legacyMonth, $matches)) {
            if ($year === '') {
                $year = $matches[1];
            }

            if ($monthNum === '') {
                $monthNum = $matches[2];
            }
        }

        if ($year !== '' && ! preg_match('/^\d{4}$/', $year)) {
            $year = '';
        }

        if ($monthNum !== '' && (! preg_match('/^\d{1,2}$/', $monthNum) || (int) $monthNum < 1 || (int) $monthNum > 12)) {
            $monthNum = '';
        }

        if ($monthNum !== '') {
            $monthNum = str_pad((string) (int) $monthNum, 2, '0', STR_PAD_LEFT);
        }

        if ($fromDate !== '' && ! $this->isValidDate($fromDate)) {
            $fromDate = '';
        }

        if ($toDate !== '' && ! $this->isValidDate($toDate)) {
            $toDate = '';
        }

        if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        // Default landing (no status param): unpaid. Explicit all/empty = paid+unpaid.
        if (! $request->has('status')) {
            $statusSelection = 'unpaid';
            $status = 'unpaid';
        } else {
            $rawStatus = trim($request->string('status')->toString());
            if ($rawStatus === '' || $rawStatus === 'all') {
                $statusSelection = 'all';
                $status = '';
            } elseif (array_key_exists($rawStatus, $this->statusFilterOptions())) {
                $statusSelection = $rawStatus;
                $status = $rawStatus;
            } else {
                $statusSelection = 'unpaid';
                $status = 'unpaid';
            }
        }

        if (! array_key_exists($campaignType, self::TYPE_FILTER_MAP)) {
            $campaignType = '';
        }

        $month = ($year !== '' && $monthNum !== '') ? "{$year}-{$monthNum}" : '';
        $hasDateRange = $fromDate !== '' || $toDate !== '';
        $isDefaultMonth = false;

        // Always month-scoped unless an explicit date range is provided.
        if ($month === '' && ! $hasDateRange) {
            $year = (string) now()->year;
            $monthNum = now()->format('m');
            $month = "{$year}-{$monthNum}";
            $isDefaultMonth = true;
        }

        // Default unpaid + default current month do not count as "active" filter badges.
        $statusCountsAsActive = in_array($statusSelection, ['paid', 'all'], true);
        $monthCountsAsActive = $month !== '' && ! $isDefaultMonth;

        $activeCount = collect([
            $monthCountsAsActive,
            $hasDateRange,
            $statusCountsAsActive,
            $campaignType !== '',
        ])->filter()->count();

        $isActive = $activeCount > 0;

        return [
            'year' => $year !== '' ? $year : null,
            'month_num' => $monthNum !== '' ? $monthNum : null,
            'month' => $month !== '' ? $month : null,
            'from_date' => $fromDate !== '' ? $fromDate : null,
            'to_date' => $toDate !== '' ? $toDate : null,
            'status' => $status !== '' ? $status : null,
            'status_selection' => $statusSelection,
            'campaign_type' => $campaignType !== '' ? $campaignType : null,
            'is_default_month' => $isDefaultMonth,
            'is_active' => $isActive,
            'active_count' => $activeCount,
            'label' => $this->buildFilterLabel($month, $fromDate, $toDate, $statusSelection, $campaignType),
        ];
    }

    /**
     * Filters for summary cards: same as table filters but ignoring payment status.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function filtersWithoutStatus(array $filters): array
    {
        $copy = $filters;
        $copy['status'] = null;

        return $copy;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $campaigns
     * @return array{all: int, paid: int, unpaid: int}
     */
    public function statusCounts(Collection $campaigns): array
    {
        $paid = 0;
        $unpaid = 0;

        foreach ($campaigns as $row) {
            if (($row['billing_payment_status'] ?? '') === 'paid') {
                $paid++;
            } else {
                $unpaid++;
            }
        }

        return [
            'all' => $paid + $unpaid,
            'paid' => $paid,
            'unpaid' => $unpaid,
        ];
    }

    /**
     * @param  array{
     *   month: ?string,
     *   from_date: ?string,
     *   to_date: ?string,
     *   status: ?string,
     *   campaign_type: ?string
     * }  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function filterCampaigns(Collection $campaigns, array $filters): Collection
    {
        $filtered = $campaigns;

        if (! empty($filters['month'])) {
            $filtered = $this->filterByMonth($filtered, $filters['month']);
        } elseif (! empty($filters['from_date']) || ! empty($filters['to_date'])) {
            $filtered = $this->filterByDateRange($filtered, $filters['from_date'], $filters['to_date']);
        }

        if (! empty($filters['status'])) {
            $filtered = $this->filterByStatus($filtered, $filters['status']);
        }

        if (! empty($filters['campaign_type'])) {
            $filtered = $this->filterByCampaignType($filtered, $filters['campaign_type']);
        }

        return $filtered->values();
    }

    /**
     * @return Collection<int, string>
     */
    public function availableYears(Collection $campaigns): Collection
    {
        $campaignYears = $campaigns
            ->pluck('created_at')
            ->filter()
            ->map(fn (string $createdAt) => (int) Carbon::parse($createdAt)->format('Y'));

        if ($campaignYears->isEmpty()) {
            return collect([(string) now()->year]);
        }

        $startYear = $campaignYears->min();
        $endYear = max($campaignYears->max(), (int) now()->year);

        $years = collect();
        for ($year = $endYear; $year >= $startYear; $year--) {
            $years->push((string) $year);
        }

        return $years->values();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function exportQueryParams(array $filters, string $format): array
    {
        $statusParam = $filters['status_selection'] ?? $filters['status'] ?? null;
        if ($statusParam === 'unpaid' && ($filters['status'] ?? null) === 'unpaid') {
            // Keep unpaid explicit in exports so downloads match the table.
            $statusParam = 'unpaid';
        }

        $params = array_filter([
            'filter_year' => $filters['year'] ?? null,
            'filter_month' => $filters['month_num'] ?? null,
            'from_date' => $filters['from_date'] ?? null,
            'to_date' => $filters['to_date'] ?? null,
            'status' => $statusParam,
            'campaign_type' => $filters['campaign_type'] ?? null,
        ]);

        $params['format'] = $format;

        return $params;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $campaigns
     * @return Collection<int, array<string, mixed>>
     */
    private function filterByMonth(Collection $campaigns, string $month): Collection
    {
        [$start, $end] = $this->monthPeriod($month);

        return $campaigns
            ->filter(fn (array $row) => $this->campaignDateInRange($row, $start, $end))
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $campaigns
     * @return Collection<int, array<string, mixed>>
     */
    private function filterByDateRange(Collection $campaigns, ?string $fromDate, ?string $toDate): Collection
    {
        $start = $fromDate ? Carbon::parse($fromDate)->startOfDay() : null;
        $end = $toDate ? Carbon::parse($toDate)->endOfDay() : null;

        return $campaigns
            ->filter(function (array $row) use ($start, $end) {
                if (empty($row['created_at'])) {
                    return false;
                }

                $createdAt = Carbon::parse($row['created_at']);

                if ($start && $createdAt->lt($start)) {
                    return false;
                }

                if ($end && $createdAt->gt($end)) {
                    return false;
                }

                return true;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function campaignDateInRange(array $row, Carbon $start, Carbon $end): bool
    {
        if (empty($row['created_at'])) {
            return false;
        }

        $createdAt = Carbon::parse($row['created_at']);

        return $createdAt->betweenIncluded($start, $end);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $campaigns
     * @return Collection<int, array<string, mixed>>
     */
    private function filterByStatus(Collection $campaigns, string $status): Collection
    {
        return $campaigns
            ->filter(function (array $row) use ($status) {
                $isPaid = ($row['billing_payment_status'] ?? '') === 'paid';

                return $status === 'paid' ? $isPaid : ! $isPaid;
            })
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $campaigns
     * @return Collection<int, array<string, mixed>>
     */
    private function filterByCampaignType(Collection $campaigns, string $campaignType): Collection
    {
        $allowedTypes = self::TYPE_FILTER_MAP[$campaignType] ?? [];

        return $campaigns
            ->filter(fn (array $row) => in_array($row['type'] ?? '', $allowedTypes, true))
            ->values();
    }

    private function buildFilterLabel(string $month, string $fromDate, string $toDate, string $statusSelection, string $campaignType): ?string
    {
        $parts = [];

        if ($month !== '') {
            $parts[] = $this->monthLabel($month);
        } elseif ($fromDate !== '' && $toDate !== '') {
            $parts[] = Carbon::parse($fromDate)->format('M j, Y').' – '.Carbon::parse($toDate)->format('M j, Y');
        } elseif ($fromDate !== '') {
            $parts[] = 'From '.Carbon::parse($fromDate)->format('M j, Y');
        } elseif ($toDate !== '') {
            $parts[] = 'Until '.Carbon::parse($toDate)->format('M j, Y');
        }

        if ($statusSelection === 'paid') {
            $parts[] = 'Paid';
        } elseif ($statusSelection === 'all') {
            $parts[] = 'All statuses';
        }

        if ($campaignType !== '') {
            $parts[] = $this->typeFilterOptions()[$campaignType];
        }

        return $parts !== [] ? implode(' · ', $parts) : null;
    }

    private function isValidDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    /**
     * @return Collection<int, Model>
     */
    public function unpaidBillableCampaignsForClient(LocalClient $client): Collection
    {
        $models = collect();

        $this->appendUnpaidModels($models, Campaign::class, $client, fn ($q) => $q->where('is_sticky_campaign', false));
        $this->appendUnpaidModels($models, Campaign::class, $client, fn ($q) => $q->where('is_sticky_campaign', true));
        $this->appendUnpaidModels($models, SidebarCampaign::class, $client);
        $this->appendUnpaidModels($models, HiddenLinksCampaign::class, $client);
        $this->appendUnpaidModels($models, ScheduleCampaign::class, $client, fn ($q) => $q->where('is_sticky_campaign', false));
        $this->appendUnpaidModels($models, ScheduleCampaign::class, $client, fn ($q) => $q->where('is_sticky_campaign', true));
        $this->appendUnpaidModels($models, ScheduleSidebarCampaign::class, $client);

        return $models->values();
    }

    /**
     * @param  Collection<int, Model>  $models
     * @param  callable(\Illuminate\Database\Eloquent\Builder): void|null  $scope
     */
    private function appendUnpaidModels(
        Collection $models,
        string $modelClass,
        LocalClient $client,
        ?callable $scope = null,
    ): void {
        $model = new $modelClass;

        if (! Schema::hasTable($model->getTable())) {
            return;
        }

        $table = $model->getTable();
        $columns = array_values(array_filter(
            self::PAYMENT_OPERATION_COLUMNS,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        $query = $modelClass::query()
            ->select($columns)
            ->where('local_client_id', $client->id)
            ->whereNotNull('billing_total')
            ->whereNotNull('billing_snapshot')
            ->where(function ($q) {
                $q->whereNull('billing_payment_status')
                    ->orWhere('billing_payment_status', '!=', 'paid');
            });

        if ($scope) {
            $scope($query);
        }

        foreach ($query->get() as $campaign) {
            $models->push($campaign);
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  callable(\Illuminate\Database\Eloquent\Builder): void|null  $scope
     */
    private function appendCampaignRows(
        Collection $rows,
        string $modelClass,
        string $typeKey,
        LocalClient $client,
        ?callable $scope = null,
    ): void {
        $model = new $modelClass;

        if (! Schema::hasTable($model->getTable())) {
            return;
        }

        $table = $model->getTable();
        $columns = array_values(array_filter(
            self::BILLING_REPORT_COLUMNS,
            fn (string $column) => Schema::hasColumn($table, $column)
        ));

        $query = $modelClass::query()
            ->select($columns)
            ->where('local_client_id', $client->id)
            ->whereNotNull('billing_total');

        if ($scope) {
            $scope($query);
        }

        $label = BillableCampaignRegistry::TYPES[$typeKey]['label'];

        foreach ($query->get() as $campaign) {
            $amountPaid = Schema::hasColumn($table, 'billing_amount_paid')
                ? $campaign->billing_amount_paid
                : null;
            $balanceDue = LocalClientBillingService::balanceDue(
                $campaign->billing_total,
                $amountPaid,
                $campaign->billing_payment_status,
            );

            $rows->push([
                'id' => $campaign->id,
                'type' => $typeKey,
                'type_label' => $label,
                'campaign_no' => $campaign->campaign_no,
                'created_at' => $campaign->created_at?->toDateTimeString(),
                'billing_total' => $campaign->billing_total,
                'billing_amount_paid' => $amountPaid,
                'billing_balance_due' => number_format($balanceDue, 2, '.', ''),
                'billing_has_credit' => LocalClientBillingService::hasBalanceCredit(
                    $amountPaid,
                    $campaign->billing_payment_status,
                ),
                'billing_currency' => $campaign->billing_currency,
                'billing_payment_status' => $campaign->billing_payment_status,
                'billing_paid_at' => $campaign->billing_paid_at?->toDateTimeString(),
                'report_url' => $this->resolveReportUrl($typeKey, $campaign),
            ]);
        }
    }

    private function resolveReportUrl(string $typeKey, object $campaign): ?string
    {
        if (empty($campaign->report_token) || empty($campaign->campaign_no)) {
            return null;
        }

        $route = match ($typeKey) {
            'campaign' => 'admin.campaign.report',
            'sticky_campaign' => 'admin.campaign.report',
            'sidebar_campaign' => 'admin.sidebar.campaign.report',
            'hidden_links_campaign' => 'admin.hidden.link.campaign.report',
            'schedule_campaign' => 'admin.schedule.campaign.report',
            'schedule_sticky_campaign' => 'admin.schedule.campaign.report',
            'schedule_sidebar_campaign' => 'admin.schedule.sidebar.campaign.report',
            default => null,
        };

        if (! $route) {
            return null;
        }

        return route($route, [
            'campaign_no' => $campaign->campaign_no,
            'token' => $campaign->report_token,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $campaigns
     * @return array{total_billed: float, total_paid: float, total_unpaid: float, paid_count: int, unpaid_count: int}
     */
    public function summarize(Collection $campaigns): array
    {
        $totalBilled = 0.0;
        $totalPaid = 0.0;
        $totalUnpaid = 0.0;
        $paidCount = 0;
        $unpaidCount = 0;

        foreach ($campaigns as $row) {
            $amount = (float) ($row['billing_total'] ?? 0);
            $totalBilled += $amount;

            if (($row['billing_payment_status'] ?? '') === 'paid') {
                $totalPaid += $amount;
                $paidCount++;
            } else {
                $due = isset($row['billing_balance_due'])
                    ? (float) $row['billing_balance_due']
                    : LocalClientBillingService::balanceDue(
                        $row['billing_total'] ?? 0,
                        $row['billing_amount_paid'] ?? null,
                        $row['billing_payment_status'] ?? null,
                    );
                $totalUnpaid += $due;
                $unpaidCount++;
            }
        }

        return [
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'total_unpaid' => $totalUnpaid,
            'paid_count' => $paidCount,
            'unpaid_count' => $unpaidCount,
        ];
    }

    public function formatSummaryForCurrency(Collection $campaigns, string $currency): array
    {
        $summary = $this->summarize($campaigns);

        return [
            'total_billed' => CurrencyFormatter::format($summary['total_billed'], $currency),
            'total_paid' => CurrencyFormatter::format($summary['total_paid'], $currency),
            'total_unpaid' => CurrencyFormatter::format($summary['total_unpaid'], $currency),
            'paid_count' => $summary['paid_count'],
            'unpaid_count' => $summary['unpaid_count'],
        ];
    }

    /**
     * All-time unpaid total and month-by-month breakdown (ignores report filters).
     *
     * @param  Collection<int, array<string, mixed>>  $campaigns
     * @return array{
     *   total_unpaid: float,
     *   unpaid_count: int,
     *   total_unpaid_formatted: string,
     *   has_unpaid: bool,
     *   breakdown: list<array{
     *     month_key: string,
     *     year: string,
     *     month_num: string,
     *     label: string,
     *     amount: float,
     *     formatted_amount: string,
     *     campaign_count: int
     *   }>
     * }
     */
    public function formatOverallUnpaidSummary(Collection $campaigns, string $currency): array
    {
        $unpaid = $this->filterCampaigns($campaigns, [
            'month' => null,
            'from_date' => null,
            'to_date' => null,
            'status' => 'unpaid',
            'campaign_type' => null,
        ]);

        $summary = $this->summarize($unpaid);
        $breakdown = [];

        foreach ($unpaid->groupBy(fn (array $row) => $this->monthKeyFromRow($row)) as $monthKey => $rows) {
            if ($monthKey === '__unknown__') {
                continue;
            }

            [$year, $monthNum] = explode('-', $monthKey);
            $amount = $rows->sum(fn (array $row) => $this->rowBalanceDue($row));

            $breakdown[] = [
                'month_key' => $monthKey,
                'year' => $year,
                'month_num' => $monthNum,
                'label' => $this->monthLabel($monthKey),
                'amount' => $amount,
                'formatted_amount' => CurrencyFormatter::format($amount, $currency),
                'campaign_count' => $rows->count(),
            ];
        }

        usort($breakdown, fn (array $a, array $b) => strcmp($b['month_key'], $a['month_key']));

        return [
            'total_unpaid' => $summary['total_unpaid'],
            'unpaid_count' => $summary['unpaid_count'],
            'total_unpaid_formatted' => CurrencyFormatter::format($summary['total_unpaid'], $currency),
            'has_unpaid' => $summary['unpaid_count'] > 0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function rowBalanceDue(array $row): float
    {
        if (isset($row['billing_balance_due'])) {
            return (float) $row['billing_balance_due'];
        }

        return LocalClientBillingService::balanceDue(
            $row['billing_total'] ?? 0,
            $row['billing_amount_paid'] ?? null,
            $row['billing_payment_status'] ?? null,
        );
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function monthPeriod(string $monthKey): array
    {
        $start = Carbon::createFromFormat('Y-m-d', $monthKey.'-01')->startOfDay();
        $end = $start->copy()->endOfMonth()->endOfDay();

        return [$start, $end];
    }

    private function monthLabel(string $monthKey): string
    {
        return Carbon::createFromFormat('Y-m-d', $monthKey.'-01')->format('F Y');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function monthKeyFromRow(array $row): string
    {
        if (empty($row['created_at'])) {
            return '__unknown__';
        }

        return Carbon::parse($row['created_at'])->format('Y-m');
    }

    /**
     * @param  Collection<int, mixed>  $items
     */
    public function paginateCollection(
        Collection $items,
        Request $request,
        int $perPage = 15,
        string $pageName = 'page',
    ): LengthAwarePaginator {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $total = $items->count();

        return new LengthAwarePaginator(
            $items->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => $pageName,
            ],
        );
    }
}
