@extends('admin.layout.general')

@section('title', 'Sidebar Campaign Report')

@section('main-content')

    <div class="w-full content-card min-w-0 !p-3 sm:!p-4 lg:!p-6">

        {{-- Header --}}
        <div class="w-full flex flex-col gap-3 md:flex-row md:items-center md:justify-between !mb-6 min-w-0">

            <h2
                class="w-full md:w-auto text-sm sm:text-base md:text-lg capitalize break-words
                   bg-[var(--primary-color)] text-white
                   !px-4 !py-2 rounded">
                {{ $campaign->campaign_no }} Sidebar Campaign Report
            </h2>

            <a href="{{ route('admin.sidebar.campaign.report.export', [
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
        <div class="w-full max-w-full min-w-0 overflow-hidden">
            <table class="w-full max-w-full table-fixed border border-gray-200 text-sm">

                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border !px-2 !py-3 w-14">S.No</th>
                        <th class="border !px-2 !py-3 w-[16%]">Live Link</th>
                        <th class="border !px-2 !py-3 w-[16%]">Domain</th>
                        <th class="border !px-2 !py-3 w-[12%]">Keyword</th>
                        <th class="border !px-2 !py-3">Target URL</th>
                        <th class="border !px-2 !py-3 w-24">Status</th>
                        <th class="border !px-2 !py-3 w-28">Date</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($tasks as $index => $task)
                        @php
                            $domain = optional($task->domainRow?->domain)->name ?? '-';
                            $keyword = $task->linkRow?->anchor_keyword ?? '-';
                            $url = $task->linkRow?->target_url ?? '-';

                            $isLive = $task->status === 'success';
                            $statusText = $isLive ? 'Live' : 'Not Live';
                            $statusClass = $isLive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="border !px-2 !py-3 text-center">
                                {{ $index + 1 }}
                            </td>

                            <td class="border !px-2 !py-3 font-medium overflow-hidden">
                                @if ($domain !== '-')
                                    <a href="https://{{ $domain }}" target="_blank" title="{{ $domain }}"
                                        class="block truncate text-sm text-blue-600 hover:underline">
                                        {{ $domain }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>

                            <td class="border !px-2 !py-3 overflow-hidden">
                                <span class="block truncate" title="{{ $domain }}">{{ $domain }}</span>
                            </td>

                            @php
                                $kwCell = \App\Support\ReportDisplay::keyword($keyword !== '-' ? $keyword : null);
                                $urlCell = \App\Support\ReportDisplay::url($url !== '-' ? $url : null);
                            @endphp
                            <td class="border !px-2 !py-3 overflow-hidden">
                                <span class="block truncate"
                                    @if ($kwCell['title'] !== '') title="{{ $kwCell['title'] }}" @endif>{{ $kwCell['display'] }}</span>
                            </td>

                            <td class="border !px-2 !py-3 overflow-hidden">
                                <span class="block truncate"
                                    @if ($urlCell['title'] !== '') title="{{ $urlCell['title'] }}" @endif>{{ $urlCell['display'] }}</span>
                            </td>

                            <td class="border !px-2 !py-3 text-center whitespace-nowrap">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-3 whitespace-nowrap">
                                {{ $task->created_at->format('d M Y') }}
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="7" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No sidebar campaign links found
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

    </div>

@endsection
