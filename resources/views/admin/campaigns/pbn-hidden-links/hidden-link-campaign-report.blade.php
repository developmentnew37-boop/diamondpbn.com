@extends('admin.layout.general')

@section('title', 'Hidden Links Campaign Report')

@section('main-content')

<div class="w-full px-3 sm:px-4 lg:px-8 xl:px-10 !py-3">
<div class="w-full content-card !p-3 sm:!p-5 lg:!p-6 min-w-0 max-w-full">

    {{-- Header --}}
    <div class="w-full flex flex-col sm:flex-row items-center sm:justify-between gap-3 !mb-6">

        <h2 class="text-sm sm:text-base md:text-lg capitalize
                   bg-[var(--primary-color)] text-white
                   !px-4 !py-2 rounded">
            {{ $campaign->campaign_no }} Hidden Links Campaign Report
        </h2>

        <a href="{{ route('admin.hidden.link.campaign.report.export', [
            'campaign_no' => $campaign->campaign_no,
            'token' => $campaign->report_token,
        ]) }}"
           class="!px-5 !py-3 bg-green-600 text-white rounded hover:bg-green-700">
            Export Excel
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 !mb-6 w-full">

        <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-blue-500 min-w-0">
            <div class="text-sm text-gray-500">Total Links</div>
            <div class="text-3xl font-bold text-gray-800">{{ $stats->total }}</div>
        </div>

        <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500 min-w-0">
            <div class="text-sm text-gray-500">Success</div>
            <div class="text-3xl font-bold text-green-600">{{ $stats->success }}</div>
        </div>

        <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500 min-w-0">
            <div class="text-sm text-gray-500">Queued</div>
            <div class="text-3xl font-bold text-yellow-600">{{ $stats->queued }}</div>
        </div>

        <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500 min-w-0">
            <div class="text-sm text-gray-500">Failed</div>
            <div class="text-3xl font-bold text-red-600">{{ $stats->failed }}</div>
        </div>
    </div>

    {{-- Table --}}
    <div class="overflow-x-auto w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
        <table class="display w-full min-w-[900px] border border-gray-200 text-sm">

            <thead>
                <tr class="bg-gray-800 text-white">
                    <th class="border !px-2 !py-3 whitespace-nowrap">S.No</th>
                    <th class="border !px-2 !py-3">Live Link</th>
                    <th class="border !px-2 !py-3">Domain</th>
                    <th class="border !px-2 !py-3">Anchor</th>
                    <th class="border !px-2 !py-3">Target URL</th>
                    <th class="border !px-2 !py-3 whitespace-nowrap">Status</th>
                    <th class="border !px-2 !py-3 whitespace-nowrap">Date</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($tasks as $index => $task)
                    @php
                        $domain = $task->domainRow?->domain?->name ?? '-';
                        $anchor = $task->linkRow?->anchor_keyword ?? '-';
                        $url    = $task->linkRow?->target_url ?? '-';

                        $isLive = $task->status === 'success';
                        $statusText  = $isLive ? 'Live' : 'Not Live';
                        $statusClass = $isLive
                            ? 'bg-green-100 text-green-700'
                            : 'bg-red-100 text-red-700';
                    @endphp

                    <tr class="hover:bg-gray-50">
                        <td class="border !px-2 !py-3 text-center whitespace-nowrap">
                            {{ $index + 1 }}
                        </td>

                        <td class="border !px-2 !py-3 font-medium">
                           <a href="https://{{ $domain }}" target="_blank" class="text-blue-600 hover:underline break-all">
                                {{ $domain ?? '-' }}
                            </a>
                        </td>

                        <td class="border !px-2 !py-3 max-w-[12rem] break-words">
                            {{ $domain }}
                        </td>

                        @php
                            $anchorCell = \App\Support\ReportDisplay::keyword($anchor !== '-' ? $anchor : null);
                            $urlCell = \App\Support\ReportDisplay::url($url !== '-' ? $url : null);
                        @endphp
                        <td class="border !px-2 !py-3 max-w-[12rem] align-top break-words"
                            @if ($anchorCell['title'] !== '') title="{{ $anchorCell['title'] }}" @endif>{{ $anchorCell['display'] }}</td>

                        <td class="border !px-2 !py-3 max-w-[16rem] align-top break-words"
                            @if ($urlCell['title'] !== '') title="{{ $urlCell['title'] }}" @endif>{{ $urlCell['display'] }}</td>

                        <td class="border !px-2 !py-3 text-center whitespace-nowrap">
                            <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                {{ $statusText }}
                            </span>
                        </td>

                        <td class="border !px-2 !py-3 whitespace-nowrap">
                            {{ $task->created_at?->format('d-M-Y') }}
                        </td>
                    </tr>

                @empty
                    <tr>
                        <td colspan="7" class="text-center !py-4 text-gray-500 bg-gray-100">
                            No hidden links campaign tasks found
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>
    </div>

</div>
</div>

@endsection
