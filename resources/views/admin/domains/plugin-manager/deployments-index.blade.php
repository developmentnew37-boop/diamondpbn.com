@extends('admin.layout.layout')

@section('title', 'Deployment History')

@push('style')
    @include('admin.domains.plugin-manager.partials.styles')
@endpush

@section('main-content')
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Deployment History</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a><span>›</span></div>
                    <div class="breadcrumb-item"><a href="{{ route('admin.plugin-manager.index') }}" class="breadcrumb-link">Plugin Manager</a><span>›</span></div>
                    <div class="breadcrumb-item"><span class="breadcrumb-link">History</span></div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center gap-2">
                <a href="{{ route('admin.plugin-manager.index') }}" class="pm-btn pm-btn-muted">
                    <span class="material-symbols-outlined !text-base">inventory_2</span>
                    Library
                </a>
                <a href="{{ route('admin.plugin-manager.deploy.create') }}" class="pm-btn">
                    <span class="material-symbols-outlined !text-base">rocket_launch</span>
                    New Deploy
                </a>
            </div>
        </div>
    </div>

    <div id="pluginManagerAlert" class="w-full hidden !mt-2" role="alert"></div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 !mt-4">
        <div class="pm-stat-card">
            <span class="material-symbols-outlined pm-stat-icon">history</span>
            <div>
                <p class="pm-stat-label">Total runs</p>
                <p class="pm-stat-value">{{ number_format($stats['total']) }}</p>
            </div>
        </div>
        <div class="pm-stat-card pm-stat-card-blue">
            <span class="material-symbols-outlined pm-stat-icon">sync</span>
            <div>
                <p class="pm-stat-label">Active</p>
                <p class="pm-stat-value">{{ number_format($stats['active']) }}</p>
            </div>
        </div>
        <div class="pm-stat-card pm-stat-card-green">
            <span class="material-symbols-outlined pm-stat-icon">check_circle</span>
            <div>
                <p class="pm-stat-label">Completed</p>
                <p class="pm-stat-value">{{ number_format($stats['completed']) }}</p>
            </div>
        </div>
        <div class="pm-stat-card pm-stat-card-orange">
            <span class="material-symbols-outlined pm-stat-icon">language</span>
            <div>
                <p class="pm-stat-label">Domains processed</p>
                <p class="pm-stat-value">{{ number_format($stats['domains_processed']) }}</p>
            </div>
        </div>
    </div>

    <div class="w-full content-card !mt-4 pm-history-card">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 !mb-4 !pb-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-semibold text-gray-800">Recent deployments</h3>
                <p class="text-xs text-gray-500 !mt-1">Track install, update, and delete rollouts across your network.</p>
            </div>
            @if ($deployments->total() > 0)
                <span class="text-xs text-gray-500 bg-gray-50 border border-gray-200 rounded-full !px-3 !py-1 w-fit">
                    Showing {{ $deployments->firstItem() }}–{{ $deployments->lastItem() }} of {{ $deployments->total() }}
                </span>
            @endif
        </div>

        @if ($deployments->isEmpty())
            <div class="pm-empty-state">
                <span class="material-symbols-outlined pm-empty-icon">rocket_launch</span>
                <h4 class="text-base font-semibold text-gray-800">No deployments yet</h4>
                <p class="text-sm text-gray-500 !mt-1 max-w-md">Upload a plugin to the library, then deploy it by category or manual domain list.</p>
                <a href="{{ route('admin.plugin-manager.deploy.create') }}" class="pm-btn !mt-4">
                    <span class="material-symbols-outlined !text-base">rocket_launch</span>
                    Start first deployment
                </a>
            </div>
        @else
            <div class="pm-history-table-wrap">
                <table class="pm-history-table">
                    <colgroup>
                        <col class="pm-col-started">
                        <col class="pm-col-package">
                        <col class="pm-col-operation">
                        <col class="pm-col-scope">
                        <col class="pm-col-progress">
                        <col class="pm-col-results">
                        <col class="pm-col-status">
                        <col class="pm-col-actions">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>Started</th>
                            <th>Package</th>
                            <th>Operation</th>
                            <th>Scope</th>
                            <th>Progress</th>
                            <th>Results</th>
                            <th>Status</th>
                            <th class="pm-th-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deployments as $deployment)
                            @php
                                $package = $deployment->pluginPackage;
                                $progress = $deployment->progressPercent();
                                $scopeLabel = $deployment->domainCategory?->name ?? ($deployment->source === 'manual' ? 'Manual list' : '—');
                            @endphp
                            <tr class="pm-history-row" data-deployment-uuid="{{ $deployment->uuid }}">
                                <td>
                                    <div class="pm-cell pm-cell-date">
                                        <span class="pm-cell-primary">{{ $deployment->created_at->format('M j, Y') }}</span>
                                        <span class="pm-cell-secondary">{{ $deployment->created_at->format('H:i') }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($package)
                                        <div class="pm-cell pm-cell-package">
                                            <span class="pm-package-name" title="{{ $package->name }}">{{ Str::limit($package->name, 42) }}</span>
                                            <span class="pm-package-meta">
                                                <span class="pm-version-tag">v{{ $package->version }}</span>
                                                <span class="pm-meta-dot">·</span>
                                                <span class="pm-folder-tag" title="{{ $package->expectedSlug() }}">{{ Str::limit($package->expectedSlug(), 28) }}</span>
                                            </span>
                                        </div>
                                    @else
                                        <div class="pm-cell">
                                            <span class="pm-cell-muted">Package removed</span>
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="pm-cell pm-cell-center">
                                        <span class="pm-op-badge pm-op-{{ $deployment->operation }}">
                                            {{ $deployment->operationLabel() }}
                                        </span>
                                    </div>
                                </td>
                                <td>
                                    <div class="pm-cell">
                                        <span class="pm-cell-primary pm-scope-name" title="{{ $scopeLabel }}">{{ Str::limit($scopeLabel, 24) }}</span>
                                        <span class="pm-cell-secondary">{{ number_format($deployment->total_count) }} domains</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="pm-cell pm-progress-cell">
                                        <div class="pm-progress-meta">
                                            <span>{{ $deployment->processed_count }}/{{ $deployment->total_count }}</span>
                                            <span>{{ $progress }}%</span>
                                        </div>
                                        <div class="progress-track pm-progress-bar">
                                            <div class="progress-fill" style="width: {{ $progress }}%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="pm-cell pm-cell-center pm-results-cell">
                                        @if ($deployment->success_count > 0)
                                            <span class="pm-result-pill pm-result-success">{{ $deployment->success_count }} ok</span>
                                        @endif
                                        @if ($deployment->failed_count > 0)
                                            <span class="pm-result-pill pm-result-failed">{{ $deployment->failed_count }} fail</span>
                                        @endif
                                        @if ($deployment->skipped_count > 0)
                                            <span class="pm-result-pill pm-result-skipped">{{ $deployment->skipped_count }} skip</span>
                                        @endif
                                        @if ($deployment->success_count === 0 && $deployment->failed_count === 0 && $deployment->skipped_count === 0)
                                            <span class="pm-cell-muted">—</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="pm-cell pm-cell-center pm-status-cell">
                                        <span class="pm-deploy-status {{ $deployment->statusBadgeClass() }}">
                                            @if ($deployment->hasFailures())
                                                <span class="material-symbols-outlined pm-status-icon">warning</span>
                                            @endif
                                            {{ ucfirst($deployment->status) }}
                                        </span>
                                        @if ($deployment->isActive())
                                            <span class="pm-status-note pm-status-note-active">In progress</span>
                                        @elseif ($deployment->hasFailures())
                                            <span class="pm-status-note pm-status-note-error">With failures</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="pm-cell pm-actions-cell">
                                        <a href="{{ route('admin.plugin-manager.deployments.show', $deployment->uuid) }}"
                                            class="pm-icon-btn pm-icon-btn-view" title="View details">
                                            <span class="material-symbols-outlined">visibility</span>
                                        </a>
                                        <button type="button"
                                            class="pm-icon-btn pm-icon-btn-delete deployment-delete-btn"
                                            data-delete-url="{{ route('admin.plugin-manager.deployments.destroy', $deployment->uuid) }}"
                                            data-active="{{ $deployment->isActive() ? '1' : '0' }}"
                                            title="Delete from history">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($deployments->hasPages())
                <div class="!mt-6 flex justify-center">
                    {{ $deployments->links() }}
                </div>
            @endif
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/plugin-manager.js') }}?v={{ @filemtime(public_path('js/plugin-manager.js')) }}"></script>
@endpush
