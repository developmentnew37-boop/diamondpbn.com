@extends('admin.layout.general')

@section('title', 'Campaigns')

@section('main-content')


    @php
        $hasStickyPost = $campaign->is_sticky_campaign ? true : false;

    @endphp




    {{-- ******************* Ends here  ***************** --}}

    <div class="w-full flex flex-col gap-4 items-stretch content-card min-w-0 max-w-full px-3 py-4 sm:px-6 sm:py-6">
        @csrf
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4">
            <div class="w-full sm:flex-1 sm:min-w-0 flex justify-center sm:justify-start">
                <h2
                    class="md:text-lg text-sm capitalize bg-[var(--primary-color)] text-white text-center sm:text-left w-full sm:w-fit max-w-full !p-3 rounded break-words">
                    {{ $campaign->campaign_no }} Campaign Report
                </h2>
            </div>
            <div class="w-full sm:w-auto shrink-0 flex justify-center sm:justify-end">
                <a href="{{ route('admin.campaign.report.export', [$campaign->campaign_no, $campaign->report_token]) }}"
                    class="inline-flex !px-5 !py-3 bg-green-600 text-white rounded hover:bg-green-700 whitespace-nowrap w-full sm:w-auto justify-center">
                    Export Excel
                </a>

                {{-- <a href="{{ route('admin.campaign.report.export.csv', [$campaign->campaign_no, $campaign->report_token]) }}"
                    class="!px-4 !py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Export CSV
                </a> --}}
            </div>

        </div>
        {{-- xxxxxxxxxxxxxxxxxx campaigns button xxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}


        <div class="w-full flex flex-col !my-4 sm:!my-6 min-w-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6 w-full">
                {{-- Total --}}
                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-blue-500 min-w-0">
                    <div class="text-sm text-gray-500">Total Posts</div>
                    <div class="mt-1 text-3xl font-bold text-gray-800 tabular-nums">
                        {{ $stats->total }}
                    </div>
                </div>

                {{-- Success --}}
                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500 min-w-0">
                    <div class="text-sm text-gray-500">Success</div>
                    <div class="mt-1 text-3xl font-bold text-green-600 tabular-nums">
                        {{ $stats->success }}
                    </div>
                </div>

                {{-- Queued --}}
                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500 min-w-0">
                    <div class="text-sm text-gray-500">Queued</div>
                    <div class="mt-1 text-3xl font-bold text-yellow-600 tabular-nums">
                        {{ $stats->queued }}
                    </div>
                </div>

                {{-- Failed --}}
                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500 min-w-0">
                    <div class="text-sm text-gray-500">Failed</div>
                    <div class="mt-1 text-3xl font-bold text-red-600 tabular-nums">
                        {{ $stats->failed }}
                    </div>
                </div>
            </div>
        </div>


        {{-- table code here --}}

        {{-- checking post is sticky or not --}}


        <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            <table
                class="display w-full min-w-[880px] border border-gray-200 border-collapse text-sm searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">

                        <th class="border !px-2 !py-3 whitespace-nowrap">Sno</th>
                        {{-- <th class="border !px-2 !py-3">Campaign No</th> --}}
                        <th class="border !px-2 !py-3">Domain</th>
                        <th class="border !px-2 !py-3 min-w-[10rem] max-w-[14rem]">Post Url</th>

                        @if ($keywordType === 'json')
                            @for ($i = 1; $i <= $maxKeywordCount; $i++)
                                <th class="border !px-2 !py-3 min-w-[8rem] max-w-[12rem]">Keyword {{ $i }}</th>
                                <th class="border !px-2 !py-3 min-w-[10rem] max-w-[14rem]">URL {{ $i }}</th>
                            @endfor
                        @else
                            <th class="border !px-2 !py-3 min-w-[8rem] max-w-[12rem]">Keyword</th>
                            <th class="border !px-2 !py-3 min-w-[10rem] max-w-[14rem]">URL</th>
                        @endif
                        @if ($hasStickyPost)
                            <th class="border !px-2 !py-3 whitespace-nowrap">Post</th>
                        @endif
                        <th class="border !px-2 !py-3 whitespace-nowrap">Status</th>
                        <th class="border !px-2 !py-3 whitespace-nowrap">Date</th>

                    </tr>
                </thead>

                <tbody>
                    @forelse ($posts as $index => $post)

                        @php
                            $domain = optional($post->campaignDomain?->domain)->name ?? '-';

                            // Status (Live / Not Live)
                            $isLive = $post->status === 'success';
                            $statusText = $isLive ? 'Live' : 'Not Live';
                            $statusClass = $isLive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';

                            // Campaign article
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

                            {{-- Sno --}}
                            <td class="border !px-2 !py-3 text-center whitespace-nowrap">
                                {{ $index + 1 }}
                            </td>

                            {{-- Campaign No --}}
                            {{-- <td class="border !px-2 !py-3 font-medium">
                                {{ $campaign->campaign_no }}
                            </td> --}}

                            {{-- Domain --}}
                            <td class="border !px-2 !py-3 max-w-[12rem] align-top break-words" title="{{ $domain }}">
                                {{ $domain }}
                            </td>

                            @php $postUrl = \App\Support\ReportDisplay::externalLink($post->remote_url); @endphp
                            <td class="border !px-2 !py-3 max-w-[14rem] align-top break-words">
                                @if ($postUrl['href'] !== '')
                                    <a href="{{ $postUrl['href'] }}" target="_blank" rel="noopener"
                                        class="text-blue-600 hover:underline break-all"
                                        title="{{ $postUrl['title'] }}">{{ $postUrl['display'] }}</a>
                                @else
                                    -
                                @endif
                            </td>

                            {{-- Dynamic Keyword / URL columns --}}
                            @if ($keywordType === 'json')
                                @for ($i = 0; $i < $maxKeywordCount; $i++)
                                    @php
                                        $kwCell = \App\Support\ReportDisplay::keyword($keywords[$i] ?? null);
                                        $urlCell = \App\Support\ReportDisplay::url($urls[$i] ?? null);
                                    @endphp
                                    <td class="border !px-2 !py-3 max-w-[12rem] align-top break-words"
                                        @if ($kwCell['title'] !== '') title="{{ $kwCell['title'] }}" @endif>{{ $kwCell['display'] }}</td>
                                    <td class="border !px-2 !py-3 max-w-[14rem] align-top break-words"
                                        @if ($urlCell['title'] !== '') title="{{ $urlCell['title'] }}" @endif>{{ $urlCell['display'] }}</td>
                                @endfor
                            @else
                                @php
                                    $kwCell = \App\Support\ReportDisplay::keyword($keywords[0] ?? null);
                                    $urlCell = \App\Support\ReportDisplay::url($urls[0] ?? null);
                                @endphp
                                <td class="border !px-2 !py-3 max-w-[12rem] align-top break-words"
                                    @if ($kwCell['title'] !== '') title="{{ $kwCell['title'] }}" @endif>{{ $kwCell['display'] }}</td>
                                <td class="border !px-2 !py-3 max-w-[14rem] align-top break-words"
                                    @if ($urlCell['title'] !== '') title="{{ $urlCell['title'] }}" @endif>{{ $urlCell['display'] }}</td>
                            @endif

                            {{-- sticky thing --}}
                            @if ($hasStickyPost)
                                <td class="border !px-2 !py-3 text-center whitespace-nowrap">
                                    @if (!empty($campaign->is_sticky_campaign) && $campaign->is_sticky_campaign)
                                        <span
                                            class="!px-2 !py-1 rounded text-xs font-semibold bg-purple-100 text-purple-700">
                                            Sticky
                                        </span>
                                    @else
                                        {{-- intentionally empty --}}
                                    @endif
                                </td>
                            @endif



                            {{-- Status --}}
                            <td class="border !px-2 !py-3 text-center whitespace-nowrap">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            {{-- Date --}}
                            <td class="border !px-2 !py-3 whitespace-nowrap">
                                {{ $post->created_at->format('d M Y') }}
                            </td>

                        </tr>

                    @empty
                        <tr>
                            <td colspan="20" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No campaign posts found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="w-full !mt-2">
            {{-- {{ $campaignPost->links() }} --}}
        </div>
    </div>


@endsection




@push('scripts')
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    {{-- <script src="{{ asset('js/general.js') }}"></script>
    <script src="{{ asset('js/create-schedule-campaign.js') }}"></script>
    <script src="{{ asset('js/selectBox.js') }}"></script> --}}
@endpush
