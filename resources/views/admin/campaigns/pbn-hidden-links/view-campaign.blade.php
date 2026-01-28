@extends('admin.layout.layout')

@section('title', 'Hidden Links Campaign')

@section('main-content')

{{-- bread-crumbs --}}
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
                    <a href="{{ route('admin.hidden.link.campaign.index') }}" class="breadcrumb-link">
                        PBN Hidden Links
                    </a>
                      <span>›</span>
                </div>
                <div class="breadcrumb-item">
                    <span>{{ $campaign->campaign_no }}</span>
                </div>
            </div>
        </div>

        <div class="w-1/2 flex justify-end items-center">
            {{-- <a href="{{ url()->previous() ?: route('admin.hidden.link.campaign.index') }}" --}}
            <a href="javascript:void(0)" onclick="history.back()"
               class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M10 19l-7-7 7-7M3 12h18"/>
                </svg>
                Back
            </a>
        </div>
    </div>
</div>

{{-- alerts --}}
@if (session('cus__success') || session('cus__error'))
    <div class="mt-2">
        @if (session('cus__success'))
            <div class="p-4 text-sm rounded bg-green-100 text-green-700">
                {{ session('cus__success') }}
            </div>
        @endif
        @if (session('cus__error'))
            <div class="p-4 text-sm rounded bg-red-100 text-red-700">
                {{ session('cus__error') }}
            </div>
        @endif
    </div>
@endif

<div class="content-card mt-3">

    <h2 class="text-lg !mb-4 bg-[var(--primary-color)] text-white w-fit !px-3 !py-2 rounded">
        {{ $campaign->campaign_no }} — Hidden Links
    </h2>

    <div class="overflow-x-auto w-full">
        <table class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
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
                            'Remote URL',
                            'Attempts',
                            'Last Error',
                            'Next Retry',
                            'Status',
                            'Created At',
                        ];
                    @endphp

                    @foreach ($tHead as $t)
                        <th class="border border-gray-200 !px-2 !py-3 text-left">
                            {{ $t }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @forelse ($campaignTasks as $index => $task)
                    <tr class="hover:bg-gray-50">

                        <td class="border !px-2 !py-2 text-center">
                            {{ $index + 1 + $offset }}
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ $campaign->campaign_no }}
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ $task->domainRow?->domain?->name ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2 max-w-[260px] truncate"
                            title="{{ $task->linkRow?->target_url }}">
                            {{ $task->linkRow?->target_url ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ $task->linkRow?->anchor_keyword ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2 text-center">
                            {{ $task->linkRow?->nofollow ? 'Yes' : 'No' }}
                        </td>


                        <td class="border !px-2 !py-2 text-center">
                            @if ($task->remote_url)
                                <a href="{{ $task->remote_url }}" target="_blank"
                                   class="bg-yellow-500 text-white rounded !px-2 !py-1 text-xs">
                                    View
                                </a>
                            @else
                                -
                            @endif
                        </td>

                        <td class="border !px-2 !py-2 text-center">
                            {{ $task->attempt_count }}
                        </td>

                        <td class="border !px-2 !py-2 max-w-[260px] truncate"
                            title="{{ $task->last_error }}">
                            {{ $task->last_error ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ optional($task->next_retry_at)?->format('d M Y H:i') ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2 text-center">
                            @php
                                $statusMap = [
                                    'queued' => 'bg-gray-100 text-gray-700',
                                    'publishing' => 'bg-yellow-100 text-yellow-700',
                                    'success' => 'bg-green-100 text-green-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                ];
                            @endphp
                            <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusMap[$task->status] ?? '' }}">
                                {{ ucfirst($task->status) }}
                            </span>
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ $task->created_at?->format('d M Y H:i') }}
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="13" class="text-center !py-4 text-gray-500 bg-gray-100">
                            No hidden links tasks found…
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">
        {{ $campaignTasks->links() }}
    </div>

</div>

@endsection
