@extends('admin.layout.general')

@section('title', $client->name . ' — Billing Report')

@push('style')
    @include('admin.local-clients.partials.billing-report-public-styles')
@endpush

@section('main-content')
    @php
        $filteredTotal = $filteredTotal ?? $campaigns->total();
        $campaignCount = $filteredTotal;
        $statusCounts = $statusCounts ?? ['all' => 0, 'paid' => 0, 'unpaid' => 0];
        $totalCampaignCount = $statusCounts['all'];
        $statusSelection = $filters['status_selection'] ?? 'unpaid';
        $overallOutstanding = $overallOutstanding ?? [
            'total_unpaid_formatted' => '—',
            'unpaid_count' => 0,
            'has_unpaid' => false,
            'breakdown' => [],
        ];
        $rowOffset = ($campaigns->currentPage() - 1) * $campaigns->perPage();
        $paidPercent = $totalCampaignCount > 0
            ? (int) round(($summary['paid_count'] / $totalCampaignCount) * 100)
            : 0;
        $clearFiltersUrl = route('admin.local-client.billing.report', [
            'id' => $client->id,
            'token' => $client->billing_report_token,
        ]);

        $statusFilterBaseQuery = request()->except(['status', 'page']);
        $statusFilterUrls = [
            'all' => route('admin.local-client.billing.report', array_merge(
                ['id' => $client->id, 'token' => $client->billing_report_token],
                $statusFilterBaseQuery,
                ['status' => 'all']
            )),
            'paid' => route('admin.local-client.billing.report', array_merge(
                ['id' => $client->id, 'token' => $client->billing_report_token],
                $statusFilterBaseQuery,
                ['status' => 'paid']
            )),
            'unpaid' => route('admin.local-client.billing.report', array_merge(
                ['id' => $client->id, 'token' => $client->billing_report_token],
                $statusFilterBaseQuery,
                ['status' => 'unpaid']
            )),
        ];

        $typeBadgeClass = function (string $type): string {
            return match (true) {
                str_contains($type, 'sidebar') => 'type-sidebar',
                str_contains($type, 'hidden') => 'type-hidden',
                str_contains($type, 'schedule') => 'type-schedule',
                str_contains($type, 'sticky') => 'type-sticky',
                str_contains($type, 'campaign') => 'type-post',
                default => 'type-default',
            };
        };
    @endphp

    <div class="billing-public-shell">
        <header class="billing-public-header">
            <div class="billing-public-container billing-public-header-inner">
                <a class="billing-brand" href="{{ url('/') }}" aria-label="Diamond PBN">
                    <span class="billing-brand-mark">
                        <img src="{{ asset('favicon.png') }}" alt="Diamond PBN logo">
                    </span>
                    <span>
                        <div class="billing-brand-name">Diamond Pbn</div>
                        <div class="billing-brand-tagline">Client billing report</div>
                    </span>
                </a>
                <a href="https://wa.me/923001234567" target="_blank" rel="noopener noreferrer" class="billing-whatsapp-badge">
                    <svg class="billing-whatsapp-icon" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="currentColor"
                            d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                    </svg>
                    <span>+92 300 1234567</span>
                </a>
            </div>
        </header>

        <main class="billing-public-main">
            <div class="billing-public-container">
                <article class="billing-report-document">
                    <div class="billing-doc-accent"></div>

                    <header class="billing-doc-header">
                        <div class="min-w-0 flex-1">
                            <div class="billing-doc-eyebrow">
                                <span class="material-symbols-outlined !text-sm">receipt_long</span>
                                Billing statement
                            </div>
                            <h1 class="billing-client-name">{{ $client->name }}</h1>
                            <div class="billing-client-meta">
                                @if ($client->company_name)
                                    <span>{{ $client->company_name }}</span>
                                    <span class="text-slate-300">·</span>
                                @endif
                                @if ($filters['label'])
                                    <span>{{ $filters['label'] }}</span>
                                @else
                                    <span>All billed campaigns</span>
                                @endif
                            </div>
                            <div class="billing-doc-meta-grid">
                                <div class="billing-doc-meta-item">
                                    <label>Currency</label>
                                    <span>{{ $client->default_currency }}</span>
                                </div>
                                <div class="billing-doc-meta-item">
                                    <label>Campaigns</label>
                                    <span>{{ $campaignCount }}</span>
                                </div>
                                <div class="billing-doc-meta-item">
                                    <label>Report date</label>
                                    <span>{{ now()->format('M d, Y') }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="billing-doc-actions">
                            <button type="button" class="billing-btn billing-btn-secondary" id="billing-view-rates-btn"
                                aria-haspopup="dialog" aria-controls="billing-rates-modal">
                                <span class="material-symbols-outlined !text-base">price_change</span>
                                View rates
                            </button>
                            <a class="billing-btn billing-btn-primary"
                                href="{{ route('admin.local-client.billing.report.export', array_merge(['id' => $client->id, 'token' => $client->billing_report_token], $exportPdfParams)) }}">
                                <span class="material-symbols-outlined !text-base">picture_as_pdf</span>
                                PDF
                            </a>
                            <a class="billing-btn billing-btn-secondary"
                                href="{{ route('admin.local-client.billing.report.export', array_merge(['id' => $client->id, 'token' => $client->billing_report_token], $exportCsvParams)) }}">
                                <span class="material-symbols-outlined !text-base">download</span>
                                CSV
                            </a>
                        </div>
                    </header>

                    <details class="billing-filter-panel" @if ($filters['is_active']) open @endif>
                        <summary class="billing-filter-summary">
                            <span class="billing-filter-summary-left">
                                <span class="material-symbols-outlined">filter_alt</span>
                                <span class="billing-filter-summary-title">Filters</span>
                                @if ($filters['is_active'])
                                    <span class="billing-filter-badge">{{ $filters['active_count'] }} active</span>
                                @endif
                            </span>
                            <span class="material-symbols-outlined billing-filter-chevron">expand_more</span>
                        </summary>

                        <form method="GET"
                            action="{{ route('admin.local-client.billing.report', ['id' => $client->id, 'token' => $client->billing_report_token]) }}"
                            class="billing-filter-form">
                            <div class="billing-filter-section">
                                <div class="billing-filter-section-title">Date</div>
                                <div class="billing-filter-grid billing-filter-grid-date">
                                    <div class="billing-filter-group">
                                        <label for="billing-filter-year">Year</label>
                                        <select id="billing-filter-year" name="filter_year" class="billing-filter-input">
                                            <option value="">Select year</option>
                                            @foreach ($availableYears as $yearValue)
                                                <option value="{{ $yearValue }}" @selected(($filters['year'] ?? '') === $yearValue)>
                                                    {{ $yearValue }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="billing-filter-group">
                                        <label for="billing-filter-month">Month</label>
                                        <select id="billing-filter-month" name="filter_month" class="billing-filter-input"
                                            @disabled(empty($filters['year']))>
                                            <option value="">Select month</option>
                                            @foreach ($monthFilterOptions as $monthValue => $monthLabel)
                                                <option value="{{ $monthValue }}" @selected(($filters['month_num'] ?? '') === $monthValue)>
                                                    {{ $monthLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="billing-filter-divider">or</div>

                                    <div class="billing-filter-group">
                                        <label for="billing-filter-from">From date</label>
                                        <input type="date" id="billing-filter-from" name="from_date"
                                            value="{{ $filters['from_date'] ?? '' }}" class="billing-filter-input">
                                    </div>

                                    <div class="billing-filter-group">
                                        <label for="billing-filter-to">To date</label>
                                        <input type="date" id="billing-filter-to" name="to_date"
                                            value="{{ $filters['to_date'] ?? '' }}" class="billing-filter-input">
                                    </div>
                                </div>
                            </div>

                            <div class="billing-filter-section">
                                <div class="billing-filter-section-title">Status & type</div>
                                <div class="billing-filter-grid billing-filter-grid-meta">
                                    <div class="billing-filter-group">
                                        <label for="billing-filter-status">Payment status</label>
                                        <select id="billing-filter-status" name="status" class="billing-filter-input">
                                            <option value="all" @selected($statusSelection === 'all')>All statuses</option>
                                            @foreach ($statusFilterOptions as $statusValue => $statusLabel)
                                                <option value="{{ $statusValue }}" @selected($statusSelection === $statusValue)>
                                                    {{ $statusLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="billing-filter-group">
                                        <label for="billing-filter-type">Campaign type</label>
                                        <select id="billing-filter-type" name="campaign_type" class="billing-filter-input">
                                            <option value="">All types</option>
                                            @foreach ($typeFilterOptions as $typeValue => $typeLabel)
                                                <option value="{{ $typeValue }}" @selected(($filters['campaign_type'] ?? '') === $typeValue)>
                                                    {{ $typeLabel }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="billing-filter-actions">
                                        <button type="submit" class="billing-btn billing-btn-primary billing-filter-apply">
                                            <span class="material-symbols-outlined !text-base">search</span>
                                            Apply
                                        </button>
                                        @if ($filters['is_active'])
                                            <a href="{{ $clearFiltersUrl }}" class="billing-btn billing-btn-secondary billing-filter-clear">
                                                <span class="material-symbols-outlined !text-base">close</span>
                                                Clear
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if ($filters['is_active'])
                                <div class="billing-filter-active">
                                    <span class="material-symbols-outlined !text-sm">info</span>
                                    Showing {{ $campaignCount }} of {{ $totalCampaignCount }} campaign{{ $totalCampaignCount === 1 ? '' : 's' }}
                                    @if ($filters['label'])
                                        · {{ $filters['label'] }}
                                    @endif
                                </div>
                            @endif
                        </form>
                    </details>

                    <div class="billing-doc-body">
                        <div class="billing-overall-outstanding {{ $overallOutstanding['has_unpaid'] ? 'has-balance' : 'is-clear' }}">
                            <div class="billing-overall-outstanding-main">
                                <div class="billing-overall-outstanding-label-row">
                                    <span class="billing-overall-outstanding-label">Overall outstanding</span>
                                    @if (! empty($overallOutstanding['breakdown']))
                                        <button type="button"
                                            class="billing-overall-info-btn"
                                            id="billing-outstanding-breakdown-btn"
                                            aria-haspopup="dialog"
                                            aria-controls="billing-outstanding-modal"
                                            aria-label="View unpaid breakdown by month">
                                            <span class="material-symbols-outlined">info</span>
                                        </button>
                                    @endif
                                </div>
                                @if ($overallOutstanding['has_unpaid'])
                                    <div class="billing-overall-outstanding-value">{{ $overallOutstanding['total_unpaid_formatted'] }}</div>
                                    <div class="billing-overall-outstanding-sub">
                                        {{ $overallOutstanding['unpaid_count'] }} unpaid campaign{{ $overallOutstanding['unpaid_count'] === 1 ? '' : 's' }} across all months
                                    </div>
                                @else
                                    <div class="billing-overall-outstanding-value is-clear">All clear</div>
                                    <div class="billing-overall-outstanding-sub">No outstanding balance on your account</div>
                                @endif
                            </div>
                            <div class="billing-overall-outstanding-icon" aria-hidden="true">
                                <span class="material-symbols-outlined">{{ $overallOutstanding['has_unpaid'] ? 'account_balance' : 'verified' }}</span>
                            </div>
                        </div>

                        @if ($statusSelection === 'all')
                            <div class="billing-stats-grid">
                                <div class="billing-stat-card total">
                                    <div class="billing-stat-top">
                                        <div class="billing-stat-label">Total billed</div>
                                        <div class="billing-stat-icon">
                                            <span class="material-symbols-outlined">account_balance_wallet</span>
                                        </div>
                                    </div>
                                    <div class="billing-stat-value">{{ $summary['total_billed'] }}</div>
                                    <div class="billing-stat-sub">{{ $totalCampaignCount }} campaign{{ $totalCampaignCount === 1 ? '' : 's' }}</div>
                                </div>
                                <div class="billing-stat-card paid">
                                    <div class="billing-stat-top">
                                        <div class="billing-stat-label">Paid</div>
                                        <div class="billing-stat-icon">
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </div>
                                    </div>
                                    <div class="billing-stat-value">{{ $summary['total_paid'] }}</div>
                                    <div class="billing-stat-sub">{{ $summary['paid_count'] }} settled</div>
                                </div>
                                <div class="billing-stat-card unpaid">
                                    <div class="billing-stat-top">
                                        <div class="billing-stat-label">This month outstanding</div>
                                        <div class="billing-stat-icon">
                                            <span class="material-symbols-outlined">pending</span>
                                        </div>
                                    </div>
                                    <div class="billing-stat-value">{{ $summary['total_unpaid'] }}</div>
                                    <div class="billing-stat-sub">{{ $summary['unpaid_count'] }} pending</div>
                                </div>
                            </div>

                            @if ($totalCampaignCount > 0)
                                <div class="billing-progress-wrap">
                                    <div class="billing-progress-head">
                                        <span>Payment collection</span>
                                        <span>{{ $paidPercent }}% paid</span>
                                    </div>
                                    <div class="billing-progress-track">
                                        <div class="billing-progress-fill" style="width: {{ $paidPercent }}%;"></div>
                                    </div>
                                </div>
                            @endif
                        @elseif ($statusSelection === 'paid')
                            <div class="billing-stats-grid billing-stats-grid-unpaid-only">
                                <div class="billing-stat-card paid">
                                    <div class="billing-stat-top">
                                        <div class="billing-stat-label">Paid</div>
                                        <div class="billing-stat-icon">
                                            <span class="material-symbols-outlined">check_circle</span>
                                        </div>
                                    </div>
                                    <div class="billing-stat-value">{{ $summary['total_paid'] }}</div>
                                    <div class="billing-stat-sub">{{ $summary['paid_count'] }} paid campaign{{ $summary['paid_count'] === 1 ? '' : 's' }}</div>
                                </div>
                            </div>
                        @else
                            <div class="billing-stats-grid billing-stats-grid-unpaid-only">
                                <div class="billing-stat-card unpaid">
                                    <div class="billing-stat-top">
                                        <div class="billing-stat-label">This month outstanding</div>
                                        <div class="billing-stat-icon">
                                            <span class="material-symbols-outlined">pending</span>
                                        </div>
                                    </div>
                                    <div class="billing-stat-value">{{ $summary['total_unpaid'] }}</div>
                                    <div class="billing-stat-sub">
                                        @if ($filters['label'])
                                            {{ $filters['label'] }} ·
                                        @endif
                                        {{ $summary['unpaid_count'] }} unpaid campaign{{ $summary['unpaid_count'] === 1 ? '' : 's' }}
                                    </div>
                                </div>
                            </div>
                        @endif
                        <div class="billing-table-section">
                            <div class="billing-table-heading">
                                <div class="billing-table-heading-left">
                                    <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">list_alt</span>
                                    <h2>Campaign billing history</h2>
                                </div>
                                <div class="billing-table-heading-right">
                                    <div class="billing-status-quick-filters" role="group" aria-label="Payment status filter">
                                        @foreach (['all' => 'All', 'paid' => 'Paid', 'unpaid' => 'Unpaid'] as $statusKey => $statusLabel)
                                            <a href="{{ $statusFilterUrls[$statusKey] }}"
                                               class="billing-status-chip {{ $statusSelection === $statusKey ? 'is-active' : '' }}"
                                               @if ($statusSelection === $statusKey) aria-current="true" @endif>
                                                {{ $statusLabel }}
                                                <span class="billing-status-chip-count">{{ $statusCounts[$statusKey] ?? 0 }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                    <span class="billing-table-count">{{ $campaignCount }} record{{ $campaignCount === 1 ? '' : 's' }}</span>
                                </div>
                            </div>
                            <div class="billing-desktop-table overflow-x-auto">
                                <table class="billing-report-table">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Campaign</th>
                                            <th>Type</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                            <th>Report</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($campaigns as $index => $row)
                                            @php
                                                $campaignNo = (string) ($row['campaign_no'] ?? '');
                                                $isSystemId = str_starts_with(strtolower($campaignNo), 'cmp-');
                                                $createdAt = $row['created_at']
                                                    ? \Illuminate\Support\Carbon::parse($row['created_at'])
                                                    : null;
                                            @endphp
                                            <tr>
                                                <td class="billing-row-index">{{ $rowOffset + $index + 1 }}</td>
                                                <td>
                                                    <span class="billing-campaign-no {{ $isSystemId ? 'is-system-id' : '' }}">
                                                        {{ $campaignNo }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="billing-type-badge {{ $typeBadgeClass($row['type'] ?? '') }}">
                                                        {{ $row['type_label'] }}
                                                    </span>
                                                </td>
                                                <td class="billing-date">
                                                    @if ($createdAt)
                                                        <time datetime="{{ $createdAt->toIso8601String() }}">
                                                            {{ $createdAt->format('M j, Y') }}
                                                            <span class="text-slate-400">·</span>
                                                            {{ $createdAt->format('g:i A') }}
                                                        </time>
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="billing-amount">
                                                        {{ \App\Support\CurrencyFormatter::format($row['billing_total'], $row['billing_currency'] ?? $client->default_currency) }}
                                                    </span>
                                                    @if (! empty($row['billing_has_credit']))
                                                        <div class="billing-due-meta">
                                                            <span class="billing-already-paid">
                                                                Already paid {{ \App\Support\CurrencyFormatter::format($row['billing_amount_paid'], $row['billing_currency'] ?? $client->default_currency) }}
                                                            </span>
                                                            <span class="billing-due-now">
                                                                Due now {{ \App\Support\CurrencyFormatter::format($row['billing_balance_due'], $row['billing_currency'] ?? $client->default_currency) }}
                                                            </span>
                                                        </div>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (($row['billing_payment_status'] ?? '') === 'paid')
                                                        <span class="billing-status-badge paid">
                                                            <span class="billing-status-dot"></span>
                                                            Paid
                                                        </span>
                                                    @else
                                                        <span class="billing-status-badge unpaid">
                                                            <span class="billing-status-dot"></span>
                                                            Unpaid
                                                        </span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (! empty($row['report_url']))
                                                        <a href="{{ $row['report_url'] }}" target="_blank" rel="noopener noreferrer" class="billing-report-link">
                                                            <span class="material-symbols-outlined">visibility</span>
                                                            View report
                                                        </a>
                                                    @else
                                                        <span class="billing-report-link-unavailable">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7">
                                                    <div class="billing-empty-state">
                                                        <span class="material-symbols-outlined">inbox</span>
                                                        @if ($filters['is_active'])
                                                            <p>No campaigns match the selected filters.</p>
                                                            <a href="{{ $clearFiltersUrl }}" class="billing-filter-empty-link">Clear filters</a>
                                                        @else
                                                            <p>No billed campaigns yet.</p>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>

                            <div class="billing-mobile-cards">
                                @forelse ($campaigns as $index => $row)
                                    @php
                                        $campaignNo = (string) ($row['campaign_no'] ?? '');
                                        $isSystemId = str_starts_with(strtolower($campaignNo), 'cmp-');
                                        $createdAt = $row['created_at']
                                            ? \Illuminate\Support\Carbon::parse($row['created_at'])
                                            : null;
                                    @endphp
                                    <article class="billing-mobile-card">
                                        <div class="billing-mobile-card-head">
                                            <span class="billing-campaign-no {{ $isSystemId ? 'is-system-id' : '' }}">{{ $campaignNo }}</span>
                                            <span class="billing-type-badge {{ $typeBadgeClass($row['type'] ?? '') }}">
                                                {{ $row['type_label'] }}
                                            </span>
                                        </div>
                                        <div class="billing-mobile-card-grid">
                                            <div>
                                                <label>Date</label>
                                                <span>
                                                    @if ($createdAt)
                                                        {{ $createdAt->format('M j, Y g:i A') }}
                                                    @else
                                                        —
                                                    @endif
                                                </span>
                                            </div>
                                            <div>
                                                <label>Amount</label>
                                                <span class="billing-amount">
                                                    {{ \App\Support\CurrencyFormatter::format($row['billing_total'], $row['billing_currency'] ?? $client->default_currency) }}
                                                </span>
                                                @if (! empty($row['billing_has_credit']))
                                                    <div class="billing-due-meta">
                                                        <span class="billing-already-paid">
                                                            Already paid {{ \App\Support\CurrencyFormatter::format($row['billing_amount_paid'], $row['billing_currency'] ?? $client->default_currency) }}
                                                        </span>
                                                        <span class="billing-due-now">
                                                            Due now {{ \App\Support\CurrencyFormatter::format($row['billing_balance_due'], $row['billing_currency'] ?? $client->default_currency) }}
                                                        </span>
                                                    </div>
                                                @endif
                                            </div>
                                            <div>
                                                <label>Status</label>
                                                @if (($row['billing_payment_status'] ?? '') === 'paid')
                                                    <span class="billing-status-badge paid">
                                                        <span class="billing-status-dot"></span>
                                                        Paid
                                                    </span>
                                                @else
                                                    <span class="billing-status-badge unpaid">
                                                        <span class="billing-status-dot"></span>
                                                        Unpaid
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                        @if (! empty($row['report_url']))
                                            <a href="{{ $row['report_url'] }}" target="_blank" rel="noopener noreferrer" class="billing-report-link !mt-3">
                                                <span class="material-symbols-outlined">visibility</span>
                                                View report
                                            </a>
                                        @endif
                                    </article>
                                @empty
                                    <div class="billing-empty-state">
                                        <span class="material-symbols-outlined">inbox</span>
                                        @if ($filters['is_active'])
                                            <p>No campaigns match the selected filters.</p>
                                            <a href="{{ $clearFiltersUrl }}" class="billing-filter-empty-link">Clear filters</a>
                                        @else
                                            <p>No billed campaigns yet.</p>
                                        @endif
                                    </div>
                                @endforelse
                            </div>

                            @if ($campaigns->hasPages())
                                <div class="billing-pagination">
                                    {{ $campaigns->links() }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <footer class="billing-doc-footer">
                        <span class="billing-doc-footer-brand">
                            <img src="{{ asset('favicon.png') }}" alt="">
                            Diamond Pbn Automation
                        </span>
                        <span>Confidential · Generated for {{ $client->name }}</span>
                    </footer>
                </article>
            </div>
        </main>

        <footer class="billing-public-footer">
            <div class="billing-public-container">
                © {{ date('Y') }} Diamond Pbn · Client billing portal
            </div>
        </footer>
    </div>

    @php
        $currencySymbol = \App\Support\CurrencyFormatter::symbol($client->default_currency ?? 'USD');
        $rateMatrix = $rateMatrix ?? collect();
        $rateSourceLabel = $rateSourceLabel ?? 'Custom rates';
    @endphp

    <div id="billing-rates-modal" class="billing-rates-modal" hidden aria-hidden="true">
        <div class="billing-rates-modal-backdrop" data-rates-close tabindex="-1"></div>
        <div class="billing-rates-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="billing-rates-modal-title">
            <div class="billing-rates-modal-header">
                <div class="min-w-0">
                    <h2 id="billing-rates-modal-title" class="billing-rates-modal-title">Your rates</h2>
                    <p class="billing-rates-modal-subtitle">{{ $rateSourceLabel }} · {{ $client->default_currency }}</p>
                </div>
                <button type="button" class="billing-rates-modal-close" data-rates-close aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="billing-rates-modal-body">
                <div class="billing-rates-table-wrap">
                    <table class="billing-rates-table">
                        <thead>
                            <tr>
                                <th>Domain category</th>
                                <th>Post ({{ $currencySymbol }})</th>
                                <th>Sidebar ({{ $currencySymbol }})</th>
                                <th>Hidden links ({{ $currencySymbol }})</th>
                                <th>Sticky ({{ $currencySymbol }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rateMatrix as $row)
                                <tr>
                                    <td class="billing-rates-cat">{{ $row['category_name'] }}</td>
                                    @foreach (['post_price', 'sidebar_price', 'hidden_links_price', 'sticky_price'] as $field)
                                        <td>
                                            @if ($row[$field] === null || $row[$field] === '')
                                                —
                                            @else
                                                {{ number_format((float) $row[$field], 2) }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="billing-rates-empty">No rates are available yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="billing-rates-note">
                    These are your current rates. Past campaign totals on this report stay as billed.
                </p>
            </div>
        </div>
    </div>

    <div id="billing-outstanding-modal" class="billing-rates-modal" hidden aria-hidden="true">
        <div class="billing-rates-modal-backdrop" data-outstanding-close tabindex="-1"></div>
        <div class="billing-rates-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="billing-outstanding-modal-title">
            <div class="billing-rates-modal-header">
                <div class="min-w-0">
                    <h2 id="billing-outstanding-modal-title" class="billing-rates-modal-title">Outstanding by month</h2>
                    <p class="billing-rates-modal-subtitle">Unpaid balance across all billing months</p>
                </div>
                <button type="button" class="billing-rates-modal-close" data-outstanding-close aria-label="Close">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <div class="billing-rates-modal-body">
                <ul class="billing-outstanding-breakdown-list">
                    @foreach ($overallOutstanding['breakdown'] as $monthRow)
                        <li>
                            <a href="{{ route('admin.local-client.billing.report', [
                                'id' => $client->id,
                                'token' => $client->billing_report_token,
                                'filter_year' => $monthRow['year'],
                                'filter_month' => $monthRow['month_num'],
                                'status' => 'unpaid',
                            ]) }}"
                               class="billing-outstanding-breakdown-item">
                                <span class="billing-outstanding-breakdown-amount">{{ $monthRow['formatted_amount'] }}</span>
                                <span class="billing-outstanding-breakdown-label">remaining in {{ $monthRow['label'] }}</span>
                                <span class="billing-outstanding-breakdown-meta">{{ $monthRow['campaign_count'] }} campaign{{ $monthRow['campaign_count'] === 1 ? '' : 's' }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="billing-rates-note">
                    Tap a month to view unpaid campaigns for that period.
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (function () {
            const yearSelect = document.getElementById('billing-filter-year');
            const monthSelect = document.getElementById('billing-filter-month');

            if (yearSelect && monthSelect) {
                function syncMonthState() {
                    const hasYear = yearSelect.value !== '';
                    monthSelect.disabled = !hasYear;

                    if (!hasYear) {
                        monthSelect.value = '';
                    }
                }

                yearSelect.addEventListener('change', syncMonthState);
                syncMonthState();
            }

            function bindModal(modalId, openBtnId, closeAttr, focusReturnBtn) {
                const modal = document.getElementById(modalId);
                const openBtn = openBtnId ? document.getElementById(openBtnId) : null;
                if (!modal) {
                    return;
                }

                const dialog = modal.querySelector('.billing-rates-modal-dialog');

                function openModal() {
                    modal.hidden = false;
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('billing-rates-modal-open');
                    dialog?.querySelector('.billing-rates-modal-close')?.focus();
                }

                function closeModal() {
                    modal.hidden = true;
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('billing-rates-modal-open');
                    if (focusReturnBtn) {
                        focusReturnBtn.focus();
                    }
                }

                if (openBtn) {
                    openBtn.addEventListener('click', openModal);
                }

                modal.querySelectorAll('[' + closeAttr + ']').forEach(function (el) {
                    el.addEventListener('click', closeModal);
                });

                document.addEventListener('keydown', function (e) {
                    if (e.key === 'Escape' && !modal.hidden) {
                        closeModal();
                    }
                });
            }

            bindModal('billing-rates-modal', 'billing-view-rates-btn', 'data-rates-close', document.getElementById('billing-view-rates-btn'));
            bindModal('billing-outstanding-modal', 'billing-outstanding-breakdown-btn', 'data-outstanding-close', document.getElementById('billing-outstanding-breakdown-btn'));
        })();
    </script>
@endpush
