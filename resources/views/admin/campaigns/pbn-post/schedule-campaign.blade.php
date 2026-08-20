@extends('admin.layout.layout')

@section('title', !empty($isStickySchedule) ? 'Schedule Sticky Posts' : 'Scheduled Campaigns')

@push('style')
    @include('admin.campaigns.partials.campaign-list-table-styles')
@endpush

@section('main-content')

    @php
        $scheduleListRoute = !empty($isStickySchedule)
            ? route('admin.schedule.sticky.campaign.index')
            : route('admin.schedule.campaign.index');
        $scheduleCreateRoute = !empty($isStickySchedule)
            ? route('admin.schedule.sticky.campaign.create')
            : route('admin.schedule.campaign.create');
    @endphp
    {{-- bread-crumbs --}}
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
                        <a href="{{ $scheduleListRoute }}" class="breadcrumb-link">
                            {{ !empty($isStickySchedule) ? 'Schedule Sticky Post' : 'Scheduled Posts' }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="w-full md:w-auto flex flex-wrap justify-start md:justify-end items-center md:shrink-0">
                @if (Auth::guard('admin')->user()->canCreateCampaigns())
                <a href="{{ $scheduleCreateRoute }}"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-full sm:w-fit justify-center
                    bg-[var(--primary-color)] whitespace-nowrap hover:bg-[var(--primary-color)]/70
                    text-white rounded transition-all">
                    {{ !empty($isStickySchedule) ? 'Create Schedule Sticky Post' : 'Create Schedule Campaign' }}
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- success & error alerts --}}
    <div class="w-full flex flex-col gap-2 items-center !mt-2">
        @if (session('cus__success') || session('cus__error'))
            <div class="w-full flex flex-col gap-2 !mb-2">
                @if (session('cus__success'))
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium">{{ session('cus__success') }}</span>
                    </div>
                @endif

                @if (session('cus__error'))
                    <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium">{{ session('cus__error') }}</span>
                    </div>
                @endif
            </div>
        @endif

        @include('admin.campaigns.partials.campaign-list-toolbar')
    </div>

    {{-- table --}}
    <div class="w-full flex flex-wrap justify-between items-start content-card">
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white !p-2 rounded">
            {{ !empty($isStickySchedule) ? 'Schedule Sticky Post Campaigns' : 'Scheduled Campaigns' }}
        </h2>
        @include('admin.campaigns.partials.campaign-list-bulk-bar', [
            'purgeFormId' => 'schedule-bulk-purge-local-form',
            'purgeBtnId' => 'schedule-bulk-purge-local-btn',
            'retryFormId' => 'schedule-bulk-retry-failed-form',
            'retryBtnId' => 'schedule-bulk-retry-failed-btn',
            'purgeAction' => route('admin.schedule.campaign.bulk.purge.local'),
            'retryAction' => route('admin.schedule.campaign.bulk.retry.failed'),
            'retryLabel' => 'Posts',
            'purgeTitle' => 'Remove selected from this app only',
            'retryTitle' => 'Retry all failed posts in selected campaigns',
            'helpText' => 'Select campaigns with checkboxes, then retry failed posts or remove local records.',
        ])

        <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            @php
                $clTh = 'border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left';
                $clTd = 'border border-gray-200 font-sans !px-2 !py-3';
                $clTdCenter = 'border border-gray-200 font-sans !px-2 !py-3 text-center';
                $clTdProgress = 'border border-gray-200 font-sans !px-2 !py-3 min-w-[140px]';
                $clTdActions = 'actions-col border border-gray-200 font-sans !px-2 !py-3 min-w-[252px]';
                $clTdCheckbox = 'border border-gray-200 font-sans !px-2 !py-3 text-center';
            @endphp
            <table class="campaign-list-table display w-full min-w-[1100px] border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th><input type="checkbox" id="bulk-checkBox-selector" class="scale-125"></th>
                        @php
                            $tHead = [
                                'sno',
                                'Campaign No',
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
                                $clTh,
                                'actions-col' => $t === 'Actions',
                            ])>{{ $t }}</th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($campaigns as $index => $campaign)
                        @php
                            $total = (int) $campaign->total_targets;
                            $completed = (int) $campaign->completed_targets;
                            $failed = (int) $campaign->failed_targets;
                            $pending = max($total - ($completed + $failed), 0);
                            $progress = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

                            if ($pending > 0 && ($completed > 0 || $failed > 0)) {
                                $status = 'running';
                                $statusClass = 'bg-yellow-100 text-yellow-700';
                            } elseif ($failed === $total && $total > 0) {
                                $status = 'failed';
                                $statusClass = 'bg-red-100 text-red-700';
                            } elseif ($completed === $total && $total > 0) {
                                $status = 'completed';
                                $statusClass = 'bg-green-100 text-green-700';
                            } elseif ($completed + $failed === $total && $total > 0) {
                                $status = 'semi-complete';
                                $statusClass = 'bg-orange-100 text-orange-700';
                            } else {
                                $status = 'queued';
                                $statusClass = 'bg-gray-100 text-gray-600';
                            }
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="{{ $clTdCheckbox }}">
                                <input type="checkbox" class="multi-check campaign-bulk-cb" name="campaign_ids[]" value="{{ $campaign->id }}">
                            </td>

                            <td class="{{ $clTdCenter }}">
                                {{ $index + 1 + $offset }}
                            </td>

                            <td class="{{ $clTd }}">
                                {{ $campaign->campaign_no }}
                            </td>

                            <td class="{{ $clTd }}">
                                {{ optional($campaign->domainCategory)->name ?? '-' }}
                            </td>

                            <td class="{{ $clTdCenter }}">
                                {{ optional($campaign->schedule_from_date)?->format('d-M-Y') ?? '-' }}
                            </td>

                            <td class="{{ $clTdCenter }}">
                                {{ optional($campaign->schedule_to_date)?->format('d-M-Y') ?? '-' }}
                            </td>

                            <td class="{{ $clTdCenter }}">{{ $total }}</td>
                            <td class="{{ $clTdCenter }}">{{ $completed }}</td>
                            <td class="{{ $clTdCenter }}">{{ $failed }}</td>
                            <td class="{{ $clTdCenter }}">{{ $pending }}</td>

                            <td class="{{ $clTdProgress }}">
                                @include('admin.campaigns.partials.campaign-list-progress', ['progress' => $progress])
                            </td>

                            <td class="{{ $clTdCenter }}">
                                @include('admin.campaigns.partials.campaign-list-status-badge', [
                                    'label' => ucfirst($status),
                                    'statusClass' => $statusClass,
                                ])
                            </td>

                            <td class="{{ $clTd }}">
                                {{ $campaign->created_at->format('d-M-Y H:i') }}
                            </td>

                            <td class="{{ $clTdActions }}">
                                @include('admin.campaigns.partials.campaign-list-actions', [
                                    'viewUrl' => route('admin.schedule.campaign.show', $campaign->id),
                                    'editUrl' => route('admin.schedule.campaign.edit', $campaign->id),
                                    'reportUrl' => route('admin.schedule.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]),
                                    'openReportInNewTab' => true,
                                    'destroyAction' => route('admin.schedule.campaign.destroy', $campaign->id),
                                    'purgeAction' => route('admin.schedule.campaign.purge.local', $campaign->id),
                                    'destroyConfirm' => 'Delete this campaign? All posts will be removed from the database and from the remote site.',
                                    'purgeConfirm' => 'Remove this campaign from the dashboard only? Remote posts stay published. You will not be able to edit this campaign here anymore.',
                                    'bulkReplaceUrl' => in_array((int) $campaign->id, $replaceableCampaignIds ?? [], true)
                                        ? route('admin.schedule.campaign.bulk-domain-replacement.create', $campaign)
                                        : null,
                                ])
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="14" class="text-center !py-4 text-gray-500 bg-gray-100 font-sans">
                                {{ !empty($isStickySchedule) ? 'No schedule sticky post campaigns found...' : 'No schedule campaigns found...' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="w-full !mt-2">
            {{ $campaigns->links() }}
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/copy.js') }}"></script>
    <script>
        (function () {
            var purgeForm = document.getElementById('schedule-bulk-purge-local-form');
            var purgeBtn = document.getElementById('schedule-bulk-purge-local-btn');
            var retryForm = document.getElementById('schedule-bulk-retry-failed-form');
            var retryBtn = document.getElementById('schedule-bulk-retry-failed-btn');

            if (!purgeForm || !purgeBtn || !retryForm || !retryBtn) return;

            // Bulk purge local handler
            purgeBtn.addEventListener('click', function () {
                var ids = Array.prototype.slice.call(document.querySelectorAll('.campaign-bulk-cb:checked')).map(function (cb) { return cb.value; });
                if (ids.length === 0) {
                    alert('Please select at least one campaign.');
                    return;
                }
                if (!confirm('Remove ' + ids.length + ' campaign(s) from this dashboard only? Remote posts will NOT be deleted.')) {
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

            // Bulk retry failed posts handler
            retryBtn.addEventListener('click', function () {
                var ids = Array.prototype.slice.call(document.querySelectorAll('.campaign-bulk-cb:checked')).map(function (cb) { return cb.value; });
                if (ids.length === 0) {
                    alert('Please select at least one campaign.');
                    return;
                }
                if (!confirm('Retry all failed posts in ' + ids.length + ' selected campaign(s)? This will queue all failed posts for republishing.')) {
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
                var hasChecked = document.querySelectorAll('.campaign-bulk-cb:checked').length > 0;
                purgeBtn.disabled = !hasChecked;
                retryBtn.disabled = !hasChecked;
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
