@extends('admin.layout.layout')

@section('title', 'Hidden Links Campaigns')

@push('style')
    @include('admin.campaigns.partials.campaign-list-table-styles')
@endpush

@section('main-content')

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
                        <a href="{{ route('admin.hidden.link.campaign.index') }}" class="breadcrumb-link">
                            PBN Hidden Links
                        </a>
                    </div>
                </div>
            </div>

            <div class="w-full md:w-auto flex flex-wrap justify-start md:justify-end items-center md:shrink-0">
                @if (Auth::guard('admin')->user()->canCreateCampaigns())
                <a href="{{ route('admin.hidden.link.campaign.create') }}"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-full sm:w-fit justify-center duration:300 bg-[var(--primary-color)]
                    whitespace-nowrap hover:bg-[var(--primary-color)]/70 text-white rounded transition-all duration">
                    Create Campaign
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- alerts + filters --}}
    <div class="w-full flex flex-col gap-2 items-center !mt-2">
        @if (session('cus__success') || session('cus__error'))
            <div class="w-full flex flex-col gap-2">
                @if (session('cus__success'))
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full !mb-2" role="alert">
                        <span class="font-medium">{{ session('cus__success') }}</span>
                    </div>
                @endif

                @if (session('cus__error'))
                    <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full !mb-2" role="alert">
                        <span class="font-medium">{{ session('cus__error') }}</span>
                    </div>
                @endif
            </div>
        @endif

        @include('admin.campaigns.partials.campaign-list-toolbar')
    </div>

    {{-- table --}}
    <div class="w-full flex flex-wrap justify-between items-start content-card">
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
            Hidden Links Campaigns
        </h2>

        <form id="bulk-delete-campaigns-form" action="{{ route('admin.hidden.link.campaign.bulk.delete') }}" method="POST" class="hidden">@csrf</form>
        <form id="bulk-purge-local-campaigns-form" action="{{ route('admin.hidden.link.campaign.bulk.purge.local') }}" method="POST" class="hidden">@csrf</form>
        <form id="hidden-links-bulk-retry-failed-form" action="{{ route('admin.hidden.link.campaign.bulk.retry.failed') }}" method="POST" class="hidden">@csrf</form>

        <div class="w-full flex flex-col gap-2 !mb-2">
            <div class="w-full flex flex-wrap items-center gap-2">
                <button type="button" id="bulk-delete-campaigns-btn"
                    class="!px-3 !py-2 rounded bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Removes links from remote sites, then deletes data">
                    Bulk delete selected (remote + DB)
                </button>
                <button type="button" id="bulk-purge-local-campaigns-btn"
                    class="!px-3 !py-2 rounded bg-orange-600 text-white text-sm hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Only removes rows in this app; remote links stay">
                    Bulk remove locally only
                </button>
                <button type="button" id="hidden-links-bulk-retry-failed-btn"
                    class="!px-3 !py-2 rounded bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Retry all failed tasks in selected campaigns">
                    Bulk Retry Failed Tasks
                </button>
            </div>
            <p class="text-sm text-gray-500">Select campaigns with checkboxes, then retry failed tasks, delete (remote + DB), or remove locally only.</p>
        </div>

        <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            <table class="campaign-list-table display w-full min-w-[1200px] border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border border-gray-200 !px-2 !py-3 text-left w-10">
                            <input type="checkbox" id="select-all-campaigns" class="scale-125" title="Select all">
                        </th>
                        @php
                            $tHead = [
                                'S.No',
                                'Campaign No',
                                'Domain Category',
                                'Links',
                                'Domains',
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
                                'border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left',
                                'actions-col min-w-[252px]' => $t === 'Actions',
                            ])>
                                {{ $t }}
                            </th>
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

                            $isBulkUpdated = $campaign->last_bulk_updated_at ?? null;
                            if ($pending > 0 && $completed > 0) {
                                $status = 'running';
                                $statusClass = 'bg-yellow-100 text-yellow-700';
                            } elseif ($completed + $failed === $total && $failed === 0 && $total > 0) {
                                $status = $isBulkUpdated ? 'updated' : 'completed';
                                $statusClass = 'bg-green-100 text-green-700';
                            } elseif ($failed === $total && $total > 0) {
                                $status = 'failed';
                                $statusClass = 'bg-red-100 text-red-700';
                            } else {
                                $status = 'queued';
                                $statusClass = 'bg-gray-100 text-gray-600';
                            }
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                <input type="checkbox" class="campaign-select-cb" name="campaign_ids[]" value="{{ $campaign->id }}">
                            </td>
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ $campaign->campaign_no }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ optional($campaign->domainCategory)->name ?? '-' }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $campaign->links_count }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $campaign->domains_count }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $completed }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $failed }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $pending }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 min-w-[140px]">
                                <div class="w-full bg-gray-200 rounded h-2">
                                    <div class="h-2 rounded
                                    {{ $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                        style="width: {{ $progress }}%">
                                    </div>
                                </div>
                                <div class="text-[11px] text-gray-600 text-center mt-1">
                                    {{ $progress }}%
                                </div>
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $status === 'updated' ? 'Updated' : ucfirst($status) }}
                                </span>
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ $campaign->created_at?->format('d-M-Y H:i') }}
                            </td>

                            <td class="actions-col border border-gray-200 font-sans !px-2 !py-3 min-w-[252px]">
                                @include('admin.campaigns.partials.campaign-list-actions', [
                                    'viewUrl' => route('admin.hidden.link.campaign.show', $campaign->id),
                                    'editUrl' => route('admin.hidden.link.campaign.edit', $campaign->id),
                                    'reportUrl' => route('admin.hidden.link.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]),
                                    'destroyAction' => route('admin.hidden.link.campaign.bulk.delete'),
                                    'destroyMethod' => 'POST',
                                    'destroyHiddenInputs' => [
                                        ['name' => 'campaign_ids[]', 'value' => $campaign->id],
                                    ],
                                    'purgeAction' => route('admin.hidden.link.campaign.purge.local', $campaign->id),
                                    'destroyConfirm' => 'Delete this campaign? Links will be removed from remote sites, then the campaign and its data will be deleted.',
                                    'purgeConfirm' => 'Remove this campaign from the dashboard only? Remote hidden links stay. You will not be able to edit this campaign here anymore.',
                                ])
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No hidden links campaigns found...
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
            var formDelete = document.getElementById('bulk-delete-campaigns-form');
            var formPurge = document.getElementById('bulk-purge-local-campaigns-form');
            var formRetry = document.getElementById('hidden-links-bulk-retry-failed-form');
            var bulkDeleteBtn = document.getElementById('bulk-delete-campaigns-btn');
            var bulkPurgeBtn = document.getElementById('bulk-purge-local-campaigns-btn');
            var bulkRetryBtn = document.getElementById('hidden-links-bulk-retry-failed-btn');
            var selectAll = document.getElementById('select-all-campaigns');
            var checkboxes = document.querySelectorAll('.campaign-select-cb');

            function selectedCampaignIds() {
                return Array.prototype.slice.call(document.querySelectorAll('.campaign-select-cb:checked')).map(function (cb) {
                    return cb.value;
                });
            }

            function fillFormWithCampaignIds(formEl, ids) {
                Array.prototype.slice.call(formEl.querySelectorAll('input[name="campaign_ids[]"]')).forEach(function (n) {
                    n.remove();
                });
                ids.forEach(function (id) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = 'campaign_ids[]';
                    inp.value = id;
                    formEl.appendChild(inp);
                });
            }

            function updateBulkButtonsState() {
                var any = selectedCampaignIds().length > 0;
                if (bulkDeleteBtn) bulkDeleteBtn.disabled = !any;
                if (bulkPurgeBtn) bulkPurgeBtn.disabled = !any;
                if (bulkRetryBtn) bulkRetryBtn.disabled = !any;
            }

            if (bulkDeleteBtn && formDelete) {
                bulkDeleteBtn.addEventListener('click', function () {
                    var ids = selectedCampaignIds();
                    if (ids.length === 0) {
                        alert('Please select at least one campaign.');
                        return;
                    }
                    if (!confirm('Delete selected campaign(s) from remote sites and from the database?')) {
                        return;
                    }
                    fillFormWithCampaignIds(formDelete, ids);
                    formDelete.submit();
                });
            }

            if (bulkPurgeBtn && formPurge) {
                bulkPurgeBtn.addEventListener('click', function () {
                    var ids = selectedCampaignIds();
                    if (ids.length === 0) {
                        alert('Please select at least one campaign.');
                        return;
                    }
                    if (!confirm('Remove selected campaign(s) from this dashboard only? Remote hidden links will NOT be deleted.')) {
                        return;
                    }
                    fillFormWithCampaignIds(formPurge, ids);
                    formPurge.submit();
                });
            }

            if (bulkRetryBtn && formRetry) {
                bulkRetryBtn.addEventListener('click', function () {
                    var ids = selectedCampaignIds();
                    if (ids.length === 0) {
                        alert('Please select at least one campaign.');
                        return;
                    }
                    if (!confirm('Retry all failed tasks in ' + ids.length + ' selected campaign(s)? This will queue all failed tasks for republishing.')) {
                        return;
                    }
                    fillFormWithCampaignIds(formRetry, ids);
                    formRetry.submit();
                });
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
                    updateBulkButtonsState();
                });
            }
            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', updateBulkButtonsState);
            });

            updateBulkButtonsState();
        })();
    </script>
@endpush
