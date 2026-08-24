@extends('admin.layout.layout')

@section('title', 'Hidden Links Campaign')

@section('main-content')

{{-- bread-crumbs --}}
<div class="page-header w-full max-w-full min-w-0">
    <div class="w-full flex flex-col gap-3">
        <div class="flex items-center justify-between gap-3 min-w-0">
            <h2 class="page-title !mb-0 min-w-0 shrink leading-tight">Dashboards</h2>
            <a href="javascript:void(0)" onclick="history.back()"
                class="inline-flex items-center justify-center gap-2 shrink-0 !px-3 !py-2 rounded bg-gray-200 hover:bg-gray-300 text-sm whitespace-nowrap"
                aria-label="Go back">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 shrink-0" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7 7-7M3 12h18" />
                </svg>
                Back
            </a>
        </div>
        <nav class="flex flex-wrap items-baseline gap-x-1.5 gap-y-2 text-sm text-gray-600 w-full min-w-0 leading-snug"
            aria-label="Breadcrumb">
            <span class="inline-flex flex-wrap items-baseline gap-x-1.5 min-w-0">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link shrink-0">Dashboard</a>
                <span class="text-gray-400 shrink-0" aria-hidden="true">›</span>
            </span>
            <span class="inline-flex flex-wrap items-baseline gap-x-1.5 min-w-0">
                <a href="{{ route('admin.hidden.link.campaign.index') }}" class="breadcrumb-link">PBN Hidden Links</a>
                <span class="text-gray-400 shrink-0" aria-hidden="true">›</span>
            </span>
            <span class="min-w-0 max-w-full text-gray-600 font-mono text-xs sm:text-sm break-all"
                title="{{ $campaign->campaign_no }}">{{ $campaign->campaign_no }}</span>
        </nav>
    </div>
</div>

{{-- alerts --}}
@if (session('cus__success') || session('cus__error'))
    <div class="mt-2">
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

<div class="content-card mt-3">
    @include('admin.campaigns.partials.local-client-billing')

    <div class="flex flex-wrap items-center gap-2 !mb-4">
        <h2 class="text-lg bg-[var(--primary-color)] text-white w-fit !px-3 !py-2 rounded">
            {{ $campaign->campaign_no }} — Hidden Links
        </h2>
        @if ($campaign->last_bulk_updated_at ?? null)
            <span class="!px-2 !py-1 rounded text-xs font-semibold bg-green-100 text-green-700">Campaign updated</span>
        @endif
        <a href="{{ route('admin.hidden.link.campaign.edit', $campaign->id) }}"
            class="!px-3 !py-2 rounded bg-green-600 text-white text-sm hover:bg-green-700">Bulk edit links</a>
    </div>

    <form id="bulk-delete-form" action="{{ route('admin.hidden.link.campaign.bulk.delete.tasks', $campaign->id) }}" method="POST" class="w-full !mb-3 hidden">
        @csrf
        <div id="bulk-delete-task-ids-container"></div>
        <button type="submit" class="!px-3 !py-2 rounded bg-red-600 text-white text-sm hover:bg-red-700"
            onclick="return confirm('Remove selected links from remote sites and database? This cannot be undone.');">Bulk delete selected</button>
    </form>

    @include('admin.campaigns.partials.post-status-filters', [
        'routeName' => 'admin.hidden.link.campaign.show',
        'routeParameter' => 'campaign',
        'campaign' => $campaign,
        'statusFilter' => $statusFilter,
        'statusCounts' => $statusCounts,
    ])

    <div class="overflow-x-auto w-full">
        <table class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
            <thead>
                <tr class="bg-gray-800 text-white">
                    <th class="border border-gray-200 !px-2 !py-3 text-left w-10">
                        <input type="checkbox" id="select-all-tasks" title="Select all">
                    </th>
                    @php
                        $tHead = [
                            'S.No',
                            'Campaign No',
                            'Domain',
                            'Target URL',
                            'Anchor',
                            'Nofollow',
                            'Remote URL',
                            'Attempts',
                            'Last Error',
                            'Next Retry',
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
                @forelse ($campaignTasks as $index => $task)
                    <tr class="hover:bg-gray-50">
                        <td class="border !px-2 !py-2 text-center">
                            <input type="checkbox" class="task-checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}">
                        </td>
                        <td class="border !px-2 !py-2 text-center">
                            {{ $index + 1 + $offset }}
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ $campaign->campaign_no }}
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ $task->domainRow?->domain?->name ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2 max-w-[260px] truncate"
                            title="{{ $task->linkRow?->target_url }}">
                            {{ $task->linkRow?->target_url ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ $task->linkRow?->anchor_keyword ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2 text-center">
                            {{ $task->linkRow?->nofollow ? 'Yes' : 'No' }}
                        </td>


                        <td class="border !px-2 !py-2 text-center">
                            @if ($task->remote_url)
                                <a href="{{ $task->remote_url }}" target="_blank"
                                   class="bg-yellow-500 text-white rounded !px-2 !py-1 text-xs">
                                    View
                                </a>
                            @else
                                -
                            @endif
                        </td>

                        <td class="border !px-2 !py-2 text-center">
                            {{ $task->attempt_count }}
                        </td>

                        <td class="border !px-2 !py-2 max-w-[260px] truncate"
                            title="{{ $task->last_error }}">
                            {{ $task->last_error ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ optional($task->next_retry_at)?->format('d M Y H:i') ?? '-' }}
                        </td>

                        <td class="border !px-2 !py-2 text-center">
                            @php
                                $statusMap = [
                                    'queued' => 'bg-gray-100 text-gray-700',
                                    'publishing' => 'bg-yellow-100 text-yellow-700',
                                    'success' => 'bg-green-100 text-green-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                ];
                                $statusLabel = ($task->status === 'success' && ($task->content_updated_at ?? null)) ? 'Updated' : ucfirst($task->status);
                            @endphp
                            <span class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusMap[$task->status] ?? '' }}">
                                {{ $statusLabel }}
                            </span>
                        </td>

                        <td class="border !px-2 !py-2">
                            {{ $task->created_at?->format('d M Y H:i') }}
                        </td>

                        <td class="border !px-2 !py-2">
                            <div class="flex gap-2 justify-center">
                                @php
                                    $canReplaceDomain = in_array($task->status, ['queued', 'failed'], true)
                                        && ! $task->remote_id
                                        && ! $task->remote_url
                                        && ! $task->published_at
                                        && ! $task->locked_at
                                        && ! $task->lock_token;
                                @endphp
                                @if (in_array($task->status, ['queued', 'failed', 'publishing']))
                                    <a href="{{ route('admin.hidden.link.campaign.retry.task', $task->id) }}"
                                        class="bg-blue-500 rounded w-7 h-7 flex items-center justify-center" title="Retry this task">
                                        <span class="material-symbols-outlined text-white !text-sm">replay</span>
                                    </a>
                                @endif
                                @if ($canReplaceDomain)
                                    <a href="{{ route('admin.hidden.link.campaign.domain-replacement.create', $task->id) }}"
                                        class="bg-blue-600 rounded w-7 h-7 flex items-center justify-center hover:bg-blue-700"
                                        title="Replace domain">
                                        <span class="material-symbols-outlined text-white text-sm">swap_horiz</span>
                                    </a>
                                @endif
                                @if ($task->remote_id)
                                    <a href="{{ route('admin.hidden.link.campaign.edit.task', $task->id) }}"
                                        class="bg-yellow-500 rounded w-7 h-7 flex items-center justify-center" title="Edit keyword/link">
                                        <span class="material-symbols-outlined text-white !text-sm">edit</span>
                                    </a>
                                @endif
                                <form action="{{ route('admin.hidden.link.campaign.delete.task', $task->id) }}" method="POST" class="inline"
                                    onsubmit="return confirm('Remove this hidden link from remote and database? This cannot be undone.');">
                                    @csrf
                                    <button type="submit"
                                        class="bg-red-500 border-0 cursor-pointer rounded w-7 h-7 flex items-center justify-center hover:bg-red-600"
                                        title="Delete this link">
                                        <span class="material-symbols-outlined text-white !text-sm">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="14" class="text-center !py-4 text-gray-500 bg-gray-100">
                            No hidden links tasks found…
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-2">
        {{ $campaignTasks->links() }}
    </div>

</div>

@push('scripts')
<script>
(function() {
    var selectAll = document.getElementById('select-all-tasks');
    var checkboxes = document.querySelectorAll('.task-checkbox');
    var form = document.getElementById('bulk-delete-form');
    var container = document.getElementById('bulk-delete-task-ids-container');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            if (form) form.classList.toggle('hidden', document.querySelectorAll('.task-checkbox:checked').length === 0);
        });
    }
    checkboxes.forEach(function(cb) {
        cb.addEventListener('change', function() {
            if (form) form.classList.toggle('hidden', document.querySelectorAll('.task-checkbox:checked').length === 0);
        });
    });
    if (form && container) {
        form.addEventListener('submit', function() {
            var checked = document.querySelectorAll('.task-checkbox:checked');
            container.innerHTML = '';
            checked.forEach(function(cb) {
                var inp = document.createElement('input');
                inp.type = 'hidden'; inp.name = 'task_ids[]'; inp.value = cb.value;
                container.appendChild(inp);
            });
        });
    }
})();
</script>
@endpush

@endsection
