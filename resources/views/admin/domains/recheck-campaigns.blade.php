@extends('admin.layout.layout')

@section('title', 'Recheck Campaigns')

@push('style')
    <style>
        .rd-results-table { width: 100%; font-size: 0.875rem; border-collapse: collapse; }
        .rd-results-table thead { background: #111827; }
        .rd-results-table thead th {
            padding: 0.75rem 1rem;
            text-align: left;
            font-weight: 500;
            font-size: 0.8125rem;
            color: #ffffff !important;
            white-space: nowrap;
        }
        .rd-link-btn {
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
        .rd-link-btn:hover { background: var(--primary-color); color: #fff; }
    </style>
@endpush

@section('main-content')
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Recheck campaigns</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.domain.recheck-disconnected') }}" class="breadcrumb-link">Recheck Disconnected</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Campaigns</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                <a href="{{ route('admin.domain.recheck-disconnected') }}"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 border border-gray-300 text-gray-700 hover:bg-gray-50 rounded">
                    New recheck
                </a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-wrap gap-4 justify-center">
        <div class="w-full mx-auto content-card">
            <div class="px-6 pt-6 pb-8 flex flex-col gap-4">
                <p class="text-sm text-gray-600">
                    Finished rechecks stay here for {{ (int) config('domain_status_checker.disconnected_retention_hours', 12) }} hours.
                    A processing run stays until it finishes. Pending domains are not counted as disconnected yet.
                </p>

                @if ($rechecks->isEmpty())
                    <p class="text-sm text-gray-700">No recheck campaigns yet.</p>
                @else
                    <div class="overflow-x-auto border border-gray-200 rounded">
                        <table class="rd-results-table">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Started</th>
                                    <th>Status</th>
                                    <th>Connected</th>
                                    <th>Disconnected</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rechecks as $run)
                                    @php
                                        $runCategory = $run->domain_category_id
                                            ? ($run->domainCategory->name ?? 'All categories')
                                            : 'All categories';
                                    @endphp
                                    <tr class="border-b">
                                        <td class="!px-4 !py-3 font-medium text-gray-900">{{ $runCategory }}</td>
                                        <td class="!px-4 !py-3 text-gray-600 whitespace-nowrap">
                                            {{ ($run->started_at ?? $run->created_at)?->timezone(config('app.timezone'))->format('d M Y H:i') }}
                                        </td>
                                        <td class="!px-4 !py-3 text-gray-700">{{ str_replace('_', ' ', $run->status) }}</td>
                                        <td class="!px-4 !py-3 text-green-700">{{ (int) $run->connected_count }}</td>
                                        <td class="!px-4 !py-3 text-red-700">{{ (int) $run->disconnected_count }}</td>
                                        <td class="!px-4 !py-3">
                                            <a class="rd-link-btn" href="{{ route('admin.domain.recheck-disconnected.campaigns.show', $run->uuid) }}">Preview</a>
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
