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
            data-export-url="{{ route('admin.plugin-manager.deployments.export-failures', $deployment->uuid) }}"
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

            <div class="flex flex-wrap gap-2 !mb-4">
                <button type="button" class="pm-btn pm-btn-muted hidden" id="retryFailedBtn">Retry Failed</button>
                <button type="button" class="pm-btn pm-btn-muted hidden" id="cancelDeployBtn">Cancel Queued</button>
                <a href="#" class="pm-btn pm-btn-muted hidden" id="exportFailuresBtn" target="_blank">Export Failures CSV</a>
            </div>
        </div>
    </div>

    <div class="w-full content-card !mt-4">
        <h3 class="text-base font-semibold !mb-4">Results</h3>
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="text-xs uppercase bg-gray-800 text-white">
                    <tr>
                        <th class="!px-4 !py-3">#</th>
                        <th class="!px-4 !py-3">Domain</th>
                        <th class="!px-4 !py-3">Category</th>
                        <th class="!px-4 !py-3">Before</th>
                        <th class="!px-4 !py-3">After</th>
                        <th class="!px-4 !py-3">Status</th>
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
