@extends('admin.layout.general')

@section('title', 'Sidebar Campaign Report')

@section('main-content')

    <div class="w-full content-card">

        {{-- Header --}}
        <div class="w-full flex flex-col sm:flex-row items-center sm:justify-between gap-3 !mb-6">

            <h2
                class="text-sm sm:text-base md:text-lg capitalize
                   bg-[var(--primary-color)] text-white
                   !px-4 !py-2 rounded">
                {{ $campaign->campaign_no }} Sidebar Campaign Report
            </h2>

            <a href="{{ route('admin.sidebar.campaign.report.export', [
                'campaign_no' => $campaign->campaign_no,
                'token' => $campaign->report_token,
            ]) }}"
                class="!px-5 !py-3 bg-green-600 text-white rounded hover:bg-green-700">
                Export Excel
            </a>
        </div>

        {{-- Stats --}}
        <div class="flex flex-wrap gap-4 !mb-6">

            <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-blue-500">
                <div class="text-sm text-gray-500">Total Links</div>
                <div class="text-3xl font-bold text-gray-800">{{ $stats->total }}</div>
            </div>

            <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500">
                <div class="text-sm text-gray-500">Success</div>
                <div class="text-3xl font-bold text-green-600">{{ $stats->success }}</div>
            </div>

            <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500">
                <div class="text-sm text-gray-500">Queued</div>
                <div class="text-3xl font-bold text-yellow-600">{{ $stats->queued }}</div>
            </div>

            <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500">
                <div class="text-sm text-gray-500">Failed</div>
                <div class="text-3xl font-bold text-red-600">{{ $stats->failed }}</div>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="display w-full border border-gray-200 text-sm whitespace-nowrap">

                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border !px-2 !py-3">S.No</th>
                        <th class="border !px-2 !py-3">Live Link</th>
                        <th class="border !px-2 !py-3">Domain</th>
                        <th class="border !px-2 !py-3">Keyword</th>
                        <th class="border !px-2 !py-3">Target URL</th>
                        <th class="border !px-2 !py-3">Status</th>
                        <th class="border !px-2 !py-3">Date</th>
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

                            <td class="border !px-2 !py-3 font-medium">
                                <a href="https://{{ $domain }}" class="text-sm text-blue-600">{{ $domain }}</a>
                            </td>

                            <td class="border !px-2 !py-3">
                                {{ $domain }}
                            </td>



                            @php
                                $kwCell = \App\Support\ReportDisplay::keyword($keyword !== '-' ? $keyword : null);
                                $urlCell = \App\Support\ReportDisplay::url($url !== '-' ? $url : null);
                            @endphp
                            <td class="border !px-2 !py-3 max-w-[12rem] align-top"
                                @if ($kwCell['title'] !== '') title="{{ $kwCell['title'] }}" @endif>{{ $kwCell['display'] }}</td>

                            <td class="border !px-2 !py-3 max-w-[18rem] align-top"
                                @if ($urlCell['title'] !== '') title="{{ $urlCell['title'] }}" @endif>{{ $urlCell['display'] }}</td>

                            <td class="border !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-3">
                                {{ $task->created_at->format('d M Y') }}
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="8" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No sidebar campaign links found
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

    </div>

@endsection
