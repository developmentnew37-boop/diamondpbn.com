@extends('admin.layout.layout')

@section('title', 'Status check preview')

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
            justify-content: center;
            min-height: 42px;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }
        .sc-btn:hover { background: #e0410f; border-color: #e0410f; color: #fff; }
        .sc-error { color: #991b1b; font-weight: 600; max-width: 28rem; }
        .sc-filter {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            min-height: 36px;
            padding: 0.3rem 0.75rem;
            font-size: 0.8125rem;
            font-weight: 600;
            border-radius: 9999px;
            border: 1px solid #e5e7eb;
            background: #fff;
            color: #374151;
            text-decoration: none;
        }
        .sc-filter.is-active {
            background: var(--primary-color);
            border-color: var(--primary-color);
            color: #fff;
        }
    </style>
@endpush

@section('main-content')
    <div class="page-header">
        <div class="sc-page-header">
            <div>
                <h2 class="page-title">{{ $runLabel }}</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.domain.status-checker.campaigns') }}" class="breadcrumb-link">Status campaigns</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Preview</span>
                    </div>
                </div>
            </div>
            <div class="sc-page-header-actions">
                @if ($check->isFinished())
                    <a href="{{ route('admin.domain.status-checker.export', $check->uuid) }}" class="sc-btn">Download Excel</a>
                @else
                    <form method="POST" action="{{ route('admin.domain.status-checker.cancel', $check->uuid) }}">
                        @csrf
                        <button type="submit" class="sc-btn">Cancel check</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="w-full flex flex-wrap gap-4 justify-center">
        <div class="w-full mx-auto content-card">
            <div class="px-6 pt-6 pb-8 flex flex-col gap-4">
                @if (session('cus__success'))
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700">
                        <span class="font-medium">{{ session('cus__success') }}</span>
                    </div>
                @endif

                <p class="text-sm text-gray-600">
                    {{ $check->source === 'inventory' ? 'Inventory' : 'Manual' }}
                    · Status: <strong class="text-gray-900">{{ str_replace('_', ' ', $check->status) }}</strong>
                    · Connected: <strong class="text-green-700">{{ (int) $check->connected_count }}</strong>
                    · Disconnected: <strong class="text-red-700">{{ (int) $check->disconnected_count }}</strong>
                </p>

                <div class="flex flex-wrap gap-2">
                    @foreach (['all' => 'All', 'connected' => 'Connected', 'disconnected' => 'Disconnected', 'verifying' => 'Verifying'] as $filter => $label)
                        <a class="sc-filter {{ $resultFilter === $filter ? 'is-active' : '' }}"
                            href="{{ route('admin.domain.status-checker.campaigns.show', array_filter(['uuid' => $check->uuid, 'result' => $filter === 'all' ? null : $filter])) }}">
                            {{ $label }} {{ $resultCounts[$filter] }}
                        </a>
                    @endforeach
                </div>

                <div class="overflow-x-auto border border-gray-200 rounded">
                    <table class="sc-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Domain</th>
                                <th>Status</th>
                                <th>HTTP</th>
                                <th>Category</th>
                                <th>Site error</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($items as $item)
                                @php
                                    $statusLabel = match ($item->check_status) {
                                        'connected' => 'Connected',
                                        'disconnected' => 'Disconnected',
                                        'retry_pending' => 'Verifying',
                                        'checking' => 'Checking',
                                        default => 'Pending',
                                    };
                                @endphp
                                <tr class="border-b">
                                    <td class="!px-4 !py-3 text-gray-500">{{ $item->sort_order }}</td>
                                    <td class="!px-4 !py-3 font-medium break-all">{{ $item->domain }}</td>
                                    <td class="!px-4 !py-3">{{ $statusLabel }}</td>
                                    <td class="!px-4 !py-3 text-gray-700">{{ $item->http_status ?: '—' }}</td>
                                    <td class="!px-4 !py-3 text-gray-600">{{ $item->category ?: 'Uncategorized' }}</td>
                                    <td class="!px-4 !py-3 {{ $item->check_status === 'disconnected' ? 'sc-error' : 'text-gray-600' }}">{{ $item->message ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="!px-4 !py-6 text-gray-500" colspan="6">{{ $resultFilter === 'all' ? 'No domains in this check.' : 'No domains match this filter.' }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{ $items->links() }}
            </div>
        </div>
    </div>
@endsection
