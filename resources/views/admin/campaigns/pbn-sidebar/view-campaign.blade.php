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
                            <a href="{{ route('admin.sidebar.campaign.index') }}" class="breadcrumb-link">PBN Post</a>
                        </div>

                    </div>
                </div>
                <div class="w-1/2 flex flex-wrap justify-end items-center">
                    {{-- <a href="{{ url()->previous() }}"
                        class="flex !p-2  text-[16px] font-normal w-fit justify-center duration:300 bg-black 
                        whitespace-nowrap hover:bg-[var(--primary-color)] text-white rounded transition-all duration">
                        Back</a> --}}
                    {{-- <a href="{{ url()->previous() ?: route('admin.sidebar.campaign.index') }}" --}}
                    <a href="javascript:void(0)" onclick="history.back()"
                        class="inline-flex items-center gap-2 !px-3 !py-2 rounded bg-gray-200 duration-400 hover:bg-gray-300 text-sm">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                            class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 19l-7-7 7-7M3 12h18" />
                        </svg>
                        Back
                    </a>

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



        </div>


        {{-- ******************* Ends here  ***************** --}}

        <div class="w-full flex flex-wrap justify-between items-start content-card">
            @csrf
            <div class="flex flex-wrap items-center gap-2 !mb-4">
                <h2 class="text-lg capitalize bg-[var(--primary-color)] text-white w-fit !p-3 rounded">
                    {{ $campaign->campaign_no }} Blogroll Campaigns
                </h2>
                @if ($campaign->last_bulk_updated_at)
                    <span class="!px-2 !py-1 rounded text-xs font-semibold bg-green-100 text-green-700">
                        Campaign updated
                    </span>
                @endif
                @if ($hasPublishedLinks)
                    <a href="{{ route('admin.sidebar.campaign.edit', $campaign->id) }}"
                        class="!px-3 !py-2 rounded bg-green-600 text-white text-sm hover:bg-green-700">
                        Bulk edit links
                    </a>
                @endif
            </div>

            {{-- Bulk delete form - above table --}}
            <form id="bulk-delete-form" action="{{ route('admin.sidebar.campaign.bulk.delete.tasks', $campaign->id) }}" method="POST" class="w-full !mb-3 hidden">
                @csrf
                <div id="bulk-delete-task-ids-container"></div>
                <button type="submit" class="!px-3 !py-2 rounded bg-red-600 text-white text-sm hover:bg-red-700"
                    onclick="return confirm('Remove selected links from remote sites and from the database?');">
                    Bulk delete selected
                </button>
            </form>

            <div class="overflow-x-auto !mt-3 w-full">
                <table
                    class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                    <thead>
                        <tr class="bg-gray-800 text-white">
                            <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left w-10">
                                <input type="checkbox" id="select-all-tasks" title="Select all">
                            </th>
                            @php
                                $tHead = [
                                    'S.No',
                                    'Campaign No',
                                    'Domain',
                                    'Keyword',
                                    'Anchor',
                                    'Nofollow',
                                    'Remote ID',
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
                                <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                    {{ $t }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaignTasks as $index => $task)
                            <tr class="hover:bg-gray-50">
                                {{-- Bulk delete checkbox --}}
                                <td class="border !px-2 !py-2 text-center">
                                    <input type="checkbox" class="task-checkbox" value="{{ $task->id }}" data-task-id="{{ $task->id }}">
                                </td>
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
                                    {{ optional($task->domainRow?->domain)->name ?? '-' }}
                                </td>

                                {{-- Target URL --}}
                                <td class="border !px-2 !py-2 max-w-[240px] truncate"
                                    title="{{ $task->linkRow?->target_url }}">
                                    {{ $task->linkRow?->target_url ?? '-' }}
                                </td>

                                {{-- Anchor --}}
                                <td class="border !px-2 !py-2">
                                    {{ $task->linkRow?->anchor_keyword ?? '-' }}
                                </td>

                                {{-- Nofollow --}}
                                <td class="border !px-2 !py-2 text-center">
                                    {{ $task->linkRow?->nofollow ? 'Yes' : 'No' }}
                                </td>

                                {{-- Remote ID --}}
                                <td class="border !px-2 !py-2">
                                    {{ $task->remote_id ?? '-' }}
                                </td>

                                {{-- Remote URL --}}
                                <td class="border !px-2 !py-2 text-center">
                                    @if ($task->remote_url)
                                        <a href="{{ $task->remote_url }}" target="_blank"
                                            class="bg-yellow-500 text-white rounded px-2 py-1 text-xs">
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

                                {{-- Last Error --}}
                                <td class="border !px-2 !py-2 max-w-[260px] truncate" title="{{ $task->last_error }}">
                                    {{ $task->last_error ?? '-' }}
                                </td>

                                {{-- Next Retry --}}
                                <td class="border !px-2 !py-2">
                                    {{ optional($task->next_retry_at)?->format('d M Y H:i') ?? '-' }}
                                </td>

                                {{-- Status: show "Updated" when task was updated (single or bulk) --}}
                                <td class="border !px-2 !py-2 text-center">
                                    @php
                                        $statusMap = [
                                            'queued' => 'bg-gray-100 text-gray-700',
                                            'publishing' => 'bg-yellow-100 text-yellow-700',
                                            'success' => 'bg-green-100 text-green-700',
                                            'failed' => 'bg-red-100 text-red-700',
                                        ];
                                        $statusLabel = ($task->status === 'success' && $task->content_updated_at)
                                            ? 'Updated'
                                            : ucfirst($task->status);
                                    @endphp
                                    <span
                                        class="!px-2 !py-1 rounded text-xs font-semibold {{ $statusMap[$task->status] ?? '' }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                {{-- Created At --}}
                                <td class="border !px-2 !py-2">
                                    {{ $task->created_at->format('d M Y H:i') }}
                                </td>

                                {{-- Actions --}}
                                <td class="border !px-2 !py-2">
                                    <div class="flex gap-2 justify-center">
                                        @if ($task->status !== 'success')
                                            <a href="{{ route('admin.sidebar.campaign.retry.task', $task->id) }}"
                                                class="bg-orange-500 rounded w-7 h-7 flex items-center justify-center"
                                                title="Manual retry from first">
                                                <span class="material-symbols-outlined text-white text-sm">refresh</span>
                                            </a>
                                        @endif
                                        @if ($task->remote_id)
                                            <a href="{{ route('admin.sidebar.campaign.edit.task', $task->id) }}"
                                                class="bg-yellow-500 rounded w-7 h-7 flex items-center justify-center"
                                                title="Update keyword/link on remote and in DB">
                                                <span class="material-symbols-outlined text-white !text-sm">edit</span>
                                            </a>
                                        @endif
                                        <a href="{{ route('admin.sidebar.campaign.delete.task', $task->id) }}"
                                            class="bg-red-500 rounded w-7 h-7 flex items-center justify-center"
                                            title="Delete this link (remote + DB)"
                                            onclick="return confirm('Remove this sidebar link from the remote site and database?');">
                                            <span class="material-symbols-outlined text-white !text-sm">delete</span>
                                        </a>
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="15" class="text-center !py-4 text-gray-500 bg-gray-100">
                                    No sidebar links found…
                                </td>
                            </tr>
                        @endforelse
                    </tbody>

                </table>
            </div>

            <div class="w-full !mt-2">
                {{ $campaignTasks->links() }}
            </div>
        </div>


    @endsection




    @push('scripts')
        <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
        <script>
            (function() {
                var selectAll = document.getElementById('select-all-tasks');
                var checkboxes = document.querySelectorAll('.task-checkbox');
                var form = document.getElementById('bulk-delete-form');
                var container = document.getElementById('bulk-delete-task-ids-container');

                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
                        toggleBulkDeleteButton();
                    });
                }
                checkboxes.forEach(function(cb) {
                    cb.addEventListener('change', toggleBulkDeleteButton);
                });
                function toggleBulkDeleteButton() {
                    var checked = document.querySelectorAll('.task-checkbox:checked');
                    if (form) {
                        form.classList.toggle('hidden', checked.length === 0);
                    }
                }
                if (form) {
                    form.addEventListener('submit', function() {
                        var checked = document.querySelectorAll('.task-checkbox:checked');
                        if (container) {
                            container.innerHTML = '';
                            checked.forEach(function(cb) {
                                var inp = document.createElement('input');
                                inp.type = 'hidden';
                                inp.name = 'task_ids[]';
                                inp.value = cb.value;
                                container.appendChild(inp);
                            });
                        }
                    });
                }
            })();
        </script>
    @endpush
