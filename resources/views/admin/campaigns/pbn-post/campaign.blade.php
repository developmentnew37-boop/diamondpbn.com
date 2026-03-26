@extends('admin.layout.layout')

@section('title', 'Campaigns')

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
                        <a href="{{ route('admin.campaign.index') }}" class="breadcrumb-link">PBN Post</a>
                    </div>

                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                @if (Auth::guard('admin')->user()->canCreateCampaigns())
                <a href="{{ route('admin.campaign.create') }}"
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
            <div class="w-full flex flex-col gap-2 !mb-2">

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
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">Post Campaigns
        </h2>
        {{-- xxxxxxxxxxxxxxxxxx campaigns button xxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}

        {{-- table code here --}}

        <div class="overflow-x-auto !mt-3 w-full">
            <table class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th><input type="checkbox" name="" id="bulk-checkBox-selector" class="scale-125 "></th>
                        @php
                            $tHead = [
                                'sno',
                                'Campaign No',
                                'Type',
                                'Domain Category',
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
                            <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
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

                            // ✅ Status: show "Updated" when campaign was bulk-updated after completion
                            $isBulkUpdated = $campaign->last_bulk_updated_at !== null;
                            if ($pending > 0) {
                                $status = 'running';
                                $statusClass = 'bg-yellow-100 text-yellow-700';
                            } elseif ($failed === $total && $total > 0) {
                                $status = 'failed';
                                $statusClass = 'bg-red-100 text-red-700';
                            } elseif ($completed === $total && $total > 0) {
                                $status = $isBulkUpdated ? 'updated' : 'complete';
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
                            {{-- checkbox --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                <input type="checkbox" class="multi-check" value="{{ $campaign->id }}">
                            </td>

                            {{-- serial no --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            {{-- campaign no --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ $campaign->campaign_no }}
                            </td>

                            {{-- type --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ $campaign->is_sticky_campaign ? 'sticky Campaign' : 'Post Campaign' }}
                            </td>

                            {{-- domain category --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{-- {{ optional($campaign->campaignDomains->first()?->domain)->name ?? '-' }} --}}
                                {{ $campaign->campaignDomain->name ?? '-' }}
                            </td>

                            {{-- totals --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $total }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $completed }}
                            </td>

                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $failed }}
                            </td>

                            {{-- pending --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                {{ $pending }}
                            </td>

                            {{-- progress --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3 min-w-[140px]">
                                <div class="w-full bg-gray-200 rounded h-2">
                                    <div class="h-2 rounded {{ $progress >= 80 ? 'bg-green-500' : ($progress >= 50 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                        style="width: {{ $progress }}%">
                                    </div>
                                </div>
                                <div class="text-[11px] text-gray-600 text-center mt-1">
                                    {{ $progress }}%
                                </div>
                            </td>

                            {{-- status --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3 text-center">
                                <span class="!x-2 !p-2 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            {{-- created --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                {{ $campaign->created_at->format('d-M-Y H:i') }}
                            </td>

                            {{-- actions --}}
                            <td class="border border-gray-200 font-sans !px-2 !py-3">
                                <div class="flex flex-wrap gap-2 justify-center">
                                    {{-- {{ route('admin.campaigns.report', $campaign->id) }} --}}
                                    <a href="{{ route('admin.campaign.show', $campaign->id) }}"
                                        class="bg-green-500 flex items-center justify-center rounded w-7 h-7 hover:bg-green-600">
                                        <span class="material-symbols-outlined !text-sm text-white">visibility</span>
                                    </a>

                                    <a href="javascript:void(0)"
                                        data-report="{{ route('admin.campaign.report', [
                                            'campaign_no' => $campaign->campaign_no,
                                            'token' => $campaign->report_token,
                                        ]) }}"
                                        class="bg-yellow-500 copy-link flex items-center justify-center rounded w-7 h-7 hover:bg-yellow-600">
                                        <span class="material-symbols-outlined !text-sm text-white">content_copy</span>
                                    </a>
                                    <a href="{{ route('admin.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]) }}"
                                        class="bg-blue-700 flex items-center justify-center rounded w-7 h-7 hover:bg-blue-800">
                                        <span class="material-symbols-outlined !text-sm text-white">assignment</span>
                                    </a>

                                    <a href="{{ route('admin.campaign.edit', $campaign->id) }}"
                                        class="bg-gray-700 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-gray-700"
                                        title="Edit campaign">
                                        <span class="material-symbols-outlined !text-[16px] text-white">edit_square</span>
                                    </a>
                                    <form action="{{ route('admin.campaign.destroy', $campaign->id) }}" method="POST"
                                        class="inline"
                                        onsubmit="return confirm('Delete this campaign? All campaign posts will be removed from the database and from remote sites.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 flex items-center justify-center rounded w-7 h-7 hover:bg-red-600"
                                            title="Delete campaign and all posts (DB + remote)">
                                            <span class="material-symbols-outlined !text-[16px] text-white">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                    @empty
                        <tr>
                            <td colspan="14" class="text-center !py-4 text-gray-500 bg-gray-100 font-sans">
                                No campaigns found...
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
