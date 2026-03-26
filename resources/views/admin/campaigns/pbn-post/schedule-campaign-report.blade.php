@extends('admin.layout.general')

@section('title', 'Scheduled Campaign Report')

@section('main-content')

    @php
        // schedule campaigns are NOT sticky
        $hasStickyPost = false;
    @endphp

    <div class="w-full flex flex-wrap justify-between items-start content-card">
        @csrf

        {{-- HEADER --}}
        <div class="w-full flex md:flex-row flex-col gap-3 items-center">
            <div class="flex sm:w-1/2 w-full justify-center md:justify-start">
                <h2 class="md:text-lg text-sm capitalize bg-[var(--primary-color)] text-white !p-3 rounded">
                    {{ $campaign->campaign_no }} Scheduled Campaign Report
                </h2>
            </div>

            <div class="flex sm:w-1/2 w-full gap-3 justify-center md:justify-end">
                <a href="{{ route('admin.schedule.campaign.report.export', [$campaign->campaign_no, $campaign->report_token]) }}"
                class="!px-5 !py-3 bg-green-600 text-white rounded hover:bg-green-700">
                Export Excel
            </a>
            </div>
        </div>

        {{-- STATS --}}
        <div class="w-full flex flex-col !my-6">
            <div class="flex flex-wrap gap-4">

                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-blue-500">
                    <div class="text-sm text-gray-500">Total Posts</div>
                    <div class="mt-1 text-3xl font-bold text-gray-800">{{ $stats->total }}</div>
                </div>

                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500">
                    <div class="text-sm text-gray-500">Success</div>
                    <div class="mt-1 text-3xl font-bold text-green-600">{{ $stats->success }}</div>
                </div>

                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500">
                    <div class="text-sm text-gray-500">Queued</div>
                    <div class="mt-1 text-3xl font-bold text-yellow-600">{{ $stats->queued }}</div>
                </div>

                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500">
                    <div class="text-sm text-gray-500">Failed</div>
                    <div class="mt-1 text-3xl font-bold text-red-600">{{ $stats->failed }}</div>
                </div>

            </div>
        </div>

        {{-- TABLE --}}
        <div class="overflow-x-auto w-full">
            <table class="display w-full border border-gray-200 text-sm whitespace-nowrap searchable-table">
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

                            if ($keywordType === 'json') {
                                $keywords = json_decode($ca->keyword ?? '[]', true) ?? [];
                                $urls = json_decode($ca->url ?? '[]', true) ?? [];
                            } else {
                                $keywords = [$ca->keyword ?? '-'];
                                $urls = [$ca->url ?? '-'];
                            }
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="border !px-2 !py-3 text-center">{{ $index + 1 }}</td>

                            <td class="border !px-2 !py-3">{{ $domain }}</td>

                            <td class="border !px-2 !py-3 break-all">
                                @if ($post->remote_url)
                                    <a href="{{ $post->remote_url }}" target="_blank" class="text-blue-600 hover:underline">
                                        {{ $post->remote_url }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>

                            @if ($keywordType === 'json')
                                @for ($i = 0; $i < $maxKeywordCount; $i++)
                                    <td class="border !px-2 !py-3">{{ $keywords[$i] ?? '-' }}</td>
                                    <td class="border !px-2 !py-3 break-all">{{ $urls[$i] ?? '-' }}</td>
                                @endfor
                            @else
                                <td class="border !px-2 !py-3">{{ $keywords[0] ?? '-' }}</td>
                                <td class="border !px-2 !py-3 break-all">{{ $urls[0] ?? '-' }}</td>
                            @endif
                            <td class="border !px-2 !py-3 text-center">
                                {{ optional($post->schedule_at)->format('d-M-Y') ?? '-' }}
                            </td>
                            <td class="border !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-3">
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
