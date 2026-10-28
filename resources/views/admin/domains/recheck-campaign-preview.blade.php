@extends('admin.layout.layout')

@section('title', 'Recheck preview')

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
        .rd-link-btn:hover { background: var(--primary-color); color: #fff; }
    </style>
@endpush

@section('main-content')
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">{{ $categoryLabel }}</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.domain.recheck-disconnected.campaigns') }}" class="breadcrumb-link">Recheck campaigns</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Preview</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                @if ($check->isFinished())
                    <a href="{{ route('admin.domain.recheck-disconnected.export', $check->uuid) }}" class="rd-link-btn">
                        Download Excel
                    </a>
                @else
                    <form method="POST" action="{{ route('admin.domain.recheck-disconnected.cancel', $check->uuid) }}">
                        @csrf
                        <button type="submit" class="rd-link-btn">Cancel recheck</button>
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
                    Status: <strong class="text-gray-900">{{ str_replace('_', ' ', $check->status) }}</strong>
                    · Connected: <strong class="text-green-700">{{ (int) $check->connected_count }}</strong>
                    · Disconnected: <strong class="text-red-700">{{ (int) $check->disconnected_count }}</strong>
                </p>

                <div class="overflow-x-auto border border-gray-200 rounded">
                    <table class="rd-results-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Domain</th>
                                <th>Status</th>
                                <th>Category</th>
                                <th>Message</th>
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
                                    <td class="!px-4 !py-3 text-gray-600">{{ $item->category ?: 'Uncategorized' }}</td>
                                    <td class="!px-4 !py-3 text-gray-600">{{ $item->message ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="!px-4 !py-6 text-gray-500" colspan="5">No domains in this recheck.</td>
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
