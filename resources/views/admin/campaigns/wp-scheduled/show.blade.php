@extends('admin.layout.layout')

@section('title', 'WP Scheduled Campaign')

@section('main-content')
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2">
                <h2 class="page-title">{{ $campaign->campaign_no }}</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.wp.schedule.campaign.index') }}" class="breadcrumb-link">WP Scheduled</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item"><span>{{ $campaign->campaign_no }}</span></div>
                </div>
            </div>
            <div class="w-1/2 flex justify-end items-center gap-2 flex-wrap">
                <a href="{{ route('admin.wp.schedule.campaign.edit', $campaign->id) }}"
                    class="inline-flex items-center gap-1.5 !px-3 !py-2 rounded bg-yellow-500 text-white text-sm hover:bg-yellow-600"
                    title="Edit campaign (batch keyword/URL)">
                    <span class="material-symbols-outlined !text-base">edit</span>
                    Edit campaign
                </a>
                <form action="{{ route('admin.wp.schedule.campaign.destroy', $campaign->id) }}" method="POST" class="inline"
                    onsubmit="return confirm('Delete this entire campaign? All posts (including scheduled) will be removed from WordPress and the database. This cannot be undone.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 !px-3 !py-2 rounded bg-red-600 text-white text-sm hover:bg-red-700" title="Delete whole campaign">
                        <span class="material-symbols-outlined !text-base">delete</span>
                        Delete campaign
                    </button>
                </form>
                <form action="{{ route('admin.wp.schedule.campaign.purge.local', $campaign->id) }}" method="POST" class="inline"
                    onsubmit="return confirm('Remove this campaign from the dashboard only? Remote WordPress posts stay. You will not be able to edit this campaign here anymore.');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 !px-3 !py-2 rounded bg-orange-600 text-white text-sm hover:bg-orange-700" title="Remove from dashboard only">
                        <span class="material-symbols-outlined !text-base">database</span>
                        Remove locally
                    </button>
                </form>
                @if($campaign->posts()->where('status', 'queued')->exists())
                    <form action="{{ route('admin.wp.schedule.campaign.run', $campaign->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="!px-3 !py-2 rounded bg-green-600 text-white text-sm hover:bg-green-700">Run queue</button>
                    </form>
                @endif
                @if($campaign->posts()->whereNotNull('remote_id')->exists())
                    <form action="{{ route('admin.wp.schedule.campaign.sync', $campaign->id) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="!px-3 !py-2 rounded bg-blue-600 text-white text-sm hover:bg-blue-700">Sync status</button>
                    </form>
                @endif
                <a href="{{ route('admin.wp.schedule.campaign.index') }}" class="!px-3 !py-2 rounded bg-gray-200 text-sm">Back</a>
            </div>
        </div>
    </div>

    @if (session('cus__success') || session('cus__error'))
        <div class="!mt-2 !mb-3">
            @if (session('cus__success'))
                <div class="!p-4 text-sm rounded bg-green-100 text-green-700">{{ session('cus__success') }}</div>
            @endif
            @if (session('cus__error'))
                <div class="!p-4 text-sm rounded bg-red-100 text-red-700">{{ session('cus__error') }}</div>
            @endif
        </div>
    @endif

    <div class="content-card mt-3">
        <div class="!mb-4 flex flex-wrap gap-4">
            <span><strong>Date range:</strong> {{ $campaign->schedule_from_date?->format('d M Y') }} → {{ $campaign->schedule_to_date?->format('d M Y') }}</span>
            <span><strong>Total posts:</strong> {{ $campaign->total_targets }}</span>
            <span><strong>Completed:</strong> {{ $campaign->completed_targets }}</span>
            <span><strong>Failed:</strong> {{ $campaign->failed_targets }}</span>
            <span class="!px-2 !py-1 rounded text-xs font-semibold
                @if($campaign->status === 'completed') bg-green-100 text-green-700
                @elseif($campaign->status === 'failed') bg-red-100 text-red-700
                @else bg-gray-100 text-gray-600 @endif">{{ ucfirst($campaign->status) }}</span>
        </div>

        <div class="!mb-4 flex flex-wrap gap-2">
            @if($campaign->report_token)
                <a href="{{ route('admin.wp.schedule.campaign.report', ['campaign_no' => $campaign->campaign_no, 'token' => $campaign->report_token]) }}"
                    target="_blank"
                    class="!px-3 !py-2 rounded bg-blue-600 text-white text-sm hover:bg-blue-700">
                    View report page
                </a>
                <a href="javascript:void(0)"
                    data-report="{{ route('admin.wp.schedule.campaign.report', ['campaign_no' => $campaign->campaign_no, 'token' => $campaign->report_token]) }}"
                    class="copy-link !px-3 !py-2 rounded bg-yellow-500 text-white text-sm hover:bg-yellow-600">
                    Copy report page link
                </a>
            @endif
        </div>

        <h3 class="text-lg font-medium !mb-2">Posts (scheduled date + status)</h3>
        <div class="overflow-x-auto">
            <table class="w-full border border-gray-200 text-sm">
                <thead>
                    <tr class="bg-gray-700 text-white">
                        <th class="border !px-2 !py-2 text-left">Scheduled date</th>
                        <th class="border !px-2 !py-2 text-left">Domain</th>
                        <th class="border !px-2 !py-2 text-left">Article</th>
                        <th class="border !px-2 !py-2 text-left">Status</th>
                        <th class="border !px-2 !py-2 text-left">Display (report)</th>
                        <th class="border !px-2 !py-2 text-left">WP status</th>
                        <th class="border !px-2 !py-2 text-left">Remote ID</th>
                        <th class="border !px-2 !py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($posts as $p)
                        <tr class="hover:bg-gray-50">
                            <td class="border !px-2 !py-2">{{ $p->scheduled_date?->format('d M Y') }}</td>
                            <td class="border !px-2 !py-2">{{ $p->campaignDomain->domain->name ?? '-' }}</td>
                            <td class="border !px-2 !py-2">{{ Str::limit(optional($p->campaignArticle?->article)->name ?? $p->campaignArticle?->article_title_snapshot ?? $p->remote_title ?? '—', 40) }}</td>
                            <td class="border !px-2 !py-2">{{ ucfirst($p->status) }}</td>
                            <td class="border !px-2 !py-2">{{ $p->display_status }}</td>
                            <td class="border !px-2 !py-2">{{ $p->remote_status ?? '-' }}</td>
                            <td class="border !px-2 !py-2">{{ $p->remote_id ?? '-' }}</td>
                            <td class="border !px-2 !py-2">
                                <div class="flex  gap-1 justify-center items-center">
                                    @if($p->remote_url)
                                        <a href="{{ $p->remote_url }}" target="_blank"
                                            class="flex items-center justify-center rounded w-8 h-8 bg-green-500 text-white hover:bg-green-600"
                                            title="View post">
                                            <span class="material-symbols-outlined !text-lg">visibility</span>
                                        </a>
                                    @endif
                                    @if($p->remote_id)
                                        <a href="{{ route('admin.wp.schedule.campaign.edit.post', $p->id) }}"
                                            class="flex items-center justify-center rounded w-8 h-8 bg-yellow-500 text-white hover:bg-yellow-600"
                                            title="Edit post">
                                            <span class="material-symbols-outlined !text-lg">edit</span>
                                        </a>
                                    @endif
                                    @if($p->can_sync)
                                        <form action="{{ route('admin.wp.schedule.campaign.sync.post', $p->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="flex items-center justify-center rounded w-8 h-8 bg-blue-500 text-white hover:bg-blue-600 border-0 cursor-pointer" title="Sync status from WordPress">
                                                <span class="material-symbols-outlined !text-lg">sync</span>
                                            </button>
                                        </form>
                                    @endif
                                    @if($p->can_retry)
                                        <form action="{{ route('admin.wp.schedule.campaign.retry.post', $p->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="flex items-center justify-center rounded w-8 h-8 bg-orange-500 text-white hover:bg-orange-600 border-0 cursor-pointer" title="Retry (re-send to WordPress)">
                                                <span class="material-symbols-outlined !text-lg">refresh</span>
                                            </button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.wp.schedule.campaign.delete.post', $p->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('Delete this scheduled post? This cannot be undone.');">
                                        @csrf
                                        <button type="submit" class="flex items-center justify-center rounded w-8 h-8 bg-red-500 text-white hover:bg-red-600 border-0 cursor-pointer" title="Delete post">
                                            <span class="material-symbols-outlined !text-lg">delete</span>
                                        </button>
                                    </form>
                                </div>
                                @if(!$p->remote_url && !$p->remote_id && !$p->can_sync && !$p->can_retry)
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="!mt-2">{{ $posts->links() }}</div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/copy.js') }}"></script>
@endpush
