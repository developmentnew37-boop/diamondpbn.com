@extends('admin.layout.general')

@section('title', 'Scheduled Campaign Report')

@section('main-content')

    @php
        $hasStickyPost = (bool) ($campaign->is_sticky_campaign ?? false);
    @endphp

    <div class="w-full flex flex-col gap-4 items-stretch content-card min-w-0 max-w-full px-3 py-4 sm:px-6 sm:py-6">
        @csrf

        {{-- HEADER --}}
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4 min-w-0">
            <div class="w-full sm:flex-1 sm:min-w-0 flex justify-center sm:justify-start">
                <h2 class="md:text-lg text-sm capitalize bg-[var(--primary-color)] text-white text-center sm:text-left w-full sm:w-fit max-w-full !p-3 rounded break-words">
                    {{ $campaign->campaign_no }} {{ $hasStickyPost ? 'Schedule Sticky Post' : 'Scheduled' }} Campaign Report
                </h2>
            </div>

            <div class="w-full sm:w-auto shrink-0 flex justify-center sm:justify-end">
                <a href="{{ route('admin.schedule.campaign.report.export', [$campaign->campaign_no, $campaign->report_token]) }}"
                class="inline-flex !px-5 !py-3 bg-green-600 text-white rounded hover:bg-green-700 whitespace-nowrap w-full sm:w-auto justify-center">
                Export Excel
            </a>
            </div>
        </div>

        {{-- STATS --}}
        <div class="w-full flex flex-col !my-4 sm:!my-6 min-w-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 w-full">

                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-blue-500 min-w-0">
                    <div class="text-sm text-gray-500">Total Posts</div>
                    <div class="mt-1 text-3xl font-bold text-gray-800 tabular-nums">{{ $stats->total }}</div>
                </div>

                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500 min-w-0">
                    <div class="text-sm text-gray-500">Success</div>
                    <div class="mt-1 text-3xl font-bold text-green-600 tabular-nums">{{ $stats->success }}</div>
                </div>

                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500 min-w-0">
                    <div class="text-sm text-gray-500">Queued</div>
                    <div class="mt-1 text-3xl font-bold text-yellow-600 tabular-nums">{{ $stats->queued }}</div>
                </div>

                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500 min-w-0">
                    <div class="text-sm text-gray-500">Failed</div>
                    <div class="mt-1 text-3xl font-bold text-red-600 tabular-nums">{{ $stats->failed }}</div>
                </div>

            </div>
        </div>

        {{-- TABLE --}}
        <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            <table class="report-table display w-full min-w-[980px] border border-gray-200 text-sm searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border !px-2 !py-3">Sno</th>
                        <th class="border !px-2 !py-3">Domain</th>
                        <th class="border !px-2 !py-3">BlogPost</th>

                        @if ($keywordType === 'json')
                            @for ($i = 1; $i <= $maxKeywordCount; $i++)
                                <th class="border !px-2 !py-3">Keyword {{ $i }}</th>
                                <th class="border !px-2 !py-3">URL {{ $i }}</th>
                            @endfor
                        @else
                            <th class="border !px-2 !py-3">Keyword</th>
                            <th class="border !px-2 !py-3">URL</th>
                        @endif
                        <th class="border !px-2 !py-3">Scheduled At</th>
                        <th class="border !px-2 !py-3">Status</th>
                        <th class="border !px-2 !py-3">Date</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($posts as $index => $post)

                        @php
                            $domain = optional($post->campaignDomain?->domain)->name ?? '-';

                            $isLive = $post->status === 'success';
                            $statusText = $isLive ? 'Live' : 'Not Live';
                            $statusClass = $isLive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';

                            $ca = $post->campaignArticle;

                            if ($ca && ($ca->keyword_type ?? 'single') === 'json') {
                                $keywords = json_decode($ca->keyword ?? '[]', true) ?? [];
                                $urls = json_decode($ca->url ?? '[]', true) ?? [];
                            } elseif ($ca) {
                                $keywords = [trim((string) ($ca->keyword ?? ''))];
                                $urls = [trim((string) ($ca->url ?? ''))];
                            } else {
                                $keywords = [];
                                $urls = [];
                            }
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="border !px-2 !py-3 text-center">{{ $index + 1 }}</td>

                            <td class="border !px-2 !py-3 report-td-domain">
                                @include('partials.report-cell-text', ['display' => $domain, 'title' => $domain !== '-' ? $domain : ''])
                            </td>

                            @php $blogLink = \App\Support\ReportDisplay::externalLink($post->remote_url); @endphp
                            <td class="border !px-2 !py-3 report-td-truncate report-td-blog">
                                @if ($blogLink['href'] !== '')
                                    <a href="{{ $blogLink['href'] }}" target="_blank" rel="noopener" class="report-clip"
                                        title="{{ $blogLink['title'] }}">{{ $blogLink['display'] }}</a>
                                @else
                                    -
                                @endif
                            </td>

                            @if ($keywordType === 'json')
                                @for ($i = 0; $i < $maxKeywordCount; $i++)
                                    @php
                                        $kwCell = \App\Support\ReportDisplay::keyword($keywords[$i] ?? null);
                                        $urlCell = \App\Support\ReportDisplay::url($urls[$i] ?? null);
                                    @endphp
                                    <td class="border !px-2 !py-3 report-td-truncate">
                                        @include('partials.report-cell-text', ['display' => $kwCell['display'], 'title' => $kwCell['title']])
                                    </td>
                                    <td class="border !px-2 !py-3 report-td-truncate">
                                        @include('partials.report-cell-text', ['display' => $urlCell['display'], 'title' => $urlCell['title']])
                                    </td>
                                @endfor
                            @else
                                @php
                                    $kwCell = \App\Support\ReportDisplay::keyword($keywords[0] ?? null);
                                    $urlCell = \App\Support\ReportDisplay::url($urls[0] ?? null);
                                @endphp
                                <td class="border !px-2 !py-3 report-td-truncate">
                                    @include('partials.report-cell-text', ['display' => $kwCell['display'], 'title' => $kwCell['title']])
                                </td>
                                <td class="border !px-2 !py-3 report-td-truncate">
                                    @include('partials.report-cell-text', ['display' => $urlCell['display'], 'title' => $urlCell['title']])
                                </td>
                            @endif
                            <td class="border !px-2 !py-3 text-center report-td-nowrap">
                                {{ optional($post->schedule_at)->format('d-M-Y') ?? '-' }}
                            </td>
                            <td class="border !px-2 !py-3 text-center report-td-nowrap">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-3 report-td-nowrap">
                                {{ optional($post->schedule_at)->format('d M Y') ?? '-' }}
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="20" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No scheduled campaign posts found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
