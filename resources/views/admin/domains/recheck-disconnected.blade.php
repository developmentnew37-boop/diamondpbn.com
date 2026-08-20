@extends('admin.layout.layout')

@section('title', 'Recheck Disconnected Domains')

@push('style')
    <style>
        .rd-summary-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            background: #fff;
        }
        .rd-summary-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }
        .rd-info-box {
            background-color: rgba(255, 74, 23, 0.08);
            border: 1px solid rgba(255, 74, 23, 0.25);
            border-radius: 0.25rem;
        }
        .rd-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            min-height: 46px;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            color: #fff;
            border-radius: 0.25rem;
            background-color: var(--primary-color);
            transition: background-color 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .rd-btn:hover:not(:disabled) { background-color: #e0410f; }
        .rd-btn:disabled { opacity: 0.7; cursor: not-allowed; }
        .rd-btn-outline {
            background: #fff;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            text-decoration: none;
        }
        .rd-btn-outline:hover:not(:disabled) {
            background: var(--primary-color);
            color: #fff;
        }
        .rd-btn-outline:disabled {
            opacity: 0.5;
            pointer-events: none;
        }
        .rd-filter-bar {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
            padding: 1rem 1.125rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
        }
        .rd-filter-control {
            width: 100%;
            max-width: 320px;
            min-height: 42px;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            color: #111827;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            border-radius: 0.25rem;
            outline: none;
        }
        .rd-filter-control:focus {
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 74, 23, 0.12);
        }
        .rd-progress-track {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }
        .rd-progress-fill {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, var(--primary-color), #fb923c);
            border-radius: 9999px;
            transition: width 0.35s ease;
        }
        .rd-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
            white-space: nowrap;
        }
        .rd-status-pending { background: #f3f4f6; color: #6b7280; }
        .rd-status-checking { background: #dbeafe; color: #1d4ed8; }
        .rd-status-retry { background: #fef3c7; color: #b45309; }
        .rd-status-connected { background: #dcfce7; color: #15803d; }
        .rd-status-disconnected { background: #fee2e2; color: #b91c1c; }
        .rd-category-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.35rem 0.75rem;
            font-size: 0.8125rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 9999px;
            color: #374151;
        }
        .rd-category-chip strong { color: #111827; }
        .rd-results-table {
            width: 100%;
            font-size: 0.875rem;
            border-collapse: collapse;
        }
        .rd-results-table thead {
            background: #111827;
        }
        .rd-results-table thead th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 500;
            font-size: 0.8125rem;
            color: #ffffff !important;
            white-space: nowrap;
            border-bottom: 1px solid #1f2937;
        }
    </style>
@endpush

@section('main-content')
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Recheck Disconnected</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.select.category') }}" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Recheck Disconnected</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                <a href="{{ route('admin.domain.status-checker') }}"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded">
                    Status Checker
                </a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-wrap gap-4 justify-center">
        <div class="w-full mx-auto content-card">
            <div class="px-6 pt-6 pb-8 flex flex-col gap-5">
                <div class="rd-info-box !p-4 text-sm text-gray-700">
                    Rechecks inventory domains currently marked <strong>disconnected</strong>. Sites that respond are
                    updated to <strong>connected</strong>. Each domain is probed up to <strong>5 times</strong> with
                    delays of <strong>1m → 2m → 4m → 5m</strong> between failures (campaign-style). Work continues in
                    the background if you leave this page — come back anytime to see connected vs disconnected counts.
                    Requires a queue worker on <code class="text-xs bg-white px-1 rounded">domainCheck</code>.
                </div>

                <p class="text-sm text-gray-600">
                    Inventory still marked disconnected:
                    <strong class="text-gray-900">{{ number_format((int) $disconnectedTotal) }}</strong>
                </p>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="rd-summary-card">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Total</div>
                        <div class="value text-gray-900" id="rdSummaryTotal">{{ (int) ($resumeBootstrap['check']['total'] ?? $resumeBootstrap['check']['started_disconnected'] ?? 0) }}</div>
                    </div>
                    <div class="rd-summary-card">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Connected</div>
                        <div class="value text-green-700" id="rdSummaryConnected">{{ (int) ($resumeBootstrap['check']['now_connected'] ?? 0) }}</div>
                    </div>
                    <div class="rd-summary-card">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Disconnected</div>
                        <div class="value text-red-700" id="rdSummaryStill">{{ (int) ($resumeBootstrap['check']['still_disconnected'] ?? 0) }}</div>
                    </div>
                    <div class="rd-summary-card">
                        <div class="text-xs uppercase tracking-wide text-gray-500">Errors</div>
                        <div class="value text-amber-700" id="rdSummaryErrors">{{ (int) ($resumeBootstrap['check']['errors'] ?? 0) }}</div>
                    </div>
                </div>

                @if (count($disconnectedByCategory) > 0)
                    <div class="flex flex-wrap gap-2">
                        @foreach ($disconnectedByCategory as $row)
                            <span class="rd-category-chip">
                                {{ $row['category'] }}
                                <strong>{{ (int) $row['count'] }}</strong>
                            </span>
                        @endforeach
                    </div>
                @endif

                <div id="rdAlert" class="hidden"></div>

                <form id="rdForm"
                    method="post"
                    action="{{ route('admin.domain.recheck-disconnected.start') }}"
                    data-start-url="{{ route('admin.domain.recheck-disconnected.start') }}"
                    data-progress-url-template="{{ route('admin.domain.recheck-disconnected.progress', ['uuid' => '__UUID__']) }}"
                    data-export-url-template="{{ route('admin.domain.recheck-disconnected.export', ['uuid' => '__UUID__']) }}"
                    data-cancel-url-template="{{ route('admin.domain.recheck-disconnected.cancel', ['uuid' => '__UUID__']) }}"
                    @if (! empty($resumeBootstrap['check']['uuid']))
                        data-resume-uuid="{{ $resumeBootstrap['check']['uuid'] }}"
                    @endif
                    class="rd-filter-bar">
                    @csrf
                    @if (! empty($resumeBootstrap))
                        <script type="application/json" id="rdResumeBootstrap">@json($resumeBootstrap)</script>
                    @endif
                    <div class="flex flex-col gap-1">
                        <label for="domain_category_id" class="text-sm font-semibold text-gray-700">Category</label>
                        <select name="domain_category_id" id="domain_category_id" class="rd-filter-control"
                            onchange="window.location='{{ route('admin.domain.recheck-disconnected') }}'+(this.value ? ('?domain_category_id='+this.value) : '')">
                            <option value="">All categories</option>
                            @foreach ($domainCategories as $category)
                                <option value="{{ $category->id }}"
                                    {{ (string) $selectedCategoryId === (string) $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 !mt-1">
                            Up to {{ number_format($disconnectedMax) }} disconnected domains per run.
                            @if ($disconnectedTotal > $disconnectedMax)
                                <span class="text-amber-700 font-medium">
                                    Scope has {{ number_format($disconnectedTotal) }} — only the first
                                    {{ number_format($disconnectedMax) }} will be checked.
                                </span>
                            @endif
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        @php
                            $resumeActive = ! empty($resumeBootstrap) && empty($resumeBootstrap['check']['is_finished']);
                            $resumeFinished = ! empty($resumeBootstrap['check']['is_finished']);
                            $resumeUuid = $resumeBootstrap['check']['uuid'] ?? null;
                        @endphp
                        <button type="submit" id="rdSubmitBtn" class="rd-btn"
                            {{ $resumeActive || $disconnectedTotal < 1 ? 'disabled' : '' }}>
                            <span id="rdSubmitSpinner" class="{{ $resumeActive ? '' : 'hidden' }} status-spinner"></span>
                            <span id="rdSubmitLabel">{{ $resumeActive ? 'Rechecking...' : 'Recheck disconnected' }}</span>
                        </button>
                        <button type="button" id="rdCancelBtn" class="rd-btn rd-btn-outline"
                            {{ $resumeActive ? '' : 'disabled' }}>
                            Cancel recheck
                        </button>
                        <a href="{{ $resumeFinished && $resumeUuid ? route('admin.domain.recheck-disconnected.export', $resumeUuid) : '#' }}"
                            id="rdExportBtn"
                            class="rd-btn rd-btn-outline {{ $resumeFinished ? '' : 'opacity-50' }}"
                            @if (! $resumeFinished) aria-disabled="true" @endif>
                            Download CSV
                        </a>
                    </div>
                </form>

                <div id="rdProgress" class="{{ ! empty($resumeBootstrap) ? '' : 'hidden' }} flex flex-col gap-2">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="text-sm font-semibold text-gray-800" id="rdProgressTitle">
                            {{ ! empty($resumeBootstrap['check']['is_finished']) ? 'Recheck complete' : 'Background check in progress' }}
                        </h3>
                        <span class="text-xs text-gray-500" id="rdProgressText">
                            @if (! empty($resumeBootstrap['check']))
                                {{ (int) ($resumeBootstrap['check']['processed'] ?? 0) }} / {{ (int) ($resumeBootstrap['check']['total'] ?? 0) }} finalized · {{ (int) ($resumeBootstrap['check']['progress_percent'] ?? 0) }}%
                            @else
                                0 / 0
                            @endif
                        </span>
                    </div>
                    <div class="rd-progress-track">
                        <div class="rd-progress-fill" id="rdProgressBar"
                            style="width: {{ (int) ($resumeBootstrap['check']['progress_percent'] ?? 0) }}%"></div>
                    </div>
                    <p class="text-xs text-gray-600" id="rdPhaseText">
                        {{ $resumeBootstrap['check']['status_message'] ?? 'Queued — waiting for domainCheck worker...' }}
                    </p>
                </div>

                <div id="rdCategoryBreakdown" class="hidden text-sm text-gray-700"></div>

                <div id="rdResults" class="hidden overflow-x-auto border border-gray-200 rounded">
                    <table class="rd-results-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Domain</th>
                                <th>Status</th>
                                <th>Details</th>
                                <th>Category</th>
                                <th>Message</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody id="rdResultsBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/recheck-disconnected.js') }}"></script>
@endpush
