@extends('admin.layout.layout')

@section('title', 'Scheduled Sidebar Campaign')

@section('main-content')

    {{-- ================= Breadcrumbs ================= --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.schedule.sidebar.campaign.index') }}" class="breadcrumb-link">
                            Schedule Blogroll
                        </a>
                    </div>
                </div>
            </div>

            <div class="w-1/2 flex justify-end items-center gap-2">
                <a href="{{ route('admin.schedule.sidebar.campaign.edit', $campaign->id) }}"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-[var(--primary-color)] text-white hover:opacity-90 text-sm">
                    Edit campaign
                </a>
                <form action="{{ route('admin.schedule.sidebar.campaign.destroy', $campaign->id) }}" method="post" class="inline"
                    onsubmit="return confirm('Delete this campaign? All blogroll links will be removed from remote sites and from the database.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-red-600 text-white hover:bg-red-700 text-sm">
                        Delete campaign
                    </button>
                </form>
                <form action="{{ route('admin.schedule.sidebar.campaign.purge.local', $campaign->id) }}" method="post" class="inline"
                    onsubmit="return confirm('Remove this campaign from the dashboard only? Remote blogroll links stay. You will not be able to edit this campaign here anymore.');">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-orange-600 text-white hover:bg-orange-700 text-sm">
                        Remove locally
                    </button>
                </form>
                <a href="javascript:void(0)" onclick="history.back()"
                    class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        class="w-5 h-5">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7 7-7M3 12h18" />
                    </svg>
                    Back
                </a>
            </div>
        </div>
    </div>

    {{-- ================= Alerts ================= --}}
    <div class="w-full !mt-3">
        @if (session('cus__success'))
            <div class="!p-4 !mb-2 text-sm rounded bg-green-100 text-green-700">
                {{ session('cus__success') }}
            </div>
        @endif

        @if (session('cus__error'))
            <div class="!p-4 !mb-2 text-sm rounded bg-red-100 text-red-700">
                {{ session('cus__error') }}
            </div>
        @endif
    </div>

    @if (! empty($isConvertedLiveCampaign) && filled($campaign->converted_from_sidebar_campaign_id))
        <div class="content-card !mt-4 !p-4 bg-orange-50 border border-orange-200 text-sm">
            <strong>Converted from live sidebar campaign</strong>
            @if ($campaign->sourceSidebarCampaign)
                ({{ $campaign->sourceSidebarCampaign->campaign_no }})
            @endif
            · Pipeline: {{ $campaign->conversion_pipeline_status ?? '—' }}
            @if ($campaign->conversion_run_date)
                · Conversion day: {{ \Carbon\Carbon::parse($campaign->conversion_run_date)->format('d M Y') }}
            @endif
            <form action="{{ route('admin.convert.sidebar.bulk-retry', $campaign->id) }}" method="post" class="inline !ml-3">
                @csrf
                <button type="submit" class="text-[var(--primary-color)] underline bg-transparent border-0 cursor-pointer">Retry failed conversion tasks</button>
            </form>
            <span class="text-gray-600">· Due slots publish on WordPress automatically; future slots stay hidden until their schedule date. WordPress status refreshes when you open this page.</span>
        </div>
    @endif

    {{-- ================= Campaign Header ================= --}}
    @include('admin.campaigns.partials.local-client-billing')
    <div class="content-card w-full">

        <h2 class="text-lg !mb-4 bg-[var(--primary-color)] text-white !px-4 !py-2 rounded w-fit">
            {{ $campaign->campaign_no }} — Scheduled Sidebar Campaign
        </h2>

        @include('admin.campaigns.partials.post-status-filters', [
            'routeName' => 'admin.schedule.sidebar.campaign.show',
            'routeParameter' => 'schedule',
            'campaign' => $campaign,
            'statusFilter' => $statusFilter,
            'statusCounts' => $statusCounts,
        ])

        {{-- ================= Tasks Table ================= --}}
        <div class="overflow-x-auto mt-4">
            <table class="display w-full border border-gray-200 text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        @php
                            $tHead = [
                                'S.No',
                                'Campaign No',
                                'Domain',
                                'Target URL',
                                'Anchor',
                                'Nofollow',
                                'Remote ID',
                                'Remote URL',
                                'Attempts',
                                'Schedule At',
                                'Last Error',
                                'Next Retry',
                                'Status',
                                'Created At',
                                'Actions',
                            ];
                        @endphp

                        @foreach ($tHead as $t)
                            <th class="border border-gray-200 !px-2 !py-3 font-normal text-left">
                                {{ $t }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @forelse ($campaignTasks as $index => $task)
                        <tr class="hover:bg-gray-50">

                            {{-- S.No --}}
                            <td class="border !px-2 !py-2 text-center">
                                {{ $index + 1 + $offset }}
                            </td>

                            {{-- Campaign No --}}
                            <td class="border !px-2 !py-2">
                                {{ $campaign->campaign_no }}
                            </td>

                            {{-- Domain --}}
                            <td class="border !px-2 !py-2">
                                {{ optional($task->domain?->domain)->name ?? '-' }}
                            </td>

                            {{-- Target URL --}}
                            @php
                                $targetUrlCell = \App\Support\ReportDisplay::url($task->link?->target_url);
                                $anchorCell = \App\Support\ReportDisplay::keyword($task->link?->anchor_keyword);
                            @endphp
                            <td class="border !px-2 !py-2 max-w-[260px] truncate" title="{{ $targetUrlCell['title'] }}">
                                {{ $targetUrlCell['display'] }}
                            </td>

                            {{-- Anchor --}}
                            <td class="border !px-2 !py-2" title="{{ $anchorCell['title'] }}">
                                {{ $anchorCell['display'] }}
                            </td>

                            {{-- Nofollow --}}
                            <td class="border !px-2 !py-2 text-center">
                                {{ $task->link?->nofollow ? 'Yes' : 'No' }}
                            </td>

                            {{-- Remote ID --}}
                            <td class="border !px-2 !py-2">
                                {{ $task->remote_id ?? '-' }}
                            </td>

                            {{-- Remote URL (View opens the PBN domain, not the outbound target link) --}}
                            @php
                                $domainName = optional($task->domain?->domain)->name;
                                $domainViewUrl = $domainName
                                    ? (preg_match('~^https?://~i', $domainName) ? $domainName : 'https://'.$domainName)
                                    : null;
                            @endphp
                            <td class="border !px-2 !py-2 text-center">
                                @if ($domainViewUrl)
                                    <a href="{{ $domainViewUrl }}" target="_blank" rel="noopener noreferrer"
                                        class="bg-yellow-500 text-white rounded !px-2 !py-1 text-xs hover:bg-yellow-600">
                                        View
                                    </a>
                                @else
                                    -
                                @endif
                            </td>

                            {{-- Attempts --}}
                            <td class="border !px-2 !py-2 text-center">
                                {{ $task->attempt_count }}
                            </td>
                            <td class="border !px-2 !py-2 text-xs">
                                @php
                                    $scheduleDisplay = \App\Support\ScheduleSidebarReportStatus::slotDate($task);
                                @endphp
                                {{ $scheduleDisplay ? \Carbon\Carbon::parse($scheduleDisplay)->format('d M Y') : '-' }}
                            </td>
                            {{-- Last Error --}}
                            <td class="border !px-2 !py-2 max-w-[300px] truncate" title="{{ $task->last_conversion_error ?? $task->last_error }}">
                                {{ $task->last_conversion_error ?? $task->last_error ?? '-' }}
                            </td>

                            {{-- Next Retry --}}
                            <td class="border !px-2 !py-2">
                                {{ $task->next_retry_at?->format('d M Y H:i') ?? '-' }}
                            </td>

                            {{-- Status --}}
                            <td class="border !px-2 !py-2 text-center">
                                @php
                                    if (! empty($isConvertedLiveCampaign) && $task->is_converted_live) {
                                        $reportStatus = \App\Support\ScheduleSidebarReportStatus::forReport($task);
                                        $statusLabel = $reportStatus['label'];
                                        $statusClass = $reportStatus['class'];
                                    } else {
                                        $statusMap = [
                                            'queued' => ['Queued', 'bg-gray-100 text-gray-700'],
                                            'publishing' => ['Publishing', 'bg-yellow-100 text-yellow-700'],
                                            'success' => ['Success', 'bg-green-100 text-green-700'],
                                            'failed' => ['Failed', 'bg-red-100 text-red-700'],
                                        ];
                                        [$statusLabel, $statusClass] = $statusMap[$task->status] ?? [ucfirst($task->status), 'bg-gray-100 text-gray-600'];
                                    }
                                @endphp
                                <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusClass }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>

                            {{-- Created At --}}
                            <td class="border !px-2 !py-2">
                                {{ $task->created_at?->format('d M Y H:i') }}
                            </td>

                            {{-- Actions --}}
                            <td class="border !px-2 !py-2">
                                <div class="flex  gap-1 justify-center items-center">
                                    @if ($task->remote_url)
                                        <a href="{{ $task->remote_url }}" target="_blank"
                                            class="bg-green-500 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-green-600" title="View on site">
                                            <span class="material-symbols-outlined text-white !text-sm">visibility</span>
                                        </a>
                                    @endif
                                    @if ($task->status === 'success' && $task->remote_id)
                                        <a href="{{ route('admin.schedule.sidebar.campaign.edit.task', $task->id) }}"
                                            class="bg-yellow-500 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-amber-600" title="Edit link">
                                            <span class="material-symbols-outlined text-white !text-sm">edit</span>
                                        </a>
                                    @endif
                                    @php
                                        $replaceProfile = $task->is_converted_live
                                            ? \App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile::convertedScheduleSidebar()
                                            : \App\Services\LiveTaskDomainReplacement\LiveTaskReplacementProfile::scheduleSidebar();
                                        $canReplaceDomain = app(\App\Services\LiveTaskDomainReplacement\LiveTaskDomainReplacementService::class)
                                            ->ineligibleReason($replaceProfile, $task) === null;
                                    @endphp
                                    @if ($canReplaceDomain)
                                        <a href="{{ route('admin.schedule.sidebar.campaign.domain-replacement.create', $task->id) }}"
                                            class="bg-blue-600 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-blue-700"
                                            title="Replace domain">
                                            <span class="material-symbols-outlined text-white !text-sm">swap_horiz</span>
                                        </a>
                                    @endif
                                    @if (in_array($task->status, ['queued', 'failed', 'publishing']) || ($task->is_converted_live && in_array($task->conversion_phase, ['failed', 'pending_draft', 'drafted'], true)))
                                        @if (! empty($isConvertedLiveCampaign) && $task->is_converted_live)
                                        <form action="{{ route('admin.convert.sidebar.retry-task', $task->id) }}" method="post" class="inline">
                                        @else
                                        <form action="{{ route('admin.schedule.sidebar.campaign.retry.task', $task->id) }}" method="post" class="inline">
                                        @endif
                                            @csrf
                                            <button type="submit" class="bg-blue-500 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-blue-600 border-0 cursor-pointer" title="Retry">
                                                <span class="material-symbols-outlined text-white !text-sm">replay</span>
                                            </button>
                                        </form>
                                    @endif
                                    <form action="{{ route('admin.schedule.sidebar.campaign.delete.task', $task->id) }}" method="post" class="inline"
                                        onsubmit="return confirm('Delete this link from the campaign and from the remote site?');">
                                        @csrf
                                        <button type="submit" class="bg-red-500 w-7 h-7 inline-flex items-center justify-center rounded hover:bg-red-600 border-0 cursor-pointer" title="Delete">
                                            <span class="material-symbols-outlined text-white !text-sm">delete</span>
                                        </button>
                                    </form>
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="text-center !py-4 text-gray-500 bg-gray-100">
                                No scheduled sidebar tasks found…
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-3">
            {{ $campaignTasks->links() }}
        </div>

    </div>

@endsection

@push('scripts')
     <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>

@endpush
