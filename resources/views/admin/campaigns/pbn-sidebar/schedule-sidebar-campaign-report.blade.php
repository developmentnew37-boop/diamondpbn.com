@extends('admin.layout.general')

@section('title', 'Scheduled Sidebar Campaign Report')

@section('main-content')

    <div class="w-full content-card min-w-0 !p-3 sm:!p-4 lg:!p-6">

        {{-- Header --}}
        <div class="w-full flex flex-col gap-3 md:flex-row md:items-center md:justify-between !mb-6 min-w-0">
            <h2
                class="w-full md:w-auto text-sm sm:text-base md:text-lg capitalize break-words
                   bg-[var(--primary-color)] text-white
                   !px-4 !py-2 rounded">
                {{ $campaign->campaign_no }} — Scheduled Blogroll Report
            </h2>
            <a href="{{ route('admin.schedule.sidebar.campaign.report.export', [
                'campaign_no' => $campaign->campaign_no,
                'token' => $campaign->report_token,
            ]) }}"
                class="w-full sm:w-auto text-center !px-5 !py-3 bg-green-600 text-white rounded hover:bg-green-700 shrink-0">
                Export Excel
            </a>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 !mb-6">

            <div class="w-full min-w-0 bg-white rounded-xl shadow-sm !p-5 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Links</div>
                <div class="text-3xl font-bold text-gray-800">{{ $stats->total }}</div>
            </div>

            <div class="w-full min-w-0 bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Success</div>
                <div class="text-3xl font-bold text-green-600">{{ $stats->success }}</div>
            </div>

            <div class="w-full min-w-0 bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500">
                <div class="text-sm text-gray-500">Queued</div>
                <div class="text-3xl font-bold text-yellow-600">{{ $stats->queued }}</div>
            </div>

            <div class="w-full min-w-0 bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500">
                <div class="text-sm text-gray-500">Failed</div>
                <div class="text-3xl font-bold text-red-600">{{ $stats->failed }}</div>
            </div>

        </div>

        {{-- Table --}}
        <div class="overflow-x-auto w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            <table class="display w-full min-w-[1020px] border border-gray-200 text-sm whitespace-nowrap">

                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border !px-2 !py-3">S.No</th>
                        <th class="border !px-2 !py-3">Live Link</th>
                        <th class="border !px-2 !py-3">Domain</th>
                        <th class="border !px-2 !py-3">Keyword</th>
                        <th class="border !px-2 !py-3">Target URL</th>
                        <th class="border !px-2 !py-3">Scheduled At</th>
                        <th class="border !px-2 !py-3">Status</th>
                        <th class="border !px-2 !py-3">Date</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($tasks as $index => $task)
                        @php
                            $domain = optional($task->domain?->domain)->name ?? '-';
                            $keyword = $task->link?->anchor_keyword ?? '-';
                            $url = $task->link?->target_url ?? '-';

                            $isLive = $task->status === 'success';
                            $statusText = $isLive ? 'Live' : 'Not Live';
                            $statusClass = $isLive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                        @endphp

                        <tr class="hover:bg-gray-50">

                            <td class="border !px-2 !py-3 text-center">
                                {{ $index + 1 }}
                            </td>

                            <td class="border !px-2 !py-3 font-medium max-w-[14rem]">
                                @if ($domain !== '-')
                                    <a href="https://{{ $domain }}" target="_blank"
                                        class="block w-full overflow-hidden text-ellipsis whitespace-nowrap text-blue-600 hover:underline"
                                        title="{{ $domain }}">
                                        {{ $domain }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>

                            <td class="border !px-2 !py-3 max-w-[14rem]">
                                <span class="block w-full overflow-hidden text-ellipsis whitespace-nowrap" title="{{ $domain }}">
                                    {{ $domain }}
                                </span>
                            </td>

                            @php
                                $kwCell = \App\Support\ReportDisplay::keyword($keyword !== '-' ? $keyword : null);
                                $urlCell = \App\Support\ReportDisplay::url($url !== '-' ? $url : null);
                            @endphp
                            <td class="border !px-2 !py-3 max-w-[12rem] align-top">
                                <span class="block w-full overflow-hidden text-ellipsis whitespace-nowrap"
                                    @if ($kwCell['title'] !== '') title="{{ $kwCell['title'] }}" @endif>
                                    {{ $kwCell['display'] }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-3 max-w-[18rem] align-top">
                                <span class="block w-full overflow-hidden text-ellipsis whitespace-nowrap"
                                    @if ($urlCell['title'] !== '') title="{{ $urlCell['title'] }}" @endif>
                                    {{ $urlCell['display'] }}
                                </span>
                            </td>
                            <td class="border !px-2 !py-3 text-xs">
                                @php $reportDate = $task->scheduleDate?->schedule_date ?? $task->schedule_at; @endphp
                                {{ $reportDate ? $reportDate->format('d-M-Y') : '-' }}
                            </td>

                            <td class="border !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-3">
                                {{ $reportDate ? $reportDate->format('d M Y') : '-' }}
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="7" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No scheduled sidebar campaign links found
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

    </div>

@endsection
