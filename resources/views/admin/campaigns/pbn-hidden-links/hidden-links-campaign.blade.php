@extends('admin.layout.layout')

@section('title', 'Hidden Links Campaigns')

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
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

            <div class="w-1/2 flex flex-wrap justify-end items-center">
                @if (Auth::guard('admin')->user()->canCreateCampaigns())
                <a href="{{ route('admin.hidden.link.campaign.create') }}"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-fit justify-center
               bg-[var(--primary-color)] whitespace-nowrap text-white rounded
               hover:bg-[var(--primary-color)]/70 transition-all">
                    Create Campaign
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- alerts --}}
    <div class="w-full flex flex-col gap-2 items-center !mt-2">
        @if (session('cus__success') || session('cus__error'))
            <div class="w-full flex flex-col gap-2">
                @if (session('cus__success'))
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700">
                        {{ session('cus__success') }}
                    </div>
                @endif

                @if (session('cus__error'))
                    <div class="!p-4 text-sm rounded bg-red-100 text-red-700">
                        {{ session('cus__error') }}
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- filters --}}
    <div class="flex flex-wrap items-center content-card w-full">

        <div class="w-full flex flex-wrap gap-2">
            <form method="GET" action="{{ url()->current() }}"
                class="w-full flex flex-col justify-center items-end gap-1">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search campaign no"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm rounded w-full">
                {{-- <button type="submit" class="bg-[var(--sidebar-bg)] text-white !mt-1 !px-5 !py-3 rounded cursor-pointer">
                    Search
                </button> --}}
            </form>
        </div>

    </div>

    {{-- table --}}
    <div class="w-full flex flex-wrap justify-between items-start content-card !mt-3">

        <div class="w-full flex flex-wrap items-center gap-3 !mb-4">
            <h2 class="text-xl capitalize bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
                Hidden Links Campaigns
            </h2>
            <form id="bulk-delete-campaigns-form" action="{{ route('admin.hidden.link.campaign.bulk.delete') }}" method="POST" class="flex items-center gap-2">
                @csrf
                <div id="bulk-delete-campaign-ids-container"></div>
                <button type="submit" id="bulk-delete-campaigns-btn" class="!px-3 !py-2 rounded bg-red-600 text-white text-sm hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Select one or more campaigns using the checkboxes, then click here">
                    Bulk delete selected
                </button>
            </form>
            <span class="text-sm text-gray-500">Select campaigns with checkboxes, then use &quot;Bulk delete selected&quot; or delete a single campaign with the red delete icon in each row.</span>
        </div>

        <div class="overflow-x-auto w-full">
            <table class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th class="border border-gray-200 !px-2 !py-3 text-left w-10">
                            <input type="checkbox" id="select-all-campaigns" title="Select all">
                        </th>
                        @php
                            $tHead = [
                                'S.No',
                                'Campaign No',
                                'Type',
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
                            <th class="border border-gray-200 !px-2 !py-3 text-left">
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
                            <td class="border !px-2 !py-2 text-center">
                                <input type="checkbox" class="campaign-select-cb" name="campaign_ids[]" value="{{ $campaign->id }}" form="bulk-delete-campaigns-form">
                            </td>
                            <td class="border !px-2 !py-2 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            <td class="border !px-2 !py-2">
                                {{ $campaign->campaign_no }}
                            </td>

                            <td class="border !px-2 !py-2">
                                Hidden Links Campaign
                            </td>

                            <td class="border !px-2 !py-2">
                                {{ optional($campaign->domainCategory)->name ?? '-' }}
                            </td>

                            <td class="border !px-2 !py-2 text-center">
                                {{ $campaign->links_count }}
                            </td>

                            <td class="border !px-2 !py-2 text-center">
                                {{ $campaign->domains_count }}
                            </td>

                            <td class="border !px-2 !py-2 text-center">
                                {{ $completed }}
                            </td>

                            <td class="border !px-2 !py-2 text-center">
                                {{ $failed }}
                            </td>

                            <td class="border !px-2 !py-2 text-center">
                                {{ $pending }}
                            </td>

                            <td class="border !px-2 !py-2 min-w-[140px]">
                                <div class="w-full bg-gray-200 rounded h-2">
                                    <div class="h-2 rounded
                                    {{ $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                        style="width: {{ $progress }}%">
                                    </div>
                                </div>
                                <div class="text-[11px] text-gray-600 text-center !mt-1">
                                    {{ $progress }}%
                                </div>
                            </td>

                            <td class="border !px-2 !py-2 text-center">
                                <span class="!p-2 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $status === 'updated' ? 'Updated' : ucfirst($status) }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-2">
                                {{ $campaign->created_at?->format('d-M-Y H:i') }}
                            </td>

                            <td class="border !px-2 !py-2">
                                <div class="flex gap-2 justify-center flex-wrap">
                                    <a href="{{ route('admin.hidden.link.campaign.show', $campaign->id) }}"
                                        class="bg-green-500 w-7 h-7 flex items-center justify-center rounded" title="View campaign">
                                        <span class="material-symbols-outlined text-white !text-sm">visibility</span>
                                    </a>
                                    <a href="{{ route('admin.hidden.link.campaign.edit', $campaign->id) }}"
                                        class="bg-yellow-600 w-7 h-7 flex items-center justify-center rounded hover:bg-yellow-700" title="Edit links (bulk update)">
                                        <span class="material-symbols-outlined text-white !text-sm">edit</span>
                                    </a>
                                    <a href="javascript:void(0)"
                                        data-report="{{ route('admin.hidden.link.campaign.report', [
                                            'campaign_no' => $campaign->campaign_no,
                                            'token' => $campaign->report_token,
                                        ]) }}"
                                        class="bg-yellow-500 copy-link flex items-center justify-center rounded w-7 h-7 hover:bg-yellow-600" title="Copy report link">
                                        <span class="material-symbols-outlined !text-sm text-white">content_copy</span>
                                    </a>
                                    <a href="{{ route('admin.hidden.link.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]) }}"
                                        class="bg-blue-600 w-7 h-7 flex items-center justify-center rounded" title="View report">
                                        <span class="material-symbols-outlined text-white !text-sm">assignment</span>
                                    </a>
                                    <form action="{{ route('admin.hidden.link.campaign.bulk.delete') }}" method="POST" class="inline"
                                        onsubmit="return confirm('Delete this campaign? Links will be removed from remote sites, then the campaign and its data will be deleted.');">
                                        @csrf
                                        <input type="hidden" name="campaign_ids[]" value="{{ $campaign->id }}">
                                        <button type="submit" class="bg-red-500 w-7 h-7 flex items-center justify-center rounded hover:bg-red-600 border-0 cursor-pointer" title="Delete this campaign">
                                            <span class="material-symbols-outlined text-white !text-sm">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="text-center !py-4 text-gray-500 bg-gray-100">
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
            var form = document.getElementById('bulk-delete-campaigns-form');
            var bulkBtn = document.getElementById('bulk-delete-campaigns-btn');
            var container = document.getElementById('bulk-delete-campaign-ids-container');
            var selectAll = document.getElementById('select-all-campaigns');
            var checkboxes = document.querySelectorAll('.campaign-select-cb');

            function updateBulkDeleteState() {
                var checked = document.querySelectorAll('.campaign-select-cb:checked');
                var any = checked.length > 0;
                if (bulkBtn) bulkBtn.disabled = !any;
                if (container) {
                    container.innerHTML = '';
                    checked.forEach(function (cb) {
                        var inp = document.createElement('input');
                        inp.type = 'hidden';
                        inp.name = 'campaign_ids[]';
                        inp.value = cb.value;
                        container.appendChild(inp);
                    });
                }
            }

            if (form) {
                form.addEventListener('submit', function () {
                    var checked = document.querySelectorAll('.campaign-select-cb:checked');
                    if (checked.length === 0) {
                        alert('Please select at least one campaign to delete.');
                        return false;
                    }
                    return confirm('Delete selected campaign(s)? Links will be removed from remote sites, then campaigns and data deleted.');
                });
            }

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(function (cb) { cb.checked = selectAll.checked; });
                    updateBulkDeleteState();
                });
            }
            checkboxes.forEach(function (cb) {
                cb.addEventListener('change', updateBulkDeleteState);
            });

            updateBulkDeleteState();
        })();
    </script>
@endpush
