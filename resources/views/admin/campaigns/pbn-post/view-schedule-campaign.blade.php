@extends('admin.layout.layout')

@section('title', 'Scheduled Campaign')

@section('main-content')

{{-- ===================== HEADER ===================== --}}
<div class="page-header">
    <div class="w-full flex flex-wrap items-center">
        <div class="w-1/2 flex flex-col gap-2">
            <h2 class="page-title">Dashboards</h2>
            <div class="breadcrumb">
                <div class="breadcrumb-item">
                    <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                    <span>›</span>
                </div>
                <div class="breadcrumb-item">
                    <a href="{{ route('admin.schedule.campaign.index') }}" class="breadcrumb-link">
                        Scheduled Campaigns
                    </a>
                </div>
            </div>
        </div>

        <div class="w-1/2 flex justify-end items-center gap-2">
            <a href="{{ route('admin.schedule.campaign.edit', $campaign->id) }}"
               class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-[var(--primary-color)] text-white hover:opacity-90 text-sm">
                Edit campaign
            </a>
            <form action="{{ route('admin.schedule.campaign.destroy', $campaign->id) }}" method="post" class="inline"
                  onsubmit="return confirm('Delete this campaign? All posts will be removed from the database and from the remote site.');">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-red-600 text-white hover:bg-red-700 text-sm">
                    Delete campaign
                </button>
            </form>
            <a href="javascript:void(0)" onclick="history.back()"
               class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">
                <span class="material-symbols-outlined">arrow_back</span>
                Back
            </a>
        </div>
    </div>
</div>

{{-- ===================== ALERTS ===================== --}}
@if (session('cus__success') || session('cus__error'))
    <div class="!mt-3">
        @if (session('cus__success'))
            <div class="!p-3 rounded bg-green-100 text-green-700">
                {{ session('cus__success') }}
            </div>
        @endif
        @if (session('cus__error'))
            <div class="!p-3 rounded bg-red-100 text-red-700">
                {{ session('cus__error') }}
            </div>
        @endif
    </div>
@endif

{{-- ===================== CAMPAIGN SUMMARY ===================== --}}
<div class="content-card !mt-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">

    <div class="flex flex-col gap-2">
        <div class="text-gray-500">Campaign No</div>
        <div class="font-semibold">{{ $campaign->campaign_no }}</div>
    </div>

    <div class="flex flex-col gap-2">
        <div class="text-gray-500">Schedule Range</div>
        <div class="font-semibold">
            {{ $campaign->schedule_from_date?->format('d M Y') ?? '-' }}
            →
            {{ $campaign->schedule_to_date?->format('d M Y') ?? '-' }}
        </div>
    </div>

    <div class="flex flex-col gap-2 justify-start">
        <div class="text-gray-500">Status</div>
        <span class="!px-2 !py-1 rounded text-xs font-semibold w-fit
            {{ $campaign->status === 'completed' ? 'bg-green-100 text-green-700' :
               ($campaign->status === 'running' ? 'bg-yellow-100 text-yellow-700' :
               ($campaign->status === 'failed' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700')) }}">
            {{ ucfirst($campaign->status) }}
        </span>
    </div>

    <div class="flex flex-col gap-2 ">
        <div class="text-gray-500">Progress</div>
        <div class="font-semibold">
            {{ $campaign->completed_targets }} /
            {{ $campaign->total_targets }}
        </div>
    </div>

</div>

{{-- ===================== POSTS TABLE ===================== --}}
<div class="content-card !mt-4">

    <h2 class="text-lg !mb-4 bg-[var(--primary-color)] text-white w-fit !px-4 !py-2 rounded">
        Scheduled Posts
    </h2>

    <div class="overflow-x-auto">
        <table class="display w-full border border-gray-200 text-sm whitespace-nowrap searchable-table">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="!px-2 !py-3 border border-gray-200">#</th>
                    <th class="!px-2 !py-3 border border-gray-200">Schedule At</th>
                    <th class="!px-2 !py-3 border border-gray-200">Domain</th>
                    <th class="!px-2 !py-3 border border-gray-200">Article</th>
                    <th class="!px-2 !py-3 border border-gray-200">Type</th>
                    <th class="!px-2 !py-3 border border-gray-200">Remote ID</th>
                    <th class="!px-2 !py-3 border border-gray-200">Remote URL</th>
                    <th class="!px-2 !py-3 border border-gray-200">Attempts</th>
                    <th class="!px-2 !py-3 border border-gray-200">Last Error</th>
                    <th class="!px-2 !py-3 border border-gray-200">Next Retry</th>
                    <th class="!px-2 !py-3 border border-gray-200">Status</th>
                    <th class="!px-2 !py-3 border border-gray-200">Created</th>
                    <th class="!px-2 !py-3 border border-gray-200">Actions</th>
                </tr>
            </thead>

            <tbody>
            @forelse ($campaignPost as $index => $post)
                <tr class="hover:bg-gray-50">
                    <td class="!px-2 !py-2 text-center border border-gray-300">
                        {{ $index + 1 + $offset }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300">
                        {{ optional($post->schedule_at)?->format('d M Y H:i') ?? '-' }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300">
                        {{ optional($post->campaignDomain?->domain)->name ?? '-' }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300 max-w-[220px] truncate"
                        title="{{ optional($post->campaignArticle?->article)->name }}">
                        {{ \Illuminate\Support\Str::limit(optional($post->campaignArticle?->article)->name ?? '-', 70) }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300">
                        {{ $post->is_sticky ? 'Sticky' : 'Post' }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300">
                        {{ $post->remote_id ?? '-' }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300 text-center">
                        @if ($post->remote_url)
                            <a href="{{ $post->remote_url }}" target="_blank"
                               class="bg-yellow-500 text-white !px-2 !py-1 rounded">
                                view
                            </a>
                        @else
                            -
                        @endif
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300 text-center">
                        {{ $post->attempt_count }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300 max-w-[220px] truncate"
                        title="{{ $post->last_error }}">
                        {{ $post->last_error ?? '-' }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300">
                        {{ optional($post->next_retry_at)?->format('d M Y H:i') ?? '-' }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300 text-center">
                        @php
                            $map = [
                                'queued' => 'bg-gray-100 text-gray-700',
                                'publishing' => 'bg-yellow-100 text-yellow-700',
                                'success' => 'bg-green-100 text-green-700',
                                'failed' => 'bg-red-100 text-red-700',
                            ];
                        @endphp
                        <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $map[$post->status] ?? 'bg-gray-100' }}">
                            {{ ucfirst($post->status) }}
                        </span>
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300">
                        {{ $post->created_at?->format('d M Y H:i') }}
                    </td>

                    <td class="!px-2 !py-2 border border-gray-300">
                        <div class="flex  gap-1 justify-center items-center">
                            @if ($post->remote_url)
                                <a href="{{ $post->remote_url }}" target="_blank"
                                   class="bg-green-500 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-green-600"
                                   title="View on site">
                                    <span class="material-symbols-outlined text-white !text-sm">visibility</span>
                                </a>
                            @endif
                            @if ($post->status === 'success' && $post->remote_id)
                                <a href="{{ route('admin.schedule.campaign.edit.post', $post->id) }}"
                                   class="bg-yellow-500 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-amber-600"
                                   title="Edit post on remote">
                                    <span class="material-symbols-outlined text-white !text-sm">edit</span>
                                </a>
                            @endif
                            @if (in_array($post->status, ['queued', 'failed', 'publishing']))
                                <form action="{{ route('admin.schedule.campaign.retry.post', $post->id) }}" method="post" class="inline">
                                    @csrf
                                    <button type="submit" class="bg-blue-500 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-blue-600 border-0 cursor-pointer"
                                        title="Retry post">
                                        <span class="material-symbols-outlined text-white !text-sm">replay</span>
                                    </button>
                                </form>
                            @endif
                            <form action="{{ route('admin.schedule.campaign.delete.post', $post->id) }}" method="post" class="inline"
                                onsubmit="return confirm('Delete this post from the campaign and from the remote site?');">
                                @csrf
                                <button type="submit" class="bg-red-500 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-red-600 border-0 cursor-pointer"
                                    title="Delete post">
                                    <span class="material-symbols-outlined text-white !text-sm">delete</span>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center !py-4 text-gray-500 bg-gray-100">
                        No scheduled posts found
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $campaignPost->links() }}
    </div>

</div>

@endsection

@push('scripts')

 <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>

@endpush
