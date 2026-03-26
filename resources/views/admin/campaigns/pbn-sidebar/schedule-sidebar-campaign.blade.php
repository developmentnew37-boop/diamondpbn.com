@extends('admin.layout.layout')

@section('title', 'Sidebar Campaigns')

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
                        <a href="{{ route('admin.schedule.sidebar.campaign.index') }}" class="breadcrumb-link">Schedule
                            Blogroll</a>
                    </div>

                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                @if (Auth::guard('admin')->user()->canCreateCampaigns())
                <a href="{{ route('admin.schedule.sidebar.campaign.create') }}"
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

        <div class="flex flex-wrap items-center content-card w-full">

            <div class="w-[65%] flex flex-wrap gap-2">

                <div class="w-1/5">
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
                <div class="w-3/5">
                    <form action="#" class="w-full flex flex-wrap justify-start items-center gap-1" method="post">
                        @csrf
                        <select name="actions" id=""
                            class="bg-gray-100 border border-gray-200 !w-2/5 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                            <option value="">Bulk actions</option>
                            <option value="1">Delete</option>
                        </select>
                        <input type="hidden" name="bulk_ids" id="valHolders">
                        <button type="submit"
                            class="flex !p-3  !px-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                            Apply
                        </button>
                    </form>


                </div>

            </div>
            <div class="w-[35%] flex flex-wrap gap-3 justify-end">

                {{-- Search Box --}}

                <div class="relative w-1/2 max-h-12 overflow-hidden">
                    <form method="GET" action="{{ url()->current() }}" class="relative w-full">

                        {{-- keep other parameters --}}
                        @foreach (request()->except('search') as $key => $value)
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
        @csrf
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">Sidebar Campaigns
        </h2>
        {{-- xxxxxxxxxxxxxxxxxx campaigns button xxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}

        {{-- table code here --}}

        <div class="overflow-x-auto !mt-3 w-full">
            <table class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th>
                            <input type="checkbox" id="bulk-checkBox-selector" class="scale-125">
                        </th>

                        @php
                            // $tHead = [
                            //     'sno',
                            //     'Campaign No',
                            //     'Type',
                            //     'Domain Category',
                            //     'Sidebar Links',
                            //     'Domains',
                            //     'Completed',
                            //     'Failed',
                            //     'Pending',
                            //     'Progress',
                            //     'Status',
                            //     'Created At',
                            //     'Actions',
                            // ];
                            $tHead = [
                                'sno',
                                'Campaign No',
                                'Domain Category',
                                'Links',
                                'Domains',
                                'Total',
                                'Completed',
                                'Failed',
                                'Pending',
                                'Progress',
                                'Status',
                                'Schedule From',
                                'Schedule To',
                                'Started At',
                                'Finished At',
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

                            $displayStatus = $campaign->status;
                            if ($total > 0 && $completed === $total && $failed === 0) {
                                $displayStatus = 'completed';
                            }

                            $statusClass = match ($displayStatus) {
                                'queued' => 'bg-gray-100 text-gray-600',
                                'running' => 'bg-yellow-100 text-yellow-700',
                                'paused' => 'bg-orange-100 text-orange-700',
                                'completed' => 'bg-green-100 text-green-700',
                                'failed' => 'bg-red-100 text-red-700',
                                'semi_failed' => 'bg-amber-100 text-amber-700',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp

                        <tr class="hover:bg-gray-50">

                            {{-- checkbox --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                <input type="checkbox" class="multi-check" value="{{ $campaign->id }}">
                            </td>

                            {{-- sno --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            {{-- campaign no --}}
                            <td class="border border-gray-200 !px-2 !py-3 font-semibold">
                                {{ $campaign->campaign_no }}
                            </td>

                            {{-- domain category --}}
                            <td class="border border-gray-200 !px-2 !py-3">
                                {{ optional($campaign->domainCategory)->name ?? '-' }}
                            </td>

                            {{-- links --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $campaign->links_count ?? $campaign->links->count() }}
                            </td>

                            {{-- domains --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $campaign->domains_count ?? $campaign->domains->count() }}
                            </td>

                            {{-- total --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center">
                                {{ $total }}
                            </td>

                            {{-- completed --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center text-green-600">
                                {{ $completed }}
                            </td>

                            {{-- failed --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-center text-red-600">
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
                                    {{ ucfirst(str_replace('_', ' ', $displayStatus)) }}
                                </span>
                            </td>

                            {{-- schedule --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-xs">
                                {{ $campaign->schedule_from_date?->format('d M Y') ?? '-' }}

                            </td>
                            <td class="border border-gray-200 !px-2 !py-3 text-xs">

                                {{ $campaign->schedule_to_date?->format('d M Y') ?? '-' }}
                            </td>

                            {{-- started --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-xs">
                                {{ $campaign->started_at?->format('d M Y H:i') ?? '-' }}
                            </td>

                            {{-- finished --}}
                            <td class="border border-gray-200 !px-2 !py-3 text-xs">
                                {{ $campaign->finished_at?->format('d M Y H:i') ?? '-' }}
                            </td>

                            {{-- actions --}}
                            <td class="border border-gray-200 !px-2 !py-3">
                                <div class="flex  gap-1 justify-center">
                                    <a href="{{ route('admin.schedule.sidebar.campaign.show', $campaign->id) }}"
                                        class="bg-green-500 w-7 h-7 flex items-center justify-center rounded hover:bg-green-600" title="View campaign">
                                        <span class="material-symbols-outlined text-white !text-sm">visibility</span>
                                    </a>
                                    <a href="{{ route('admin.schedule.sidebar.campaign.edit', $campaign->id) }}"
                                        class="bg-black w-7 h-7 flex items-center justify-center rounded hover:bg-amber-600" title="Edit campaign">
                                        <span class="material-symbols-outlined text-white !text-sm">edit</span>
                                    </a>
                                    <a href="javascript:void(0)"
                                        data-report="{{ route('admin.schedule.sidebar.campaign.report', [
                                            'campaign_no' => $campaign->campaign_no,
                                            'token' => $campaign->report_token,
                                        ]) }}"
                                        class="bg-yellow-500 copy-link flex items-center justify-center rounded w-7 h-7 hover:bg-yellow-600" title="Copy report link">
                                        <span class="material-symbols-outlined !text-sm text-white">content_copy</span>
                                    </a>
                                    <a href="{{ route('admin.schedule.sidebar.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]) }}"
                                        target="_blank"
                                        class="bg-blue-600 w-7 h-7 flex items-center justify-center rounded hover:bg-blue-700" title="Open report">
                                        <span class="material-symbols-outlined text-white !text-sm">assignment</span>
                                    </a>
                                    <form action="{{ route('admin.schedule.sidebar.campaign.destroy', $campaign->id) }}" method="post" class="inline"
                                        onsubmit="return confirm('Delete this campaign? All blogroll links will be removed from remote sites and from the database.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 w-7 h-7 flex items-center justify-center rounded hover:bg-red-600 border-0 cursor-pointer" title="Delete campaign">
                                            <span class="material-symbols-outlined text-white !text-sm">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="15" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No scheduled sidebar campaigns found...
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
@endpush
