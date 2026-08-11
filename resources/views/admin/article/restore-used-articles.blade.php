@extends('admin.layout.layout')

@section('title', 'Restore Articles')

@section('main-content')

    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Restore Articles</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.article.index') }}" class="breadcrumb-link">Articles</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Restore Articles</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                <a href="{{ route('admin.article.index') }}"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 bg-gray-700 hover:bg-gray-600 text-white rounded">
                    Back to articles
                </a>
                <a href="{{ route('admin.article.trashed.index') }}"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 border border-red-600 text-red-700 hover:bg-red-50 rounded">
                    Delete Articles
                </a>
            </div>
        </div>
    </div>

    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1 !px-1">
            @if (session()->has('cus__success'))
                <div class="w-full content-card ">
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium">{{ session('cus__success') }}</span>
                    </div>
                </div>
            @endif
            @if (session()->has('cus__error'))
                <div class="w-full content-card">
                    <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium">{{ session('cus__error') }}</span>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="w-full flex flex-wrap gap-8 justify-center">
        <div class="w-full flex flex-wrap gap-4 justify-center">
            <div class="w-full mx-auto content-card">
                <div class="px-6 pt-6 flex flex-col gap-3 justify-between">
                    <h2 class="text-xl bg-green-600 text-white !p-2 rounded font-semibold capitalize w-fit">
                        Restore articles to library
                    </h2>

                    <div class="text-sm text-gray-700 !p-3 rounded border border-green-200 bg-green-50">
                        This list shows articles that were <strong>used in campaigns</strong> and then soft-deleted.
                        <strong>Restore</strong> moves them back to the normal Articles list as <strong>unused</strong>
                        so they can be selected for new campaigns. Restore runs in the background (queue
                        <code class="text-xs bg-white px-1 rounded">article_restore</code>). Keep your queue worker running.
                    </div>

                    <div
                        class="flex flex-wrap items-start gap-4 !py-3 border-b border-gray-200 !pb-4 w-full">
                        <form action="{{ route('admin.article.restore-used.queue-restore-by-quantity') }}" method="post"
                            class="flex flex-wrap items-end gap-3"
                            onsubmit="var el=document.getElementById('restore-qty-input'); var q=el?parseInt(el.value,10):0; return confirm('Queue restore of up to '+q+' deleted used article(s) matching current filters (newest deleted first)?');">
                            @csrf
                            @foreach (request()->only(['search', 'category', 'language']) as $k => $v)
                                @if ($v !== null && $v !== '')
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endif
                            @endforeach
                            <div class="flex flex-col gap-1">
                                <label for="restore-qty-input" class="text-sm font-medium text-gray-700">Restore by
                                    quantity</label>
                                <input id="restore-qty-input" type="number" name="quantity" min="1" max="500000"
                                    required value="{{ old('quantity') }}" placeholder="e.g. 5000"
                                    class="border border-gray-300 rounded !px-3 !py-2 text-sm w-40"
                                    inputmode="numeric" autocomplete="off">
                            </div>
                            <button type="submit"
                                class="flex !p-3 !px-4 text-sm font-normal justify-center bg-green-600 hover:bg-green-700 text-white rounded cursor-pointer">
                                Queue restore by quantity
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 max-w-lg !pb-1">
                            Uses the <strong>same category, language, and search</strong> as the table below. Picks the
                            <strong>most recently deleted</strong> rows first. If fewer rows match than the number you enter,
                            only those are queued.
                            @if (($totalTrashedUsedMatchingFilters ?? 0) > 0)
                                <span class="block !mt-1 font-medium text-gray-600">{{ (int) $totalTrashedUsedMatchingFilters }}
                                    match current filters.</span>
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 !py-2">
                        <form action="{{ route('admin.article.restore-used.queue-restore-all') }}" method="post"
                            class="inline-flex"
                            onsubmit="return confirm('Queue restore of ALL {{ (int) ($totalTrashedUsedForRestoreAll ?? 0) }} deleted used article(s) in your scope? This ignores search/category filters on this page.');">
                            @csrf
                            <button type="submit"
                                class="flex !p-3 !px-4 text-sm font-normal justify-center border-2 border-green-600 text-green-700 bg-white hover:bg-green-50 rounded cursor-pointer">
                                Queue restore all deleted used
                                @if (($totalTrashedUsedForRestoreAll ?? 0) > 0)
                                    <span class="!ml-2 font-semibold">({{ (int) $totalTrashedUsedForRestoreAll }})</span>
                                @endif
                            </button>
                        </form>
                        <span class="text-xs text-gray-500 max-w-xl">Restores every soft-deleted <strong>used</strong>
                            article you are allowed to see (your articles, or all if super admin).</span>
                    </div>

                    <div class="w-full flex flex-wrap items-center !mt-2">
                        <div class="w-[70%] flex flex-wrap gap-2">
                            <div class="w-1/5">
                                <select name="" id="article_category"
                                    class="bg-gray-100 border border-gray-200 !w-full !p-3 text-sm w-full rounded outline-none focus:border-green-600">
                                    <option value="">select category</option>
                                    @if (isset($categories) && count($categories) > 0)
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ request('category') == $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="w-1/5">
                                <select name="" id="article_langauge"
                                    class="bg-gray-100 border border-gray-200 !w-full !p-3 text-sm w-full rounded outline-none focus:border-green-600">
                                    <option value="">select language</option>
                                    @if (isset($languages) && count($languages) > 0)
                                        @foreach ($languages as $language)
                                            <option value="{{ $language->id }}"
                                                {{ request('language') == $language->id ? 'selected' : '' }}>
                                                {{ $language->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="w-2/5 flex flex-wrap items-center gap-2 min-w-[220px]">
                                <span class="text-sm text-gray-600 whitespace-nowrap">Bulk (queue)</span>
                                <form action="{{ route('admin.article.restore-used.restore-bulk') }}"
                                    class="flex flex-wrap items-center gap-2" method="post"
                                    id="restore-bulk-form"
                                    onsubmit="var h=document.getElementById('restoreBulkIds'); if(!h||!h.value.trim()){alert('Select at least one article.');return false;} return confirm('Queue restore of the selected deleted used articles?');">
                                    @csrf
                                    <input type="hidden" name="bulk_ids" id="restoreBulkIds" value="">
                                    <button type="submit"
                                        class="flex !p-3 !px-4 text-sm font-normal justify-center duration-600 transition-all bg-green-600 hover:bg-green-700 text-white rounded cursor-pointer">
                                        Restore selected
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="w-[30%] flex flex-wrap gap-3 justify-end">
                            <div class="relative w-1/2 max-h-12 overflow-hidden">
                                <form method="GET" action="{{ url()->current() }}" class="relative w-full">
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

                    <div class="flex flex-wrap overflow-x-auto !mt-6">
                        <table
                            class="w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                            <thead>
                                <tr class="bg-black text-white">
                                    <th class="border border-gray-200 !px-2 !py-3">
                                        <input type="checkbox" id="bulk-checkBox-selector-restore" class="text-lg">
                                    </th>
                                    <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">Sno</th>
                                    <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">Title</th>
                                    <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">Category</th>
                                    <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">Language</th>
                                    <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">Status</th>
                                    <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">Deleted</th>
                                    <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">User</th>
                                    <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($articles as $index => $article)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 font-sans !px-2 !py-2 text-center">
                                            <input type="checkbox" class="multi-check-restore" value="{{ $article->id }}">
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $index + 1 + $offset }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $article->name }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->category->name ?? '-' }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->language->name ?? '-' }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            <span
                                                class="flex !px-3 !py-2 bg-red-100 text-red-600 rounded text-sm w-fit">used</span>
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->deleted_at ? $article->deleted_at->format('d-m-Y H:i') : '—' }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->admin->name ?? '—' }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            <form action="{{ route('admin.article.restore-used.restore', $article->id) }}"
                                                method="POST" class="inline-flex"
                                                onsubmit="return confirm('Queue restore of this deleted used article back to the library?');">
                                                @csrf
                                                <button type="submit"
                                                    class="bg-green-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-green-700 cursor-pointer"
                                                    title="Restore to library">
                                                    <span class="material-symbols-outlined !text-[16px] text-white">
                                                        restore
                                                    </span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="bg-gray-100 !p-3 text-center">No deleted <strong>used</strong>
                                            articles available to restore.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="w-full">
                        {{ $articles->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>
    <script>
    (function () {
        function initRestoreArticleBulkCheckboxes() {
            var bulk = document.getElementById('bulk-checkBox-selector-restore');
            var hidden = document.getElementById('restoreBulkIds');
            if (!bulk || !hidden) return;
            var boxes = document.querySelectorAll('.multi-check-restore');
            function updateHidden() {
                var vals = [];
                for (var i = 0; i < boxes.length; i++) {
                    if (boxes[i].checked) vals.push(boxes[i].value);
                }
                hidden.value = vals.join(',');
            }
            bulk.addEventListener('change', function () {
                for (var j = 0; j < boxes.length; j++) {
                    boxes[j].checked = bulk.checked;
                }
                updateHidden();
            });
            for (var k = 0; k < boxes.length; k++) {
                boxes[k].addEventListener('change', function () {
                    if (!this.checked) bulk.checked = false;
                    else {
                        var all = true;
                        for (var m = 0; m < boxes.length; m++) {
                            if (!boxes[m].checked) { all = false; break; }
                        }
                        bulk.checked = all;
                    }
                    updateHidden();
                });
            }
            updateHidden();
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initRestoreArticleBulkCheckboxes);
        } else {
            initRestoreArticleBulkCheckboxes();
        }
    })();
    </script>
@endpush
