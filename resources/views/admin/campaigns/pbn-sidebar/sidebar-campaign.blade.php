@extends('admin.layout.layout')

@section('title', 'Sidebar Campaigns')

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.sidebar.campaign.index') }}" class="breadcrumb-link">PBN Blogroll</a>
                    </div>

                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center">
                @if (Auth::guard('admin')->user()->canCreateCampaigns())
                <a href="{{ route('admin.sidebar.campaign.create') }}"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-fit justify-center duration:300 bg-[var(--primary-color)] 
                    whitespace-nowrap hover:bg-[var(--primary-color)]/70 text-white rounded transition-all duration">
                    Create Campaign</a>
                @endif
            </div>
        </div>
    </div>


    {{-- ******************* success & errors alerts ***************** --}}

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

        {{-- filters thing here --}}

        <div
            class="flex flex-col gap-4 lg:flex-row lg:flex-nowrap lg:items-center lg:justify-between content-card w-full min-w-0 campaign-list-toolbar">

            <div
                class="w-full lg:w-auto lg:flex-1 lg:min-w-0 flex flex-col gap-2 sm:flex-row sm:flex-wrap md:flex-nowrap sm:items-center lg:items-center">

                <div class="w-full sm:w-40 shrink-0 md:shrink-0">
                    <select name="" id="domain-category"
                        class="bg-gray-100  border border-gray-200 !w-full !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                        <option value="">select</option>
                        {{-- @if (isset($domainCategories) && count($domainCategories) > 0)
                            @foreach ($domainCategories as $domainCategory)
                                <option value="{{ $domainCategory->id }}">{{ $domainCategory->name }}</option>
                            @endforeach
                        @endif --}}
                    </select>
                </div>
                <div class="w-full sm:flex-1 sm:min-w-0 md:min-w-[12rem]">
                    <form action="#"
                        class="w-full flex flex-col gap-2 sm:flex-row sm:flex-wrap md:flex-nowrap sm:items-center sm:gap-2"
                        method="post">
                        @csrf
                        <select name="actions" id=""
                            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full sm:flex-1 sm:min-w-0 md:min-w-[9rem] rounded outline-none focus:border-orange-600">
                            <option value="">Bulk actions</option>
                            <option value="1">Delete</option>
                        </select>
                        <input type="hidden" name="bulk_ids" id="valHolders">
                        <button type="submit"
                            class="flex !p-3 !px-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer shrink-0 w-full sm:w-auto">
                            Apply
                        </button>
                    </form>


                </div>

            </div>
            <div
                class="w-full lg:w-auto lg:max-w-none flex flex-col gap-3 sm:flex-row sm:flex-wrap md:flex-nowrap sm:items-center sm:gap-3 lg:justify-end lg:shrink-0 min-w-0">

                @include('admin.campaigns.partials.campaign-owner-filter')

                {{-- Search Box --}}

                <div class="relative w-full sm:flex-1 sm:min-w-[12rem] sm:max-w-md max-h-12 overflow-hidden min-w-0">
                    <form method="GET" action="{{ url()->current() }}" class="relative w-full">

                        {{-- keep other parameters --}}
                        @foreach (request()->except('search') as $key => $value)
                            @continue(is_array($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <input type="search" name="search" placeholder="search here" id="search_category"
                            value="{{ request('search') }}"
                            class="bg-gray-100 shadow border border-gray-200 !p-3 !pr-[50px] max-h-12 text-sm w-full rounded outline-none">

                        <button type="submit"
                            class="w-12 h-12 flex items-center justify-center bg-[var(--sidebar-bg)] absolute top-0 right-0 rounded-r">
                            <svg class="w-5 h-5 text-white !text-sm" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>

                    </form>


                </div>

            </div>
        </div>


    </div>


    {{-- ******************* Ends here  ***************** --}}

    <div class="w-full flex flex-wrap justify-between items-start content-card">
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">Sidebar Campaigns
        </h2>
        <form id="sidebar-bulk-purge-local-form" action="{{ route('admin.sidebar.campaign.bulk.purge.local') }}" method="POST" class="hidden">@csrf</form>
        <div class="w-full flex flex-wrap items-center gap-2 !mb-2">
            <button type="button" id="sidebar-bulk-purge-local-btn"
                class="!px-3 !py-2 rounded bg-orange-600 text-white text-sm hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Bulk remove locally only
            </button>
            <span class="text-sm text-gray-500">Select with checkboxes; remote blogroll is not changed.</span>
        </div>

        <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
            <table class="display w-full min-w-[1100px] border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th>
                            <input type="checkbox" id="bulk-checkBox-selector" class="scale-125">
                        </th>

                        @php
                            $tHead = [
                                'sno',
                                'Campaign No',
                                'Type',
                                'Domain Category',
                                'Sidebar Links',
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
                            <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">
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

                            $isBulkUpdated = $campaign->last_bulk_updated_at !== null;
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

                            {{-- checkbox --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                <input type="checkbox" class="multi-check campaign-bulk-cb" name="campaign_ids[]" value="{{ $campaign->id }}">
                            </td>

                            {{-- sno --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            {{-- campaign no --}}
                            <td class="border border-gray-200 !px-2 !py-3">
                                {{ $campaign->campaign_no }}
                            </td>

                            {{-- type --}}
                            <td class="border border-gray-200 !px-2 !py-3">
                                Sidebar Campaign
                            </td>

                            {{-- domain category --}}
                            <td class="border border-gray-200 !px-2 !py-3">
                                {{ optional($campaign->domainCategory)->name ?? '-' }}
                            </td>

                            {{-- sidebar links --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $campaign->links->count() }}
                            </td>

                            {{-- domains --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $campaign->domains->count() }}
                            </td>

                            {{-- completed --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $completed }}
                            </td>

                            {{-- failed --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $failed }}
                            </td>

                            {{-- pending --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $pending }}
                            </td>

                            {{-- progress --}}
                            <td class="border border-gray-200 !px-2 !py-3 min-w-[140px]">
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

                            {{-- status --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $status === 'updated' ? 'Updated' : ucfirst($status) }}
                                </span>
                            </td>

                            {{-- created --}}
                            <td class="border border-gray-200 !px-2 !py-3">
                                {{ $campaign->created_at?->format('d M Y H:i') }}
                            </td>

                            {{-- actions --}}
                            <td class="border border-gray-200 !px-2 !py-3">
                                <div class="flex gap-2 justify-center">
                                    {{-- View campaign (tasks/links) --}}
                                    <a href="{{ route('admin.sidebar.campaign.show', $campaign->id) }}"
                                        class="bg-green-500 w-7 h-7 flex items-center justify-center rounded hover:bg-green-600"
                                        title="View campaign">
                                        <span class="material-symbols-outlined text-white !text-sm">visibility</span>
                                    </a>
                                    {{-- Edit / bulk edit links --}}
                                    <a href="{{ route('admin.sidebar.campaign.edit', $campaign->id) }}"
                                        class="bg-yellow-400 w-7 h-7 flex items-center justify-center rounded hover:bg-yellow-500"
                                        title="Edit links (bulk update keyword/URL on remote and in DB)">
                                        <span class="material-symbols-outlined text-white !text-sm">edit</span>
                                    </a>
                                    <a href="javascript:void(0)"
                                        data-report="{{ route('admin.sidebar.campaign.report', [
                                            'campaign_no' => $campaign->campaign_no,
                                            'token' => $campaign->report_token,
                                        ]) }}"
                                        class="bg-gray-500 copy-link flex items-center justify-center rounded w-7 h-7 hover:bg-amber-600"
                                        title="Copy report link">
                                        <span class="material-symbols-outlined !text-sm text-white">content_copy</span>
                                    </a>
                                    {{-- {{ route('admin.sidebar-campaigns.report', $campaign->id) }} --}}
                                    <a href="{{ route('admin.sidebar.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]) }}"
                                        class="bg-blue-600 w-7 h-7 flex items-center justify-center rounded hover:bg-blue-700">
                                        <span class="material-symbols-outlined text-white !text-sm">assignment</span>
                                    </a>
                                    <form action="{{ route('admin.sidebar.campaign.destroy', $campaign->id) }}" method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Delete this sidebar campaign? All tasks will be removed from the database and from remote blogroll.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 w-7 h-7 flex items-center justify-center rounded hover:bg-red-600"
                                            title="Delete campaign and all tasks (DB + remote)">
                                            <span class="material-symbols-outlined text-white !text-sm">delete</span>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.sidebar.campaign.purge.local', $campaign->id) }}" method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Remove this campaign from the dashboard only? Remote blogroll links stay. You will not be able to edit this campaign here anymore.');">
                                        @csrf
                                        <button type="submit" class="bg-orange-500 w-7 h-7 flex items-center justify-center rounded hover:bg-orange-600"
                                            title="Dashboard only — does not delete remote links">
                                            <span class="material-symbols-outlined text-white !text-sm">database</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="13" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No sidebar campaigns found...
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
            var form = document.getElementById('sidebar-bulk-purge-local-form');
            var btn = document.getElementById('sidebar-bulk-purge-local-btn');
            if (!form || !btn) return;
            btn.addEventListener('click', function () {
                var ids = Array.prototype.slice.call(document.querySelectorAll('.campaign-bulk-cb:checked')).map(function (cb) { return cb.value; });
                if (ids.length === 0) { alert('Please select at least one campaign.'); return; }
                if (!confirm('Remove ' + ids.length + ' campaign(s) from this dashboard only? Remote blogroll links will NOT be deleted.')) return;
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
            function sync() { btn.disabled = document.querySelectorAll('.campaign-bulk-cb:checked').length === 0; }
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
                cb.addEventListener('change', function () { sync(); syncSelectAllHeader(); });
            });
            var selAll = document.getElementById('bulk-checkBox-selector');
            if (selAll) {
                selAll.addEventListener('change', function () {
                    selAll.indeterminate = false;
                    document.querySelectorAll('.campaign-bulk-cb').forEach(function (cb) { cb.checked = selAll.checked; });
                    sync();
                });
            }
            sync();
            syncSelectAllHeader();
        })();
    </script>
@endpush
