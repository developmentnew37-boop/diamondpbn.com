@extends('admin.layout.layout')

@section('title', 'WP Scheduled Campaigns')

@push('style')
    @include('admin.campaigns.partials.campaign-list-table-styles')
@endpush

@section('main-content')
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="w-full md:flex-1 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.wp.schedule.campaign.index') }}" class="breadcrumb-link">WP Scheduled</a>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-auto flex flex-wrap justify-start md:justify-end items-center md:shrink-0">
                @if (Auth::guard('admin')->user()->canCreateCampaigns())
                <a href="{{ route('admin.wp.schedule.campaign.create') }}"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-full sm:w-fit justify-center bg-[var(--primary-color)] whitespace-nowrap text-white rounded hover:bg-[var(--primary-color)]/70 transition-all">
                    Create WP Scheduled Campaign
                </a>
                @endif
            </div>
        </div>
    </div>

    @if (session('cus__success') || session('cus__error'))
        <div class="w-full flex flex-col gap-2 !mt-2">
            @if (session('cus__success'))
                <div class="!p-4 text-sm rounded bg-green-100 text-green-700">{{ session('cus__success') }}</div>
            @endif
            @if (session('cus__error'))
                <div class="!p-4 text-sm rounded bg-red-100 text-red-700">{{ session('cus__error') }}</div>
            @endif
        </div>
    @endif

    <div class="content-card w-full !mt-3">
        <div class="flex flex-wrap items-center gap-3 !mb-3">
            @include('admin.campaigns.partials.campaign-owner-filter')
            <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center gap-2 flex-1 min-w-[200px]">
                @foreach (request()->except('search') as $key => $value)
                    @continue(is_array($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search campaign no"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm rounded w-full !max-w-xs">
            </form>
        </div>

        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
            WordPress Scheduled Campaigns
        </h2>
        <form id="wp-sched-bulk-purge-local-form" action="{{ route('admin.wp.schedule.campaign.bulk.purge.local') }}" method="POST" class="hidden">@csrf</form>
        <div class="w-full flex flex-wrap items-center gap-2 !mb-2">
            <button type="button" id="wp-sched-bulk-purge-local-btn"
                class="!px-3 !py-2 rounded bg-orange-600 text-white text-sm hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Bulk remove locally only
            </button>
            <span class="text-sm text-gray-500">Select rows with checkboxes; remote WordPress posts are not changed.</span>
        </div>

        <div class="overflow-x-auto w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            <table class="campaign-list-table display w-full min-w-[1100px] border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border border-gray-200 !px-2 !py-3 text-center w-10">
                            <input type="checkbox" id="wp-sched-select-all" class="scale-125" title="Select all">
                        </th>
                        <th class="border border-gray-200 !px-2 !py-3 text-left">S.No</th>
                        <th class="border border-gray-200 !px-2 !py-3 text-left">Campaign No</th>
                        <th class="border border-gray-200 !px-2 !py-3 text-left">From → To</th>
                        <th class="border border-gray-200 !px-2 !py-3 text-center">Posts</th>
                        <th class="border border-gray-200 !px-2 !py-3 text-center">Completed</th>
                        <th class="border border-gray-200 !px-2 !py-3 text-center">Failed</th>
                        <th class="border border-gray-200 !px-2 !py-3 text-center">Status</th>
                        <th class="border border-gray-200 !px-2 !py-3 text-left">Created</th>
                        <th class="actions-col border border-gray-200 !px-2 !py-3 text-left min-w-[252px]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($campaigns as $index => $campaign)
                        @php
                            $total = (int) $campaign->total_targets;
                            $completed = (int) $campaign->completed_targets;
                            $failed = (int) $campaign->failed_targets;
                            $pending = max($total - $completed - $failed, 0);
                            $progress = $total > 0 ? round(($completed / $total) * 100, 1) : 0;
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="border !px-2 !py-2 text-center">
                                <input type="checkbox" class="wp-sched-campaign-cb campaign-bulk-cb" name="campaign_ids[]" value="{{ $campaign->id }}">
                            </td>
                            <td class="border !px-2 !py-2">{{ $index + 1 + $offset }}</td>
                            <td class="border !px-2 !py-2">{{ $campaign->campaign_no }}</td>
                            <td class="border !px-2 !py-2">{{ $campaign->schedule_from_date?->format('d M Y') }} → {{ $campaign->schedule_to_date?->format('d M Y') }}</td>
                            <td class="border !px-2 !py-2 text-center">{{ $total }}</td>
                            <td class="border !px-2 !py-2 text-center">{{ $completed }}</td>
                            <td class="border !px-2 !py-2 text-center">{{ $failed }}</td>
                            <td class="border !px-2 !py-2">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold
                                    @if($campaign->status === 'completed') bg-green-100 text-green-700
                                    @elseif($campaign->status === 'failed') bg-red-100 text-red-700
                                    @elseif($campaign->status === 'running') bg-yellow-100 text-yellow-700
                                    @else bg-gray-100 text-gray-600 @endif">
                                    {{ ucfirst($campaign->status) }}
                                </span>
                            </td>
                            <td class="border !px-2 !py-2">{{ $campaign->created_at?->format('d-M-Y H:i') }}</td>
                            <td class="actions-col border !px-2 !py-2 min-w-[252px]">
                                @include('admin.campaigns.partials.campaign-list-actions', [
                                    'viewUrl' => route('admin.wp.schedule.campaign.show', $campaign->id),
                                    'editUrl' => route('admin.wp.schedule.campaign.edit', $campaign->id),
                                    'reportUrl' => ($campaign->report_token ?? null)
                                        ? route('admin.wp.schedule.campaign.report', [
                                            'campaign_no' => $campaign->campaign_no,
                                            'token' => $campaign->report_token,
                                        ])
                                        : null,
                                    'openReportInNewTab' => true,
                                    'destroyAction' => route('admin.wp.schedule.campaign.destroy', $campaign->id),
                                    'purgeAction' => route('admin.wp.schedule.campaign.purge.local', $campaign->id),
                                    'destroyConfirm' => 'Delete this entire campaign? All posts will be removed from WordPress and the database.',
                                    'purgeConfirm' => 'Remove this campaign from the dashboard only? Remote WordPress posts stay. You will not be able to edit this campaign here anymore.',
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center !py-6 text-gray-500">No WP scheduled campaigns yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="w-full !mt-2">{{ $campaigns->links() }}</div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/copy.js') }}"></script>
    <script>
        (function () {
            var form = document.getElementById('wp-sched-bulk-purge-local-form');
            var btn = document.getElementById('wp-sched-bulk-purge-local-btn');
            var selectAll = document.getElementById('wp-sched-select-all');
            var boxes = document.querySelectorAll('.wp-sched-campaign-cb');
            if (!form || !btn) return;
            btn.addEventListener('click', function () {
                var ids = Array.prototype.slice.call(document.querySelectorAll('.wp-sched-campaign-cb:checked')).map(function (cb) { return cb.value; });
                if (ids.length === 0) { alert('Please select at least one campaign.'); return; }
                if (!confirm('Remove ' + ids.length + ' campaign(s) from this dashboard only? Remote WordPress posts will NOT be deleted.')) return;
                Array.prototype.slice.call(form.querySelectorAll('input[name="campaign_ids[]"]')).forEach(function (n) { n.remove(); });
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'campaign_ids[]';
                    inp.value = id;
                    form.appendChild(inp);
                });
                form.submit();
            });
            function sync() { btn.disabled = document.querySelectorAll('.wp-sched-campaign-cb:checked').length === 0; }
            boxes.forEach(function (cb) { cb.addEventListener('change', sync); });
            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    boxes.forEach(function (cb) { cb.checked = selectAll.checked; });
                    sync();
                });
            }
            sync();
        })();
    </script>
@endpush
