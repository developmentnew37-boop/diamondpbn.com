@extends('admin.layout.general')

@section('title', 'Campaigns')

@section('main-content')


    @php
        $hasStickyPost = $campaign->is_sticky_campaign ? true : false;

    @endphp




    {{-- ******************* Ends here  ***************** --}}

    <div class="w-full flex flex-wrap justify-between items-start content-card">
        @csrf
        <div class="w-full flex md:flex-row md:flex-wrap flex-col md:gap-0 gap-3 items-center ">
            <div class="flex sm:w-1/2 w-full items-center md:!justify-start justify-center">
                <h2
                    class="md:text-lg text-sm capitalize md:!mb-4 bg-[var(--primary-color)] text-white text-center w-fit !p-3 rounded">
                    {{ $campaign->campaign_no }} Campaign Report
                </h2>
            </div>
            <div class="flex sm:w-1/2 w-full gap-3 items-center md:justify-end justify-center">
                <a href="{{ route('admin.campaign.report.export', [$campaign->campaign_no, $campaign->report_token]) }}"
                    class="!px-5 !py-3 bg-green-600 text-white rounded hover:bg-green-700">
                    Export Excel
                </a>

                {{-- <a href="{{ route('admin.campaign.report.export.csv', [$campaign->campaign_no, $campaign->report_token]) }}"
                    class="!px-4 !py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Export CSV
                </a> --}}
            </div>

        </div>
        {{-- xxxxxxxxxxxxxxxxxx campaigns button xxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}


        <div class="w-full flex flex-col !my-6">
            <div class="flex flex-wrap gap-4 mb-6">
                {{-- Total --}}
                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-blue-500">
                    <div class="text-sm text-gray-500">Total Posts</div>
                    <div class="mt-1 text-3xl font-bold text-gray-800">
                        {{ $stats->total }}
                    </div>
                </div>

                {{-- Success --}}
                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500">
                    <div class="text-sm text-gray-500">Success</div>
                    <div class="mt-1 text-3xl font-bold text-green-600">
                        {{ $stats->success }}
                    </div>
                </div>

                {{-- Queued --}}
                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500">
                    <div class="text-sm text-gray-500">Queued</div>
                    <div class="mt-1 text-3xl font-bold text-yellow-600">
                        {{ $stats->queued }}
                    </div>
                </div>

                {{-- Failed --}}
                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500">
                    <div class="text-sm text-gray-500">Failed</div>
                    <div class="mt-1 text-3xl font-bold text-red-600">
                        {{ $stats->failed }}
                    </div>
                </div>
            </div>
        </div>


        {{-- table code here --}}

        {{-- checking post is sticky or not --}}


        <div class="overflow-x-auto !mt-3 w-full">
            <table class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">

                        <th class="border !px-2 !py-3">Sno</th>
                        {{-- <th class="border !px-2 !py-3">Campaign No</th> --}}
                        <th class="border !px-2 !py-3">Domain</th>
                        <th class="border !px-2 !py-3">Post Url</th>

                        @if ($keywordType === 'json')
                            @for ($i = 1; $i <= $maxKeywordCount; $i++)
                                <th class="border !px-2 !py-3">Keyword {{ $i }}</th>
                                <th class="border !px-2 !py-3">URL {{ $i }}</th>
                            @endfor
                        @else
                            <th class="border !px-2 !py-3">Keyword</th>
                            <th class="border !px-2 !py-3">URL</th>
                        @endif
                        @if ($hasStickyPost)
                            <th class="border !px-2 !py-3">Post</th>
                        @endif
                        <th class="border !px-2 !py-3">Status</th>
                        <th class="border !px-2 !py-3">Date</th>

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
                            <td class="border !px-2 !py-3 text-center">
                                {{ $index + 1 }}
                            </td>

                            {{-- Campaign No --}}
                            {{-- <td class="border !px-2 !py-3 font-medium">
                                {{ $campaign->campaign_no }}
                            </td> --}}

                            {{-- Domain --}}
                            <td class="border !px-2 !py-3">
                                {{ $domain }}
                            </td>

                            @php $postUrl = \App\Support\ReportDisplay::externalLink($post->remote_url); @endphp
                            <td class="border !px-2 !py-3 max-w-[18rem] align-top">
                                @if ($postUrl['href'] !== '')
                                    <a href="{{ $postUrl['href'] }}" target="_blank" rel="noopener"
                                        class="text-blue-600 hover:underline"
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
                                    <td class="border !px-2 !py-3 max-w-[12rem] align-top"
                                        @if ($kwCell['title'] !== '') title="{{ $kwCell['title'] }}" @endif>{{ $kwCell['display'] }}</td>
                                    <td class="border !px-2 !py-3 max-w-[18rem] align-top"
                                        @if ($urlCell['title'] !== '') title="{{ $urlCell['title'] }}" @endif>{{ $urlCell['display'] }}</td>
                                @endfor
                            @else
                                @php
                                    $kwCell = \App\Support\ReportDisplay::keyword($keywords[0] ?? null);
                                    $urlCell = \App\Support\ReportDisplay::url($urls[0] ?? null);
                                @endphp
                                <td class="border !px-2 !py-3 max-w-[12rem] align-top"
                                    @if ($kwCell['title'] !== '') title="{{ $kwCell['title'] }}" @endif>{{ $kwCell['display'] }}</td>
                                <td class="border !px-2 !py-3 max-w-[18rem] align-top"
                                    @if ($urlCell['title'] !== '') title="{{ $urlCell['title'] }}" @endif>{{ $urlCell['display'] }}</td>
                            @endif

                            {{-- sticky thing --}}
                            @if ($hasStickyPost)
                                <td class="border !px-2 !py-3 text-center">
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
                            <td class="border !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>
                            </td>

                            {{-- Date --}}
                            <td class="border !px-2 !py-3">
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
