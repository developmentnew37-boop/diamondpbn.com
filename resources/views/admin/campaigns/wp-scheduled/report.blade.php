@extends('admin.layout.general')

@section('title', 'WP Scheduled Campaign Report')

@section('main-content')

    <div class="w-full flex flex-wrap justify-between items-start content-card">
        {{-- HEADER --}}
        <div class="w-full flex md:flex-row flex-col gap-3 items-center">
            <div class="flex sm:w-1/2 w-full justify-center md:justify-start">
                <h2 class="md:text-lg text-sm capitalize bg-[var(--primary-color)] text-white !p-3 rounded">
                    {{ $campaign->campaign_no }} WP Scheduled Campaign Report
                </h2>
            </div>
            <div class="flex sm:w-1/2 w-full gap-3 justify-center md:justify-end">
                <a href="{{ route('admin.wp.schedule.campaign.report.export', [$campaign->campaign_no, $campaign->report_token]) }}"
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
                    <div class="!mt-1 text-3xl font-bold text-gray-800">{{ $stats->total }}</div>
                </div>
                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-green-500">
                    <div class="text-sm text-gray-500">Live</div>
                    <div class="!mt-1 text-3xl font-bold text-green-600">{{ $stats->success }}</div>
                </div>
                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-yellow-500">
                    <div class="text-sm text-gray-500">Scheduled</div>
                    <div class="!mt-1 text-3xl font-bold text-yellow-600">{{ $stats->queued }}</div>
                </div>
                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-orange-500">
                    <div class="text-sm text-gray-500">Missed schedule</div>
                    <div class="!mt-1 text-3xl font-bold text-orange-600">{{ $stats->missed ?? 0 }}</div>
                </div>
                <div class="flex-1 min-w-[220px] bg-white rounded-xl shadow-sm !p-5 border-l-4 border-red-500">
                    <div class="text-sm text-gray-500">Failed</div>
                    <div class="!mt-1 text-3xl font-bold text-red-600">{{ $stats->failed }}</div>
                </div>
            </div>
        </div>

        {{-- TABLE --}}
        <div class="overflow-x-auto w-full">
            <table class="display w-full border border-gray-200 text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border !px-2 !py-3">Sno</th>
                        <th class="border !px-2 !py-3">Scheduled Date</th>
                        <th class="border !px-2 !py-3">Domain</th>
                        <th class="border !px-2 !py-3">Article</th>
                        <th class="border !px-2 !py-3">Status</th>
                        <th class="border !px-2 !py-3">Display (report)</th>
                        <th class="border !px-2 !py-3">WP status</th>
                        <th class="border !px-2 !py-3">Remote URL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($posts as $index => $post)
                        @php
                            $disp = $post->display_status;
                            $statusClass = match ($disp) {
                                'Live' => 'bg-green-100 text-green-700',
                                'Failed' => 'bg-red-100 text-red-700',
                                'Missed schedule' => 'bg-orange-100 text-orange-700',
                                default => 'bg-yellow-100 text-yellow-700',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="border !px-2 !py-3 text-center">{{ $index + 1 }}</td>
                            <td class="border !px-2 !py-3">{{ $post->scheduled_date?->format('d M Y') ?? '-' }}</td>
                            <td class="border !px-2 !py-3">{{ optional($post->campaignDomain?->domain)->name ?? '-' }}</td>
                            <td class="border !px-2 !py-3 max-w-[200px] truncate" title="{{ optional($post->campaignArticle?->article)->name }}">
                                {{ \Illuminate\Support\Str::limit(optional($post->campaignArticle?->article)->name ?? '-', 50) }}
                            </td>
                            <td class="border !px-2 !py-3 text-center">{{ ucfirst($post->status) }}</td>
                            <td class="border !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">{{ $disp }}</span>
                            </td>
                            <td class="border !px-2 !py-3 text-center">{{ $post->remote_status ?? '-' }}</td>
                            <td class="border !px-2 !py-3 break-all">
                                @if ($post->remote_url)
                                    <a href="{{ $post->remote_url }}" target="_blank" class="text-blue-600 hover:underline">
                                        {{ \Illuminate\Support\Str::limit($post->remote_url, 50) }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No posts found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
