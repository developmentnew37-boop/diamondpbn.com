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
                <div class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a><span>›</span></div>
                <div class="breadcrumb-item"><a href="{{ route('admin.plugin-manager.index') }}" class="breadcrumb-link">Plugin Manager</a><span>›</span></div>
                <div class="breadcrumb-item"><span class="breadcrumb-link">Plugin Report</span></div>
            </div>
        </div>
        <div class="pm-page-actions">
            <a href="{{ route('admin.plugin-manager.index') }}" class="pm-btn pm-btn-muted">← Library</a>
        </div>
    </div>

    <div id="pluginManagerAlert" class="w-full hidden !mt-2" role="alert"></div>

    <div class="w-full content-card !mt-4">
        <form id="pluginReportForm" class="flex flex-col gap-4"
            data-start-url="{{ route('admin.plugin-manager.reports.start') }}">
            @csrf
            <p class="text-sm text-gray-600">Choose a category or a domain list. Each site’s installed plugins are written to an Excel file with name and version. Plugin rows are not saved in the database.</p>

            <input type="hidden" name="source" id="sourceInput" value="inventory">

            <div class="flex flex-wrap gap-2">
                <button type="button" class="source-tab" data-source="manual">Manual List</button>
                <button type="button" class="source-tab active" data-source="inventory">By Category</button>
            </div>

            <div id="panel-manual" class="source-panel">
                <textarea name="domains_list" id="domains_list" rows="10" placeholder="example.com&#10;another.net"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded font-mono"></textarea>
                <p class="text-xs text-gray-500">Max {{ config('plugin_manager.max_domains_per_deployment') }} domains per report.</p>
            </div>

            <div id="panel-inventory" class="source-panel active">
                <div class="flex flex-col gap-2 max-w-md">
                    <label for="domain_category_id" class="text-sm font-medium text-gray-700">Domain Category</label>
                    <select name="domain_category_id" id="domain_category_id"
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">
                        <option value="">Select category...</option>
                        @foreach ($domainCategories as $category)
                            <option value="{{ $category->id }}" data-count="{{ $category->domains_count }}">
                                {{ $category->name }} ({{ $category->domains_count }} domains)
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit" class="pm-btn w-fit" id="reportSubmitBtn">
                <span class="btn-spinner hidden" id="reportSubmitSpinner"></span>
                <span class="material-symbols-outlined !text-base">description</span>
                Start Report
            </button>
        </form>
    </div>

    @if ($reports->isNotEmpty())
        <div class="w-full content-card !mt-4">
            <h3 class="text-sm font-semibold text-gray-800 !mb-3">Recent reports</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b border-gray-200">
                            <th class="!py-2 !pr-3 font-medium">Started</th>
                            <th class="!py-2 !pr-3 font-medium">Scope</th>
                            <th class="!py-2 !pr-3 font-medium">Progress</th>
                            <th class="!py-2 !pr-3 font-medium">Status</th>
                            <th class="!py-2 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($reports as $report)
                            <tr class="border-b border-gray-100">
                                <td class="!py-2 !pr-3">{{ $report->created_at->format('M j, Y H:i') }}</td>
                                <td class="!py-2 !pr-3">{{ $report->domainCategory?->name ?? 'Manual list' }}</td>
                                <td class="!py-2 !pr-3">{{ $report->processed_count }}/{{ $report->total_count }}</td>
                                <td class="!py-2 !pr-3">{{ ucfirst($report->status) }}</td>
                                <td class="!py-2">
                                    <a href="{{ route('admin.plugin-manager.reports.show', $report->uuid) }}" class="text-[var(--primary-color)] font-semibold">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-gray-500 !mt-3">Finished reports and their Excel files are removed after {{ config('plugin_manager.report_retention_hours') }} hours.</p>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/plugin-manager.js') }}?v={{ @filemtime(public_path('js/plugin-manager.js')) }}"></script>
@endpush
