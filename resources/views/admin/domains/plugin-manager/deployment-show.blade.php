@extends('admin.layout.layout')

@section('title', 'Deployment Progress')

@push('style')
    @include('admin.domains.plugin-manager.partials.styles')
@endpush

@section('main-content')
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Deployment Progress</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item"><a href="{{ route('admin.plugin-manager.index') }}" class="breadcrumb-link">Plugin Manager</a><span>›</span></div>
                    <div class="breadcrumb-item"><span class="breadcrumb-link">Progress</span></div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end gap-2">
                <a href="{{ route('admin.plugin-manager.deployments.index') }}" class="pm-btn pm-btn-muted">History</a>
                <a href="{{ route('admin.plugin-manager.deploy.create') }}" class="pm-btn pm-btn-muted">New Deploy</a>
            </div>
        </div>
    </div>

    <div id="pluginManagerAlert" class="w-full hidden !mt-2" role="alert"></div>

    <div class="w-full content-card !mt-4">
        <div class="flex flex-col gap-2 !mb-4">
            <p class="text-sm text-gray-600">
                <strong>{{ $deployment->pluginPackage?->displayLabel() }}</strong>
                @if ($deployment->pluginPackage)
                    · WP folder: <code class="text-xs bg-gray-100 !px-1 rounded">{{ $deployment->pluginPackage->expectedSlug() }}</code>
                @endif
                · Operation: <strong>{{ str_replace('_', ' ', $deployment->operation) }}</strong>
                @if ($deployment->domainCategory)
                    · Category: <strong>{{ $deployment->domainCategory->name }}</strong>
                @endif
            </p>
        </div>

        <div id="deploymentProgressPanel"
            data-progress-url="{{ route('admin.plugin-manager.deployments.progress', $deployment->uuid) }}"
            data-retry-url="{{ route('admin.plugin-manager.deployments.retry-failed', $deployment->uuid) }}"
            data-cancel-url="{{ route('admin.plugin-manager.deployments.cancel', $deployment->uuid) }}"
            data-initial-finished="{{ $deployment->isFinished() ? '1' : '0' }}">

            <div class="flex items-center justify-between gap-3 flex-wrap !mb-3">
                <h3 class="text-base font-semibold" id="deploymentProgressTitle">Deployment in progress</h3>
                <span id="deploymentPhaseText" class="text-sm text-gray-600">{{ $deployment->status_message }}</span>
            </div>
            <div class="progress-track !mb-2">
                <div class="progress-fill" id="deploymentProgressBar"></div>
            </div>
            <p id="deploymentProgressText" class="text-xs text-gray-500 !mb-4">0 / {{ $deployment->total_count }}</p>

            <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 !mb-4">
                <div class="summary-card"><p class="text-xs text-gray-500">Total</p><p class="value" id="sumTotal">{{ $deployment->total_count }}</p></div>
                <div class="summary-card border-green-200 bg-green-50"><p class="text-xs text-green-700">Success</p><p class="value text-green-700" id="sumSuccess">{{ $deployment->success_count }}</p></div>
                <div class="summary-card border-red-200 bg-red-50"><p class="text-xs text-red-700">Failed</p><p class="value text-red-700" id="sumFailed">{{ $deployment->failed_count }}</p></div>
                <div class="summary-card border-yellow-200 bg-yellow-50"><p class="text-xs text-yellow-700">Skipped</p><p class="value text-yellow-700" id="sumSkipped">{{ $deployment->skipped_count }}</p></div>
                <div class="summary-card"><p class="text-xs text-gray-500">Pending</p><p class="value" id="sumPending">{{ max(0, $deployment->total_count - $deployment->processed_count) }}</p></div>
            </div>

            <div class="pm-progress-toolbar">
                <div class="pm-progress-actions">
                    <button type="button" class="pm-btn hidden" id="retryFailedBtn">
                        <span class="material-symbols-outlined !text-base">replay</span>
                        Retry Failed / Skipped
                    </button>
                    <button type="button" class="pm-btn pm-btn-danger hidden" id="cancelDeployBtn">
                        <span class="material-symbols-outlined !text-base">cancel</span>
                        Cancel Queued
                    </button>
                </div>
                <div id="exportResultsGroup" class="pm-export-panel hidden">
                    <span class="pm-export-label">
                        <span class="material-symbols-outlined !text-base">download</span>
                        Export CSV
                    </span>
                    <div class="pm-segmented" role="group" aria-label="Export by status">
                        <a href="{{ route('admin.plugin-manager.deployments.export-failures', ['uuid' => $deployment->uuid, 'status' => 'all']) }}"
                            class="pm-segment export-status-link" data-status="all" target="_blank">All</a>
                        <a href="{{ route('admin.plugin-manager.deployments.export-failures', ['uuid' => $deployment->uuid, 'status' => 'failed']) }}"
                            class="pm-segment export-status-link" data-status="failed" target="_blank">Failed</a>
                        <a href="{{ route('admin.plugin-manager.deployments.export-failures', ['uuid' => $deployment->uuid, 'status' => 'skipped']) }}"
                            class="pm-segment export-status-link" data-status="skipped" target="_blank">Skipped</a>
                        <a href="{{ route('admin.plugin-manager.deployments.export-failures', ['uuid' => $deployment->uuid, 'status' => 'success']) }}"
                            class="pm-segment export-status-link" data-status="success" target="_blank">Success</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full content-card !mt-4">
        <div class="pm-results-toolbar">
            <div>
                <h3 class="text-base font-semibold text-gray-800 !m-0">Results</h3>
                <p class="text-xs text-gray-500 !mt-1 !mb-0">Filter rows by status without reloading.</p>
            </div>
            <div class="pm-segmented pm-segmented-filters" id="resultsStatusFilters" role="group" aria-label="Filter results by status">
                @foreach (['all' => 'All', 'success' => 'Success', 'failed' => 'Failed', 'skipped' => 'Skipped', 'pending' => 'Pending'] as $value => $label)
                    <button type="button"
                        class="pm-segment results-status-filter {{ $value === 'all' ? 'is-active' : '' }}"
                        data-status="{{ $value }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="pm-results-table-wrap overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-800 text-white">
                    <tr>
                        <th class="!px-4 !py-3">#</th>
                        <th class="!px-4 !py-3">Domain</th>
                        <th class="!px-4 !py-3">Category</th>
                        <th class="!px-4 !py-3">Before</th>
                        <th class="!px-4 !py-3">After</th>
                        <th class="!px-4 !py-3">Operation</th>
                        <th class="!px-4 !py-3">Status</th>
                        <th class="!px-4 !py-3">Plugin file</th>
                        <th class="!px-4 !py-3">Resolved via</th>
                        <th class="!px-4 !py-3">Audit</th>
                        <th class="!px-4 !py-3">Message</th>
                    </tr>
                </thead>
                <tbody id="deploymentResultsBody"></tbody>
            </table>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/plugin-manager.js') }}?v={{ @filemtime(public_path('js/plugin-manager.js')) }}"></script>
@endpush
