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
                <a href="{{ route('admin.hidden.link.campaign.create') }}"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-fit justify-center
               bg-[var(--primary-color)] whitespace-nowrap text-white rounded
               hover:bg-[var(--primary-color)]/70 transition-all">
                    Create Campaign
                </a>
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

        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">
            Hidden Links Campaigns
        </h2>

        <div class="overflow-x-auto w-full">
            <table class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                <thead>
                    <tr class="bg-gray-800 text-white">
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

                            if ($pending > 0 && $completed > 0) {
                                $status = 'running';
                                $statusClass = 'bg-yellow-100 text-yellow-700';
                            } elseif ($completed + $failed === $total && $failed === 0 && $total > 0) {
                                $status = 'completed';
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
                                    {{ ucfirst($status) }}
                                </span>
                            </td>

                            <td class="border !px-2 !py-2">
                                {{ $campaign->created_at?->format('d-M-Y H:i') }}
                            </td>

                            <td class="border !px-2 !py-2">
                                <div class="flex gap-2 justify-center">
                                    {{-- {{ route('admin.hidden.link.campaign.show', $campaign->id) }} --}}
                                    <a href="{{ route('admin.hidden.link.campaign.show', $campaign->id) }}"
                                        class="bg-green-500 w-7 h-7 flex items-center justify-center rounded">
                                        <span class="material-symbols-outlined text-white !text-sm">visibility</span>
                                    </a>

                                    <a href="javascript:void(0)"
                                        data-report="{{ route('admin.hidden.link.campaign.report', [
                                            'campaign_no' => $campaign->campaign_no,
                                            'token' => $campaign->report_token,
                                        ]) }}"
                                        class="bg-yellow-500 copy-link flex items-center justify-center rounded w-7 h-7 hover:bg-yellow-600">
                                        <span class="material-symbols-outlined !text-sm text-white">content_copy</span>
                                    </a>
                                    <a href="{{ route('admin.hidden.link.campaign.report', [
                                        'campaign_no' => $campaign->campaign_no,
                                        'token' => $campaign->report_token,
                                    ]) }}"
                                        class="bg-blue-600 w-7 h-7 flex items-center justify-center rounded">
                                        <span class="material-symbols-outlined text-white !text-sm">assignment</span>
                                    </a>
                                </div>
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
@endpush
