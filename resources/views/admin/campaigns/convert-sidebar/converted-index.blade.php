@extends('admin.layout.layout')

@section('title', 'Live → Scheduled sidebar')

@push('style')
    @include('admin.campaigns.partials.campaign-list-table-styles')
    @include('admin.campaigns.convert-sidebar.partials.converted-index-page-styles')
@endpush

@section('main-content')
<div class="converted-dripfeed-list-page w-full max-w-full min-w-0">
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
                        <a href="{{ route('admin.convert.sidebar.converted.index') }}" class="breadcrumb-link">Converted sidebar</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">Live → Scheduled sidebar</div>
                </div>
            </div>

            <div class="w-full md:w-auto flex flex-wrap justify-start md:justify-end items-center md:shrink-0 gap-2">
                <a href="{{ route('admin.convert.sidebar.step1') }}"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-full sm:w-fit justify-center
                    bg-[var(--primary-color)] whitespace-nowrap hover:bg-[var(--primary-color)]/70
                    text-white rounded transition-all">
                    Convert sidebar campaign
                </a>
            </div>
        </div>
    </div>

    <div class="w-full flex flex-col gap-2 !mt-2 !mb-2">
        @if (session('cus__success') || session('cus__error'))
            <div class="w-full flex flex-col gap-2">
                @if (session('cus__success'))
                    <div class="js-cus-alert !p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        {{ session('cus__success') }}
                    </div>
                @endif
                @if (session('cus__error'))
                    <div class="js-cus-alert !p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        {{ session('cus__error') }}
                    </div>
                @endif
            </div>
        @endif

        @include('admin.campaigns.partials.campaign-list-toolbar', [
            'searchPlaceholder' => 'Search scheduled or source sidebar campaign…',
        ])
    </div>

    @php
        $statItems = [
            ['value' => $stats['total'] ?? 0, 'label' => 'Total converted', 'icon' => 'folder_open', 'ring' => 'bg-orange-50 text-[var(--primary-color)]'],
            ['value' => $stats['active'] ?? 0, 'label' => 'Active', 'icon' => 'sync', 'ring' => 'bg-yellow-50 text-yellow-600'],
            ['value' => $stats['completed'] ?? 0, 'label' => 'Completed', 'icon' => 'task_alt', 'ring' => 'bg-green-50 text-green-600'],
            ['value' => $stats['attention'] ?? 0, 'label' => 'Needs attention', 'icon' => 'warning', 'ring' => 'bg-red-50 text-red-600'],
        ];
    @endphp

    <div class="content-card converted-dripfeed-stats-card !py-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-2 sm:gap-3 w-full min-w-0">
            @foreach ($statItems as $item)
                <div
                    class="flex flex-row items-center gap-2 sm:gap-3 rounded-xl border border-gray-100 bg-gradient-to-r from-gray-50/90 to-white !p-2.5 sm:!p-3 min-w-0">
                    <div
                        class="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-full {{ $item['ring'] }} shadow-inner">
                        <span class="material-symbols-outlined !text-[22px]">{{ $item['icon'] }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-lg sm:text-xl font-bold tabular-nums leading-tight text-gray-900">
                            {{ number_format($item['value']) }}</p>
                        <p class="text-[11px] sm:text-xs text-gray-600 leading-snug line-clamp-2">{{ $item['label'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="w-full content-card">
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
            Live → Scheduled sidebar campaigns
        </h2>

        @if ($campaigns->total() > 0)
            <p class="text-sm text-gray-500 w-full !mb-3">
                Showing {{ $campaigns->firstItem() }}–{{ $campaigns->lastItem() }} of {{ number_format($campaigns->total()) }}
                · Live sidebar campaigns moved to staggered schedule.
            </p>
        @else
            <p class="text-sm text-gray-500 w-full !mb-3">
                Live sidebar campaigns moved to staggered schedule.
            </p>
        @endif

        <form id="schedule-sidebar-bulk-purge-local-form" action="{{ route('admin.schedule.sidebar.campaign.bulk.purge.local') }}" method="POST" class="hidden">@csrf</form>
        <form id="schedule-sidebar-bulk-retry-failed-form" action="{{ route('admin.schedule.sidebar.campaign.bulk.retry.failed') }}" method="POST" class="hidden">@csrf</form>

        <div class="w-full flex flex-col gap-2 !mb-2">
            <div class="w-full flex flex-wrap items-center gap-2">
                <button type="button" id="schedule-sidebar-bulk-retry-failed-btn"
                    class="!px-3 !py-2 rounded bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Retry all failed tasks in selected campaigns">
                    Bulk Retry Failed Tasks
                </button>
                <button type="button" id="schedule-sidebar-bulk-purge-local-btn"
                    class="!px-3 !py-2 rounded bg-orange-600 text-white text-sm hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Remove selected from this app only; remote blogroll entries stay published">
                    Bulk remove locally only
                </button>
            </div>
            <p class="text-sm text-gray-500">Select campaigns with checkboxes, then retry failed tasks or remove local records.</p>
        </div>

        <div class="converted-dripfeed-table-scroll">
            <table class="campaign-list-table display w-full min-w-[1200px] border border-gray-200 border-collapse text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th><input type="checkbox" id="bulk-checkBox-selector" class="scale-125"></th>
                        @php
                            $tHead = [
                                'sno',
                                'Campaign No',
                                'Source Live',
                                'Domain Category',
                                'Schedule From',
                                'Schedule To',
                                'Total Targets',
                                'Completed',
                                'Failed',
                                'Pending',
                                'Progress',
                                'Status',
                                'Created At',
                                'Actions',
                            ];
                        @endphp
                        @foreach ($tHead as $t)
                            <th @class([
                                'border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left',
                                'actions-col min-w-[252px]' => $t === 'Actions',
                            ])>{{ $t }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($campaigns as $index => $campaign)
                        @php
                            $total = (int) ($campaign->live_tasks_total ?? $campaign->total_targets);
                            $completed = (int) ($campaign->live_tasks_success ?? $campaign->completed_targets);
                            $failed = (int) ($campaign->live_tasks_failed ?? $campaign->failed_targets);
                            $pending = max($total - ($completed + $failed), 0);
                            $progress = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

                            $categoryLabel = optional($campaign->domainCategory)->name
                                ?? optional($campaign->sourceSidebarCampaign?->domainCategory)->name
                                ?? '-';

                            if ($pending > 0) {
                                $status = 'running';
                                $statusClass = 'bg-yellow-100 text-yellow-700';
                            } elseif ($failed === $total && $total > 0) {
                                $status = 'failed';
                                $statusClass = 'bg-red-100 text-red-700';
                            } elseif ($completed + $failed === $total && $total > 0) {
                                $status = 'completed';
                                $statusClass = 'bg-green-100 text-green-700';
                            } else {
                                $status = 'queued';
                                $statusClass = 'bg-gray-100 text-gray-600';
                            }

                            $source = $campaign->sourceSidebarCampaign;
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                <input type="checkbox" class="multi-check campaign-bulk-cb" name="campaign_ids[]" value="{{ $campaign->id }}">
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ $campaign->campaign_no }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                @if ($source)
                                    <a href="{{ route('admin.sidebar.campaign.show', $source->id) }}"
                                        class="text-[var(--primary-color)] hover:underline"
                                        title="{{ $source->campaign_no }}">
                                        {{ $source->campaign_no }}
                                    </a>
                                @else
                                    -
                                @endif
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ $categoryLabel }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ optional($campaign->schedule_from_date)?->format('d M Y') ?? '-' }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ optional($campaign->schedule_to_date)?->format('d M Y') ?? '-' }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">{{ $total }}</td>
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">{{ $completed }}</td>
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">{{ $failed }}</td>
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">{{ $pending }}</td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 min-w-[140px]">
                                <div class="w-full bg-gray-200 rounded h-2">
                                    <div class="h-2 rounded {{ $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                        style="width: {{ min(100, max(0, $progress)) }}%">
                                    </div>
                                </div>
                                <div class="text-[11px] text-gray-600 text-center mt-1">{{ $progress }}%</div>
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ $campaign->created_at->format('d-M-Y H:i') }}
                            </td>

                            <td class="actions-col border border-gray-200 font-sans !px-2 !py-3 min-w-[252px]">
                                @include('admin.campaigns.partials.campaign-list-actions', [
                                    'viewUrl' => route('admin.schedule.sidebar.campaign.show', $campaign->id),
                                    'editUrl' => route('admin.schedule.sidebar.campaign.edit', $campaign->id),
                                    'reportUrl' => route('admin.schedule.sidebar.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]),
                                    'openReportInNewTab' => true,
                                    'destroyAction' => route('admin.schedule.sidebar.campaign.destroy', $campaign->id),
                                    'purgeAction' => route('admin.schedule.sidebar.campaign.purge.local', $campaign->id),
                                    'destroyConfirm' => 'Delete this campaign? All tasks will be removed from the database and from the remote site.',
                                    'purgeConfirm' => 'Remove this campaign from the dashboard only? Remote blogroll entries stay published.',
                                ])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="text-center !py-4 text-gray-500 bg-gray-100 font-sans">
                                No converted schedule sidebar campaigns found.
                                <a href="{{ route('admin.convert.sidebar.step1') }}" class="text-[var(--primary-color)] hover:underline">Convert a live sidebar campaign</a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="w-full !mt-4">
            {{ $campaigns->links() }}
        </div>
    </div>
</div>
@endsection

@push('scripts')
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/copy.js') }}"></script>
    <script>
        (function () {
            var purgeForm = document.getElementById('schedule-sidebar-bulk-purge-local-form');
            var purgeBtn = document.getElementById('schedule-sidebar-bulk-purge-local-btn');
            var retryForm = document.getElementById('schedule-sidebar-bulk-retry-failed-form');
            var retryBtn = document.getElementById('schedule-sidebar-bulk-retry-failed-btn');

            if (!purgeForm || !purgeBtn || !retryForm || !retryBtn) return;

            purgeBtn.addEventListener('click', function () {
                var ids = Array.prototype.slice.call(document.querySelectorAll('.campaign-bulk-cb:checked')).map(function (cb) { return cb.value; });
                if (ids.length === 0) {
                    alert('Please select at least one campaign.');
                    return;
                }
                if (!confirm('Remove ' + ids.length + ' campaign(s) from this dashboard only? Remote blogroll entries will NOT be deleted.')) {
                    return;
                }
                Array.prototype.slice.call(purgeForm.querySelectorAll('input[name="campaign_ids[]"]')).forEach(function (n) { n.remove(); });
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'campaign_ids[]';
                    inp.value = id;
                    purgeForm.appendChild(inp);
                });
                purgeForm.submit();
            });

            retryBtn.addEventListener('click', function () {
                var ids = Array.prototype.slice.call(document.querySelectorAll('.campaign-bulk-cb:checked')).map(function (cb) { return cb.value; });
                if (ids.length === 0) {
                    alert('Please select at least one campaign.');
                    return;
                }
                if (!confirm('Retry all failed tasks in ' + ids.length + ' selected campaign(s)? This will queue all failed tasks for republishing.')) {
                    return;
                }
                Array.prototype.slice.call(retryForm.querySelectorAll('input[name="campaign_ids[]"]')).forEach(function (n) { n.remove(); });
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'campaign_ids[]';
                    inp.value = id;
                    retryForm.appendChild(inp);
                });
                retryForm.submit();
            });

            function syncButtons() {
                var n = document.querySelectorAll('.campaign-bulk-cb:checked').length;
                purgeBtn.disabled = n === 0;
                retryBtn.disabled = n === 0;
            }

            function syncSelectAllHeader() {
                var boxes = document.querySelectorAll('.campaign-bulk-cb');
                var selAll = document.getElementById('bulk-checkBox-selector');
                if (!selAll || boxes.length === 0) return;
                var allOn = Array.prototype.every.call(boxes, function (c) { return c.checked; });
                var anyOn = Array.prototype.some.call(boxes, function (c) { return c.checked; });
                selAll.checked = allOn;
                selAll.indeterminate = anyOn && !allOn;
            }

            document.querySelectorAll('.campaign-bulk-cb').forEach(function (cb) {
                cb.addEventListener('change', function () {
                    syncButtons();
                    syncSelectAllHeader();
                });
            });

            var selAll = document.getElementById('bulk-checkBox-selector');
            if (selAll) {
                selAll.addEventListener('change', function () {
                    selAll.indeterminate = false;
                    document.querySelectorAll('.campaign-bulk-cb').forEach(function (cb) {
                        cb.checked = selAll.checked;
                    });
                    syncButtons();
                });
            }

            syncButtons();
            syncSelectAllHeader();
        })();
    </script>
@endpush
