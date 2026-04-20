@extends('admin.layout.layout')

@section('title', !empty($isStickySchedule) ? 'Schedule Sticky Posts' : 'Scheduled Campaigns')

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
                        <a href="{{ $scheduleListRoute }}" class="breadcrumb-link">
                            {{ !empty($isStickySchedule) ? 'Schedule Sticky Post' : 'Scheduled Posts' }}
                        </a>
                    </div>
                </div>
            </div>

            <div class="w-1/2 flex flex-wrap justify-end items-center">
                @if (Auth::guard('admin')->user()->canCreateCampaigns())
                <a href="{{ $scheduleCreateRoute }}"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-fit justify-center
                    bg-[var(--primary-color)] whitespace-nowrap hover:bg-[var(--primary-color)]/70
                    text-white rounded transition-all">
                    {{ !empty($isStickySchedule) ? 'Create Schedule Sticky Post' : 'Create Schedule Campaign' }}
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- success & error alerts --}}
    <div class="w-full flex flex-col gap-2 items-center !mt-2 !mb-2">
        @if (session('cus__success') || session('cus__error'))
            <div class="w-full flex flex-col gap-2">
                @if (session('cus__success'))
                    <div class="js-cus-alert !p-4 text-sm rounded bg-green-100 text-green-700" role="alert">
                        {{ session('cus__success') }}
                    </div>
                @endif

                @if (session('cus__error'))
                    <div class="js-cus-alert !p-4 text-sm rounded bg-red-100 text-red-700" role="alert">
                        {{ session('cus__error') }}
                    </div>
                @endif
            </div>
        @endif
    </div>  

    {{-- filters --}}
    <div class="flex flex-wrap items-center content-card w-full">

        <div class="w-[65%] flex flex-wrap gap-2">
            <div class="w-1/5">
                <select class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none">
                    <option value="">select</option>
                </select>
            </div>

            <div class="w-3/5">
                <form class="flex gap-1" method="post">
                    @csrf
                    <select class="bg-gray-100 border border-gray-200 !p-3 text-sm w-2/5 rounded outline-none">
                        <option value="">Bulk actions</option>
                        <option value="1">Delete</option>
                    </select>
                    <input type="hidden" id="valHolders">
                    <button type="submit"
                        class="!p-3 !px-4 text-sm bg-[var(--sidebar-bg)]
                        hover:bg-[var(--primary-color)] text-white rounded">
                        Apply
                    </button>
                </form>
            </div>
        </div>

        <div class="w-[35%] flex flex-wrap justify-end items-center gap-3">
            @include('admin.campaigns.partials.campaign-owner-filter')
            <form method="GET" action="{{ url()->current() }}" class="relative flex-1 min-w-[200px] max-w-[50%]">
                @foreach (request()->except('search') as $key => $value)
                    @continue(is_array($value))
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach

                <input type="search" name="search" placeholder="search here" value="{{ request('search') }}"
                    class="bg-gray-100 border border-gray-200 !p-3 !pr-[50px] w-full rounded">

                <button type="submit" class="w-12 h-12 absolute right-0 top-0 bg-[var(--sidebar-bg)] rounded-r">
                    🔍
                </button>
            </form>
        </div>
    </div>

    {{-- table --}}
    <div class="w-full flex flex-wrap justify-between items-start content-card">
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white !p-2 rounded">
            {{ !empty($isStickySchedule) ? 'Schedule Sticky Post Campaigns' : 'Scheduled Campaigns' }}
        </h2>
        <form id="schedule-bulk-purge-local-form" action="{{ route('admin.schedule.campaign.bulk.purge.local') }}" method="POST" class="hidden">@csrf</form>
        <div class="w-full flex flex-wrap items-center gap-2 !mb-2">
            <button type="button" id="schedule-bulk-purge-local-btn"
                class="!px-3 !py-2 rounded bg-orange-600 text-white text-sm hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed"
                title="Remove selected from this app only">
                Bulk remove locally only
            </button>
            <span class="text-sm text-gray-500">Select with checkboxes; does not delete remote posts.</span>
        </div>

        <div class="overflow-x-auto w-full">
            <table class="display w-full border border-gray-200 text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th><input type="checkbox" id="bulk-checkBox-selector" class="scale-125"></th>
                        @php
                            $tHead = [
                                'S.No',
                                'Campaign No',
                                'Type',
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
                            <th class="border !px-2 !py-3 text-left !font-normal">{{ $t }}</th>
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
                        @endphp

                        <tr class="hover:bg-gray-50">
                            <td class="border !px-2 !py-3 text-center">
                                <input type="checkbox" class="multi-check campaign-bulk-cb" name="campaign_ids[]" value="{{ $campaign->id }}">
                            </td>

                            <td class="border !px-2 !py-3 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            <td class="border !px-2 !py-3">
                                {{ $campaign->campaign_no }}
                            </td>

                            <td class="border !px-2 !py-3">
                                {{ !empty($isStickySchedule) ? 'Schedule Sticky Post' : 'Scheduled Campaign' }}
                            </td>

                            <td class="border !px-2 !py-3">
                                {{ optional($campaign->domainCategory)->name ?? '-' }}
                            </td>

                            {{-- Schedule From --}}
                            <td class="border !px-2 !py-3 text-center">
                                {{ optional($campaign->schedule_from_date)?->format('d M Y') ?? '-' }}
                            </td>

                            {{-- Schedule To --}}
                            <td class="border !px-2 !py-3 text-center">
                                {{ optional($campaign->schedule_to_date)?->format('d M Y') ?? '-' }}
                            </td>

                            <td class="border !px-2 !py-3 text-center">{{ $total }}</td>
                            <td class="border !px-2 !py-3 text-center">{{ $completed }}</td>
                            <td class="border !px-2 !py-3 text-center">{{ $failed }}</td>
                            <td class="border !px-2 !py-3 text-center">{{ $pending }}</td>

                            <td class="border !px-2 !py-3 min-w-[140px]">
                                <div class="w-full bg-gray-200 h-2 rounded">
                                    <div class="h-2 rounded
                                        {{ $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                        style="width: {{ $progress }}%">
                                    </div>
                                </div>
                                <div class="text-xs text-center mt-1">{{ $progress }}%</div>
                            </td>

                            <td class="border !px-2 !py-3 text-center">
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-3">
                                {{ $campaign->created_at->format('d-M-Y H:i') }}
                            </td>

                            <td class="border !px-2 !py-3">
                                <div class="flex  gap-2 justify-center">
                                    <a href="{{ route('admin.schedule.campaign.show', $campaign->id) }}"
                                        class="bg-green-500 flex items-center justify-center rounded w-7 h-7 hover:bg-green-600"
                                        title="View campaign">
                                        <span class="material-symbols-outlined !text-sm text-white">visibility</span>
                                    </a>

                                    <a href="{{ route('admin.schedule.campaign.edit', $campaign->id) }}"
                                        class="bg-black flex items-center justify-center rounded w-7 h-7 hover:bg-amber-600"
                                        title="Edit campaign">
                                        <span class="material-symbols-outlined !text-sm text-white">edit</span>
                                    </a>

                                    <a href="javascript:void(0)"
                                        data-report="{{ route('admin.schedule.campaign.report', [
                                            'campaign_no' => $campaign->campaign_no,
                                            'token' => $campaign->report_token,
                                        ]) }}"
                                        class="bg-yellow-500 copy-link flex items-center justify-center rounded w-7 h-7 hover:bg-yellow-600"
                                        title="Copy report link">
                                        <span class="material-symbols-outlined !text-sm text-white">content_copy</span>
                                    </a>

                                    <a href="{{ route('admin.schedule.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]) }}"
                                        target="_blank"
                                        class="bg-blue-700 flex items-center justify-center rounded w-7 h-7 hover:bg-blue-800"
                                        title="Open report">
                                        <span class="material-symbols-outlined !text-sm text-white">assignment</span>
                                    </a>

                                    <form action="{{ route('admin.schedule.campaign.destroy', $campaign->id) }}" method="post" class="inline"
                                        onsubmit="return confirm('Delete this campaign? All posts will be removed from the database and from the remote site.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 flex items-center justify-center rounded w-7 h-7 hover:bg-red-600 border-0 cursor-pointer"
                                            title="Delete campaign">
                                            <span class="material-symbols-outlined !text-sm text-white">delete</span>
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.schedule.campaign.purge.local', $campaign->id) }}" method="post" class="inline"
                                        onsubmit="return confirm('Remove this campaign from the dashboard only? Remote posts stay published. You will not be able to edit this campaign here anymore.');">
                                        @csrf
                                        <button type="submit" class="bg-orange-500 flex items-center justify-center rounded w-7 h-7 hover:bg-orange-600 border-0 cursor-pointer"
                                            title="Dashboard only — does not delete remote posts">
                                            <span class="material-symbols-outlined !text-sm text-white">database</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="15" class="text-center !py-4 text-gray-500 bg-gray-100">
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
            var form = document.getElementById('schedule-bulk-purge-local-form');
            var btn = document.getElementById('schedule-bulk-purge-local-btn');
            if (!form || !btn) return;
            btn.addEventListener('click', function () {
                var ids = Array.prototype.slice.call(document.querySelectorAll('.campaign-bulk-cb:checked')).map(function (cb) { return cb.value; });
                if (ids.length === 0) {
                    alert('Please select at least one campaign.');
                    return;
                }
                if (!confirm('Remove ' + ids.length + ' campaign(s) from this dashboard only? Remote posts will NOT be deleted.')) {
                    return;
                }
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
            function syncPurgeBtn() {
                btn.disabled = document.querySelectorAll('.campaign-bulk-cb:checked').length === 0;
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
                    syncPurgeBtn();
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
                    syncPurgeBtn();
                });
            }
            syncPurgeBtn();
            syncSelectAllHeader();
        })();
    </script>
@endpush
