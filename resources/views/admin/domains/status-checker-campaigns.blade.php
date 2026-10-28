@extends('admin.layout.layout')

@section('title', 'Status campaigns')

@push('style')
    <style>
        .sc-table { width: 100%; font-size: 0.875rem; border-collapse: collapse; }
        .sc-table thead { background: #111827; }
        .sc-table thead th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 500;
            font-size: 0.8125rem;
            color: #ffffff !important;
            white-space: nowrap;
        }
        .sc-link-btn {
            display: inline-flex;
            align-items: center;
            min-height: 36px;
            padding: 0.35rem 0.75rem;
            font-size: 0.8125rem;
            color: var(--primary-color);
            border: 1px solid var(--primary-color);
            border-radius: 0.25rem;
            text-decoration: none;
            background: #fff;
        }
        .sc-link-btn:hover { background: var(--primary-color); color: #fff; }
        .sc-page-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .sc-page-header-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
        }
        @media (min-width: 768px) {
            .sc-page-header-actions { margin-left: auto; }
        }
        .sc-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            min-height: 42px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            line-height: 1;
            border-radius: 0.25rem;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .sc-btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }
        .sc-btn-primary:hover {
            background: #e0410f;
            border-color: #e0410f;
            color: #fff;
        }
        .sc-pill {
            display: inline-flex;
            align-items: center;
            padding: 0.15rem 0.55rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #f3f4f6;
            color: #374151;
            text-transform: capitalize;
        }
        .sc-pill.is-processing, .sc-pill.is-queued { background: #dbeafe; color: #1d4ed8; }
        .sc-pill.is-completed { background: #dcfce7; color: #166534; }
        .sc-pill.is-failed, .sc-pill.is-cancelled { background: #fee2e2; color: #991b1b; }
    </style>
@endpush

@section('main-content')
    <div class="page-header">
        <div class="sc-page-header">
            <div>
                <h2 class="page-title">Status campaigns</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.domain.status-checker') }}" class="breadcrumb-link">Status Checker</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Campaigns</span>
                    </div>
                </div>
            </div>
            <div class="sc-page-header-actions">
                <a href="{{ route('admin.domain.status-checker') }}" class="sc-btn sc-btn-primary">New check</a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-wrap gap-4 justify-center">
        <div class="w-full mx-auto content-card">
            <div class="px-6 pt-6 pb-8 flex flex-col gap-4">
                <p class="text-sm text-gray-600">
                    Finished checks stay here for {{ (int) config('domain_status_checker.status_checker_retention_hours', 12) }} hours.
                    A processing run stays until it finishes. The status column is what shows the run is done.
                </p>

                @if ($checks->isEmpty())
                    <p class="text-sm text-gray-700">No status checks yet.</p>
                @else
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="sc-table">
                            <thead>
                                <tr>
                                    <th>Source</th>
                                    <th>Category</th>
                                    <th>Started</th>
                                    <th>Status</th>
                                    <th>Connected</th>
                                    <th>Disconnected</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($checks as $run)
                                    @php
                                        $runLabel = $run->source === 'manual'
                                            ? 'Manual list'
                                            : ($run->domain_category_id ? ($run->domainCategory->name ?? 'All categories') : 'All categories');
                                    @endphp
                                    <tr class="border-b">
                                        <td class="!px-4 !py-3 text-gray-700">{{ $run->source === 'inventory' ? 'Inventory' : 'Manual' }}</td>
                                        <td class="!px-4 !py-3 font-medium text-gray-900">{{ $runLabel }}</td>
                                        <td class="!px-4 !py-3 text-gray-600 whitespace-nowrap">
                                            {{ ($run->started_at ?? $run->created_at)?->timezone(config('app.timezone'))->format('d M Y H:i') }}
                                        </td>
                                        <td class="!px-4 !py-3">
                                            <span class="sc-pill is-{{ $run->status }}">{{ str_replace('_', ' ', $run->status) }}</span>
                                        </td>
                                        <td class="!px-4 !py-3 text-green-700">{{ (int) $run->connected_count }}</td>
                                        <td class="!px-4 !py-3 text-red-700">{{ (int) $run->disconnected_count }}</td>
                                        <td class="!px-4 !py-3">
                                            <a class="sc-link-btn" href="{{ route('admin.domain.status-checker.campaigns.show', $run->uuid) }}">Preview</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
