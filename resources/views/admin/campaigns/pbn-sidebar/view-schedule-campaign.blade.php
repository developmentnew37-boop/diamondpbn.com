@extends('admin.layout.layout')

@section('title', 'Scheduled Sidebar Campaign')

@section('main-content')

    {{-- ================= Breadcrumbs ================= --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.schedule.sidebar.campaign.index') }}" class="breadcrumb-link">
                            Schedule Blogroll
                        </a>
                    </div>
                </div>
            </div>

            <div class="w-1/2 flex justify-end">
                <a href="javascript:void(0)" onclick="history.back()"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7M3 12h18" />
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </div>

    {{-- ================= Alerts ================= --}}
    <div class="w-full !mt-3">
        @if (session('cus__success'))
            <div class="!p-4 !mb-2 text-sm rounded bg-green-100 text-green-700">
                {{ session('cus__success') }}
            </div>
        @endif

        @if (session('cus__error'))
            <div class="!p-4 !mb-2 text-sm rounded bg-red-100 text-red-700">
                {{ session('cus__error') }}
            </div>
        @endif
    </div>

    {{-- ================= Campaign Header ================= --}}
    <div class="content-card w-full">

        <h2 class="text-lg !mb-4 bg-[var(--primary-color)] text-white !px-4 !py-2 rounded w-fit">
            {{ $campaign->campaign_no }} — Scheduled Sidebar Campaign
        </h2>

        {{-- ================= Tasks Table ================= --}}
        <div class="overflow-x-auto mt-4">
            <table class="display w-full border border-gray-200 text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        @php
                            $tHead = [
                                'S.No',
                                'Campaign No',
                                'Domain',
                                'Target URL',
                                'Anchor',
                                'Nofollow',
                                'Remote ID',
                                'Remote URL',
                                'Attempts',
                                'Schedule At',
                                'Last Error',
                                'Next Retry',
                                'Status',
                                'Created At',
                            ];
                        @endphp

                        @foreach ($tHead as $t)
                            <th class="border border-gray-200 !px-2 !py-3 font-normal text-left">
                                {{ $t }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($campaignTasks as $index => $task)
                        <tr class="hover:bg-gray-50">

                            {{-- S.No --}}
                            <td class="border !px-2 !py-2 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            {{-- Campaign No --}}
                            <td class="border !px-2 !py-2">
                                {{ $campaign->campaign_no }}
                            </td>

                            {{-- Domain --}}
                            <td class="border !px-2 !py-2">
                                {{ optional($task->domain?->domain)->name ?? '-' }}
                            </td>

                            {{-- Target URL --}}
                            <td class="border !px-2 !py-2 max-w-[260px] truncate" title="{{ $task->link?->target_url }}">
                                {{ $task->link?->target_url ?? '-' }}
                            </td>

                            {{-- Anchor --}}
                            <td class="border !px-2 !py-2">
                                {{ $task->link?->anchor_keyword ?? '-' }}
                            </td>

                            {{-- Nofollow --}}
                            <td class="border !px-2 !py-2 text-center">
                                {{ $task->link?->nofollow ? 'Yes' : 'No' }}
                            </td>

                            {{-- Remote ID --}}
                            <td class="border !px-2 !py-2">
                                {{ $task->remote_id ?? '-' }}
                            </td>

                            {{-- Remote URL --}}
                            <td class="border !px-2 !py-2 text-center">
                                @if ($task->remote_url)
                                    <a href="{{ $task->remote_url }}" target="_blank"
                                        class="bg-yellow-500 text-white rounded px-2 py-1 text-xs">
                                        View
                                    </a>
                                @else
                                    -
                                @endif
                            </td>

                            {{-- Attempts --}}
                            <td class="border !px-2 !py-2 text-center">
                                {{ $task->attempt_count }}
                            </td>
                            <td class="border !px-2 !py-2 text-xs">
                                {{ $task->schedule_at?->format('d M Y H:i') ?? '-' }}
                            </td>
                            {{-- Last Error --}}
                            <td class="border !px-2 !py-2 max-w-[300px] truncate" title="{{ $task->last_error }}">
                                {{ $task->last_error ?? '-' }}
                            </td>

                            {{-- Next Retry --}}
                            <td class="border !px-2 !py-2">
                                {{ $task->next_retry_at?->format('d M Y H:i') ?? '-' }}
                            </td>

                            {{-- Status --}}
                            <td class="border !px-2 !py-2 text-center">
                                @php
                                    $statusMap = [
                                        'queued' => 'bg-gray-100 text-gray-700',
                                        'publishing' => 'bg-yellow-100 text-yellow-700',
                                        'success' => 'bg-green-100 text-green-700',
                                        'failed' => 'bg-red-100 text-red-700',
                                    ];
                                @endphp
                                <span
                                    class="!px-2 !py-1 rounded text-xs font-semibold
                                {{ $statusMap[$task->status] ?? 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($task->status) }}
                                </span>
                            </td>

                            {{-- Created At --}}
                            <td class="border !px-2 !py-2">
                                {{ $task->created_at?->format('d M Y H:i') }}
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No scheduled sidebar tasks found…
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-3">
            {{ $campaignTasks->links() }}
        </div>

    </div>

@endsection

@push('scripts')
     <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>

@endpush
