@extends('admin.layout.layout')

@section('title', 'Plugin Report')

@push('style')
    @include('admin.domains.plugin-manager.partials.styles')
@endpush

@section('main-content')
    <div class="page-header pm-page-header">
        <div class="pm-page-heading">
            <h2 class="page-title">Plugin Report</h2>
            <div class="breadcrumb flex-wrap">
                <div class="breadcrumb-item"><a href="{{ route('admin.plugin-manager.index') }}" class="breadcrumb-link">Plugin Manager</a><span>›</span></div>
                <div class="breadcrumb-item"><a href="{{ route('admin.plugin-manager.reports.create') }}" class="breadcrumb-link">Plugin Report</a><span>›</span></div>
                <div class="breadcrumb-item"><span class="breadcrumb-link">Progress</span></div>
            </div>
        </div>
        <div class="pm-page-actions">
            <a href="{{ route('admin.plugin-manager.reports.create') }}" class="pm-btn pm-btn-muted">New Report</a>
        </div>
    </div>

    <div id="pluginManagerAlert" class="w-full hidden !mt-2" role="alert"></div>

    <div class="w-full content-card !mt-4" id="pluginReportPanel"
        data-progress-url="{{ route('admin.plugin-manager.reports.progress', $report->uuid) }}"
        data-download-url="{{ route('admin.plugin-manager.reports.download', $report->uuid) }}"
        data-cancel-url="{{ route('admin.plugin-manager.reports.cancel', $report->uuid) }}"
        data-delete-url="{{ route('admin.plugin-manager.reports.destroy', $report->uuid) }}"
        data-index-url="{{ route('admin.plugin-manager.reports.create') }}"
        data-finished="{{ $report->isFinished() ? '1' : '0' }}"
        data-download-ready="{{ $report->status === 'completed' && $report->file_path ? '1' : '0' }}">
        <p class="text-sm text-gray-600 !mb-4">
            {{ number_format($report->total_count) }} domains
            @if ($report->domainCategory)
                · Category: <strong>{{ $report->domainCategory->name }}</strong>
            @else
                · Manual list
            @endif
        </p>

        <div class="progress-track !mb-2">
            <div class="progress-fill" id="pluginReportBar" style="width: {{ $report->progressPercent() }}%"></div>
        </div>
        <p id="pluginReportProgressText" class="text-xs text-gray-500 !mb-1">{{ $report->processed_count }} / {{ $report->total_count }} · {{ $report->progressPercent() }}%</p>
        <p id="pluginReportStatus" class="text-sm text-gray-700 !mb-4">{{ $report->status_message }}</p>

        <div class="flex flex-wrap gap-4 text-sm !mb-4">
            <span>Reached <strong id="pluginReportSuccess">{{ $report->success_count }}</strong></span>
            <span>Could not read <strong id="pluginReportFailed">{{ $report->failed_count }}</strong></span>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.plugin-manager.reports.download', $report->uuid) }}" id="pluginReportDownload" class="pm-btn {{ $report->status === 'completed' && $report->file_path ? '' : 'hidden' }}">
                <span class="material-symbols-outlined !text-base">download</span>
                Download Excel
            </a>
            <button type="button" id="pluginReportCancel" class="pm-btn pm-btn-muted {{ $report->isFinished() ? 'hidden' : '' }}">Cancel</button>
            <button type="button" id="pluginReportDelete" class="pm-btn pm-btn-muted">Delete report</button>
        </div>
        <p class="text-xs text-gray-500 !mt-3">The Excel file lists every plugin on each site. It is deleted with this report, and leftover files are removed after {{ config('plugin_manager.report_retention_hours') }} hours.</p>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/plugin-manager.js') }}?v={{ @filemtime(public_path('js/plugin-manager.js')) }}"></script>
@endpush
