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
                    <div class="breadcrumb-item"><a href="{{ route('admin.plugin-manager.index') }}" class="breadcrumb-link">Plugin Manager</a><span>›</span></div>
                    <div class="breadcrumb-item"><span class="breadcrumb-link">History</span></div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex justify-start sm:justify-end">
                <a href="{{ route('admin.plugin-manager.deploy.create') }}" class="pm-btn">Deploy Plugin</a>
            </div>
        </div>
    </div>

    <div class="w-full content-card !mt-4">
        @if ($deployments->isEmpty())
            <p class="text-sm text-gray-500">No deployments yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-800 text-white">
                        <tr>
                            <th class="!px-4 !py-3">Date</th>
                            <th class="!px-4 !py-3">Package</th>
                            <th class="!px-4 !py-3">Operation</th>
                            <th class="!px-4 !py-3">Category</th>
                            <th class="!px-4 !py-3">Status</th>
                            <th class="!px-4 !py-3">Results</th>
                            <th class="!px-4 !py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deployments as $deployment)
                            <tr class="border-b border-gray-100">
                                <td class="!px-4 !py-3">{{ $deployment->created_at->format('M j, Y H:i') }}</td>
                                <td class="!px-4 !py-3">{{ $deployment->pluginPackage?->displayLabel() ?? '—' }}</td>
                                <td class="!px-4 !py-3">{{ str_replace('_', ' ', $deployment->operation) }}</td>
                                <td class="!px-4 !py-3">{{ $deployment->domainCategory?->name ?? ($deployment->source === 'manual' ? 'Manual' : '—') }}</td>
                                <td class="!px-4 !py-3 capitalize">{{ $deployment->status }}</td>
                                <td class="!px-4 !py-3">
                                    <span class="text-green-700">{{ $deployment->success_count }} ok</span>,
                                    <span class="text-red-700">{{ $deployment->failed_count }} fail</span>,
                                    <span class="text-yellow-700">{{ $deployment->skipped_count }} skip</span>
                                </td>
                                <td class="!px-4 !py-3">
                                    <a href="{{ route('admin.plugin-manager.deployments.show', $deployment->uuid) }}" class="text-[var(--primary-color)] font-semibold text-xs">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endsection
