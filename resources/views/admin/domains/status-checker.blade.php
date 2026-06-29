@extends('admin.layout.layout')

@section('title', 'Domain Status Checker')

@push('style')
    <style>
        .summary-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            background: #fff;
        }

        .summary-card .value {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .theme-info-box {
            background-color: rgba(255, 74, 23, 0.08);
            border: 1px solid rgba(255, 74, 23, 0.25);
            border-radius: 0.25rem;
        }

        .theme-btn {
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

        .theme-btn:hover:not(:disabled) {
            background-color: #e0410f;
        }

        .theme-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .theme-btn-muted {
            background-color: #fff;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
        }

        .theme-btn-muted:hover {
            background-color: var(--primary-color);
            color: #fff;
        }

        .source-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 42px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-radius: 0.25rem;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .source-tab.active {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }

        .source-panel {
            display: none;
        }

        .source-panel.active {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .progress-track {
            width: 100%;
            height: 10px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, var(--primary-color), #fb923c);
            border-radius: 9999px;
            transition: width 0.35s ease;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
            white-space: nowrap;
        }

        .status-pending { background: #f3f4f6; color: #6b7280; }
        .status-checking { background: #dbeafe; color: #1d4ed8; }
        .status-retry { background: #fef3c7; color: #b45309; }
        .status-connected { background: #dcfce7; color: #166534; }
        .status-disconnected { background: #fee2e2; color: #991b1b; }

        .status-spinner {
            width: 12px;
            height: 12px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            animation: status-spin 0.8s linear infinite;
            opacity: 0.85;
        }

        @keyframes status-spin {
            to { transform: rotate(360deg); }
        }

        #statusCheckProgress.progress-complete .progress-header-spinner {
            display: none;
        }

        .btn-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #fff;
            border-radius: 50%;
            animation: status-spin 0.8s linear infinite;
        }
    </style>
@endpush

@section('main-content')
    @php
        $activeSource = 'manual';
    @endphp

    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Domain Status Checker</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Status Checker</a>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center gap-2">
                <a href="{{ route('admin.domain.index') }}" class="theme-btn theme-btn-muted">
                    <span class="material-symbols-outlined !text-base">list</span>
                    Domains List
                </a>
            </div>
        </div>
    </div>

    <div id="statusCheckerAlert" class="w-full hidden !mt-2" role="alert"></div>

    <div class="w-full content-card !mt-4">
        <div class="!p-4 theme-info-box !mb-4">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined text-[var(--primary-color)] !text-xl">info</span>
                <div class="text-sm text-gray-700">
                    Checks run on the <strong>domainCheck</strong> queue via a queue worker.
                    Start the worker: <code class="bg-white !px-1 rounded text-xs">php artisan queue:work --queue=domainCheck</code>
                    Slow sites get a <strong>second verification pass</strong> before marked disconnected.
                    Endpoint: <code class="bg-white !px-1 rounded text-xs">/wp-json/external/v1/status</code>
                </div>
            </div>
        </div>

        <form
            class="flex flex-col gap-4"
            id="statusCheckerForm"
            data-start-url="{{ route('admin.domain.status-checker.start') }}"
            data-progress-url-template="{{ route('admin.domain.status-checker.progress', ['uuid' => '__UUID__']) }}"
        >
            @csrf
            <input type="hidden" name="source" id="sourceInput" value="{{ $activeSource }}">

            <div class="flex flex-wrap gap-2">
                <button type="button" class="source-tab active" data-source="manual">
                    <span class="material-symbols-outlined !text-base">edit_note</span>
                    Manual List
                </button>
                <button type="button" class="source-tab" data-source="inventory">
                    <span class="material-symbols-outlined !text-base">database</span>
                    From System Inventory
                </button>
            </div>

            <div id="panel-manual" class="source-panel active">
                <div class="flex flex-col gap-2">
                    <label for="domains_list" class="text-sm font-medium text-gray-700">Domain List</label>
                    <textarea
                        id="domains_list"
                        name="domains_list"
                        rows="12"
                        placeholder="example.com&#10;another-domain.net&#10;https://third-site.org"
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)] font-mono"></textarea>
                    <p class="text-xs text-gray-500">Max 500 domains per run. Results stream in as each batch completes.</p>
                </div>

                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="checkbox" name="update_inventory" value="1" class="rounded">
                    Update status in database for domains that exist in inventory
                </label>
            </div>

            <div id="panel-inventory" class="source-panel">
                <div class="flex flex-col gap-2 max-w-md">
                    <label for="domain_category_id" class="text-sm font-medium text-gray-700">Domain Category</label>
                    <select name="domain_category_id" id="domain_category_id"
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
                        <option value="">All categories</option>
                        @foreach ($domainCategories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500">
                        Loads domains from inventory and saves status back automatically (max 500 per run).
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                <button type="submit" class="theme-btn" id="statusCheckSubmitBtn">
                    <span class="btn-spinner hidden" id="statusCheckSubmitSpinner"></span>
                    <span class="material-symbols-outlined !text-base">network_check</span>
                    <span id="statusCheckSubmitLabel">Check Status</span>
                </button>
                <button type="button" class="theme-btn theme-btn-muted" id="clearManualBtn">
                    Clear List
                </button>
            </div>
        </form>
    </div>

    <div id="statusCheckProgress" class="w-full content-card !mt-4 hidden">
        <div class="flex flex-col gap-3">
            <div class="flex items-center justify-between gap-3 flex-wrap">
                <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
                    <span id="statusCheckProgressSpinner" class="status-spinner progress-header-spinner" style="border-color: var(--primary-color); border-top-color: transparent;"></span>
                    <span id="statusCheckProgressTitle" class="progress-header-title">Background Check In Progress</span>
                </h3>
                <span id="statusCheckPhaseText" class="text-sm text-gray-600">Starting...</span>
            </div>
            <div class="progress-track">
                <div class="progress-fill" id="statusCheckProgressBar"></div>
            </div>
            <p id="statusCheckProgressText" class="text-xs text-gray-500">0 / 0</p>
        </div>
    </div>

    <div id="statusCheckSummary" class="w-full grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 !mt-4 hidden">
        <div class="summary-card">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
            <p class="value text-gray-900" id="summaryTotal">0</p>
        </div>
        <div class="summary-card border-green-200 bg-green-50">
            <p class="text-xs text-green-700 uppercase tracking-wide">Connected</p>
            <p class="value text-green-700" id="summaryConnected">0</p>
        </div>
        <div class="summary-card border-red-200 bg-red-50">
            <p class="text-xs text-red-700 uppercase tracking-wide">Disconnected</p>
            <p class="value text-red-700" id="summaryDisconnected">0</p>
        </div>
        <div class="summary-card border-orange-200 bg-orange-50">
            <p class="text-xs text-orange-700 uppercase tracking-wide">In System</p>
            <p class="value text-orange-700" id="summaryInInventory">0</p>
        </div>
        <div class="summary-card">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Not In System</p>
            <p class="value text-gray-700" id="summaryNotInInventory">0</p>
        </div>
    </div>

    <div id="statusCheckResults" class="w-full content-card min-w-0 !mt-4 hidden">
        <h3 class="text-base font-semibold text-gray-800 !mb-4">Live Results</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left" id="statusResultsTable">
                <thead class="text-xs uppercase bg-gray-800 text-white">
                    <tr>
                        <th class="!px-4 !py-3">#</th>
                        <th class="!px-4 !py-3">Domain</th>
                        <th class="!px-4 !py-3">Connection</th>
                        <th class="!px-4 !py-3">In System</th>
                        <th class="!px-4 !py-3">Category</th>
                        <th class="!px-4 !py-3">Message</th>
                        <th class="!px-4 !py-3">Response</th>
                    </tr>
                </thead>
                <tbody id="statusCheckResultsBody"></tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/domain-status-checker.js') }}?v={{ filemtime(public_path('js/domain-status-checker.js')) }}"></script>
@endpush
