@extends('admin.layout.general')

@section('title', $bill->campaign_no.' Campaign Report')

@section('main-content')
    @php
        $total = count($lines);
        $reportedAt = $bill->created_at?->format('d M Y') ?? '-';
    @endphp

    <div class="w-full flex flex-col gap-4 items-stretch content-card min-w-0 max-w-full px-3 py-4 sm:px-6 sm:py-6">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4">
            <div class="w-full sm:flex-1 sm:min-w-0 flex justify-center sm:justify-start">
                <h2 class="md:text-lg text-sm capitalize bg-[var(--primary-color)] text-white text-center sm:text-left w-full sm:w-fit max-w-full !p-3 rounded break-words">
                    {{ $bill->campaign_no }} Campaign Report
                </h2>
            </div>
            <div class="w-full sm:w-auto shrink-0 flex justify-center sm:justify-end">
                <a href="{{ route('admin.local-client.manual-billing.report.export', [$bill->id, $bill->report_token]) }}"
                    class="inline-flex !px-5 !py-3 bg-green-600 text-white rounded hover:bg-green-700 whitespace-nowrap w-full sm:w-auto justify-center">
                    Export Excel
                </a>
            </div>
        </div>

        <div class="w-full flex flex-col !my-4 sm:!my-6 min-w-0">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6 w-full">
                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-blue-500 min-w-0">
                    <div class="text-sm text-gray-500">Total Posts</div>
                    <div class="mt-1 text-3xl font-bold text-gray-800 tabular-nums">{{ $total }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500 min-w-0">
                    <div class="text-sm text-gray-500">Success</div>
                    <div class="mt-1 text-3xl font-bold text-green-600 tabular-nums">{{ $total }}</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500 min-w-0">
                    <div class="text-sm text-gray-500">Queued</div>
                    <div class="mt-1 text-3xl font-bold text-yellow-600 tabular-nums">0</div>
                </div>
                <div class="bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500 min-w-0">
                    <div class="text-sm text-gray-500">Failed</div>
                    <div class="mt-1 text-3xl font-bold text-red-600 tabular-nums">0</div>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            <table class="display w-full min-w-[880px] border border-gray-200 border-collapse text-sm">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border !px-2 !py-3 whitespace-nowrap">Sno</th>
                        <th class="border !px-2 !py-3">Domain</th>
                        <th class="border !px-2 !py-3 min-w-[10rem] max-w-[14rem]">Post Url</th>
                        <th class="border !px-2 !py-3 min-w-[8rem] max-w-[12rem]">Keyword</th>
                        <th class="border !px-2 !py-3 min-w-[10rem] max-w-[14rem]">URL</th>
                        <th class="border !px-2 !py-3 whitespace-nowrap">Status</th>
                        <th class="border !px-2 !py-3 whitespace-nowrap">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lines as $index => $line)
                        @php
                            $postUrl = \App\Support\ReportDisplay::externalLink($line['live'] !== '' ? $line['live'] : null);
                            $keyword = \App\Support\ReportDisplay::keyword($line['keyword'] !== '' ? $line['keyword'] : null);
                            $targetUrl = \App\Support\ReportDisplay::url($line['url'] !== '' ? $line['url'] : null);
                            $postHref = preg_match('/^https?:\/\//i', $postUrl['href']) === 1 ? $postUrl['href'] : '';
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="border !px-2 !py-3 text-center whitespace-nowrap">{{ $index + 1 }}</td>
                            <td class="border !px-2 !py-3 max-w-[12rem] align-top break-words" @if ($line['domain'] !== '') title="{{ $line['domain'] }}" @endif>
                                {{ $line['domain'] !== '' ? $line['domain'] : '-' }}
                            </td>
                            <td class="border !px-2 !py-3 max-w-[14rem] align-top">
                                @if ($postHref !== '')
                                    <a href="{{ $postHref }}" target="_blank" rel="noopener"
                                        class="block w-full overflow-hidden text-ellipsis whitespace-nowrap text-blue-600 hover:underline"
                                        title="{{ $postUrl['title'] }}">{{ $postUrl['display'] }}</a>
                                @else
                                    <span class="block w-full overflow-hidden text-ellipsis whitespace-nowrap" @if ($postUrl['title'] !== '') title="{{ $postUrl['title'] }}" @endif>{{ $postUrl['display'] }}</span>
                                @endif
                            </td>
                            <td class="border !px-2 !py-3 max-w-[12rem] align-top break-words" @if ($keyword['title'] !== '') title="{{ $keyword['title'] }}" @endif>{{ $keyword['display'] }}</td>
                            <td class="border !px-2 !py-3 max-w-[14rem] align-top">
                                @if (preg_match('/^https?:\/\//i', $targetUrl['title']) === 1)
                                    <a href="{{ $targetUrl['title'] }}" target="_blank" rel="noopener"
                                        class="block w-full overflow-hidden text-ellipsis whitespace-nowrap text-blue-600 hover:underline"
                                        title="{{ $targetUrl['title'] }}">{{ $targetUrl['display'] }}</a>
                                @else
                                    <span class="block w-full break-words" @if ($targetUrl['title'] !== '') title="{{ $targetUrl['title'] }}" @endif>{{ $targetUrl['display'] }}</span>
                                @endif
                            </td>
                            <td class="border !px-2 !py-3 text-center whitespace-nowrap">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold bg-green-100 text-green-700">Live</span>
                            </td>
                            <td class="border !px-2 !py-3 whitespace-nowrap">{{ $reportedAt }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No campaign posts found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
