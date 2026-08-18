@extends('admin.layout.layout')

@section('title', 'Delete Articles')

@section('main-content')

    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Delete Articles</h2>
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
                        <span class="breadcrumb-link">Delete Articles</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                <a href="{{ route('admin.article.index') }}"
                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration-300 bg-gray-700 hover:bg-gray-600 text-white rounded">
                    Back to articles
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
                    <h2 class="text-xl bg-red-600 text-white !p-2 rounded font-semibold capitalize w-fit">
                        Soft-deleted used articles
                    </h2>

                    <div class="text-sm text-gray-700 !p-3 rounded border border-amber-200 bg-amber-50">
                        This list only includes articles that were <strong>used in campaigns</strong> and then
                        soft-deleted. Non-used deleted articles are not shown here. <strong>Permanent delete</strong>
                        is run in the background (queue <code class="text-xs bg-white px-1 rounded">article_permanent_purge</code>).
                        Campaign-related rows may cascade when the article row is removed. Keep your queue worker running.
                    </div>

                    <div class="flex flex-wrap items-center gap-3 !py-2">
                        <form action="{{ route('admin.article.trashed.queue-purge-all-used') }}" method="post"
                            class="inline-flex"
                            onsubmit="return confirm('Queue permanent deletion of ALL {{ (int) ($totalTrashedUsedForPurgeAll ?? 0) }} deleted used article(s) in your scope? This ignores search/category/date filters on this page. Cannot be undone.');">
                            @csrf
                            <button type="submit"
                                class="flex !p-3 !px-4 text-sm font-normal justify-center border-2 border-red-600 text-red-700 bg-white hover:bg-red-50 rounded cursor-pointer">
                                Queue purge all deleted used
                                @if (($totalTrashedUsedForPurgeAll ?? 0) > 0)
                                    <span class="!ml-2 font-semibold">({{ (int) $totalTrashedUsedForPurgeAll }})</span>
                                @endif
                            </button>
                        </form>
                        <span class="text-xs text-gray-500 max-w-xl">Removes every soft-deleted <strong>used</strong>
                            article you are allowed to see (your articles, or all if super admin).</span>
                    </div>

                    <div
                        class="flex flex-wrap items-start gap-4 !py-3 border-t border-gray-200 !mt-1 !pt-4 w-full">
                        <form action="{{ route('admin.article.trashed.queue-purge-by-quantity') }}" method="post"
                            class="flex flex-wrap items-end gap-3"
                            onsubmit="var el=document.getElementById('purge-qty-input'); var q=el?parseInt(el.value,10):0; return confirm('Queue permanent deletion of up to '+q+' deleted used article(s) matching current filters (newest deleted first)?');">
                            @csrf
                            @foreach (request()->only(['search', 'category', 'language', 'date_from', 'date_to']) as $k => $v)
                                @if ($v !== null && $v !== '')
                                    <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                                @endif
                            @endforeach
                            <div class="flex flex-col gap-1">
                                <label for="purge-qty-input" class="text-sm font-medium text-gray-700">Purge by
                                    quantity</label>
                                <input id="purge-qty-input" type="number" name="quantity" min="1" max="500000"
                                    required value="{{ old('quantity') }}" placeholder="e.g. 5000"
                                    class="border border-gray-300 rounded !px-3 !py-2 text-sm w-40"
                                    inputmode="numeric" autocomplete="off">
                            </div>
                            <button type="submit"
                                class="flex !p-3 !px-4 text-sm font-normal justify-center bg-orange-600 hover:bg-orange-700 text-white rounded cursor-pointer">
                                Queue purge by quantity
                            </button>
                        </form>
                        <p class="text-xs text-gray-500 max-w-lg !pb-1">
                            Uses the <strong>same category, language, search, and date range</strong> as the table below
                            (change filters first if you need a subset). Picks the <strong>most recently deleted</strong>
                            rows first. If fewer rows match than the number you enter, only those are queued.
                        </p>
                    </div>

                    <style>
                        .article-filter-bar {
                            display: flex;
                            flex-direction: column;
                            gap: 0.875rem;
                            margin-top: 0.75rem;
                            padding: 1rem 1.125rem;
                            background: #f9fafb;
                            border: 1px solid #e5e7eb;
                            border-radius: 0.5rem;
                        }
                        .article-filter-grid {
                            display: grid;
                            grid-template-columns: repeat(1, minmax(0, 1fr));
                            gap: 0.875rem;
                        }
                        @media (min-width: 640px) {
                            .article-filter-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
                        }
                        @media (min-width: 1024px) {
                            .article-filter-grid { grid-template-columns: 1.1fr 1.1fr 1fr 1fr 1.4fr; }
                        }
                        .article-filter-field {
                            display: flex;
                            flex-direction: column;
                            gap: 0.375rem;
                            min-width: 0;
                        }
                        .article-filter-label {
                            font-size: 0.8125rem;
                            font-weight: 600;
                            color: #374151;
                            margin: 0;
                        }
                        .article-filter-hint {
                            font-size: 0.75rem;
                            color: #6b7280;
                            margin: 0;
                        }
                        .article-filter-control {
                            width: 100%;
                            min-height: 42px;
                            padding: 0.625rem 0.875rem;
                            font-size: 0.875rem;
                            line-height: 1.25;
                            color: #111827;
                            background: #f3f4f6;
                            border: 1px solid #e5e7eb;
                            border-radius: 0.25rem;
                            outline: none;
                            transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
                        }
                        select.article-filter-control {
                            appearance: none;
                            -webkit-appearance: none;
                            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b7280' d='M1.4 0.6L6 5.2 10.6.6 12 2 6 8 0 2z'/%3E%3C/svg%3E");
                            background-repeat: no-repeat;
                            background-position: right 0.875rem center;
                            background-size: 12px 8px;
                            padding-right: 2.25rem;
                            cursor: pointer;
                        }
                        .article-filter-control:focus {
                            border-color: #dc2626;
                            background-color: #fff;
                            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.12);
                        }
                        .article-filter-actions {
                            display: flex;
                            flex-wrap: wrap;
                            align-items: center;
                            gap: 0.5rem;
                        }
                        .article-filter-apply {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            min-height: 42px;
                            padding: 0.625rem 1.125rem;
                            font-size: 0.875rem;
                            font-weight: 500;
                            color: #fff;
                            background: #dc2626;
                            border: none;
                            border-radius: 0.25rem;
                            cursor: pointer;
                        }
                        .article-filter-apply:hover { background: #b91c1c; }
                        .article-filter-clear {
                            display: inline-flex;
                            align-items: center;
                            justify-content: center;
                            min-height: 42px;
                            padding: 0.625rem 1.125rem;
                            font-size: 0.875rem;
                            font-weight: 500;
                            color: #374151;
                            background: #fff;
                            border: 1px solid #d1d5db;
                            border-radius: 0.25rem;
                            text-decoration: none;
                        }
                        .article-filter-clear:hover { background: #f3f4f6; }
                        .article-bulk-row {
                            display: flex;
                            flex-wrap: wrap;
                            align-items: center;
                            gap: 0.75rem;
                            margin-top: 0.75rem;
                        }
                    </style>

                    <form method="GET" action="{{ route('admin.article.trashed.index') }}" class="article-filter-bar">
                        <div class="article-filter-grid">
                            <div class="article-filter-field">
                                <label class="article-filter-label" for="article_category">Category</label>
                                <select name="category" id="article_category" class="article-filter-control">
                                    <option value="">All categories</option>
                                    @if (isset($categories) && count($categories) > 0)
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}"
                                                {{ (string) request('category') === (string) $category->id ? 'selected' : '' }}>
                                                {{ $category->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="article-filter-field">
                                <label class="article-filter-label" for="article_langauge">Language</label>
                                <select name="language" id="article_langauge" class="article-filter-control">
                                    <option value="">All languages</option>
                                    @if (isset($languages) && count($languages) > 0)
                                        @foreach ($languages as $language)
                                            <option value="{{ $language->id }}"
                                                {{ (string) request('language') === (string) $language->id ? 'selected' : '' }}>
                                                {{ $language->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div class="article-filter-field">
                                <label class="article-filter-label" for="date_from">Used from</label>
                                <input type="date" name="date_from" id="date_from" class="article-filter-control"
                                    value="{{ request('date_from') }}">
                            </div>
                            <div class="article-filter-field">
                                <label class="article-filter-label" for="date_to">Used to</label>
                                <input type="date" name="date_to" id="date_to" class="article-filter-control"
                                    value="{{ request('date_to') }}">
                            </div>
                            <div class="article-filter-field">
                                <label class="article-filter-label" for="search_category">Search</label>
                                <input type="search" name="search" id="search_category" class="article-filter-control"
                                    value="{{ request('search') }}" placeholder="Title or content">
                            </div>
                        </div>
                        <p class="article-filter-hint">
                            Date range filters by when the article was used and soft-deleted (Deleted column).
                        </p>
                        <div class="article-filter-actions">
                            <button type="submit" class="article-filter-apply">Apply filters</button>
                            <a href="{{ route('admin.article.trashed.index') }}" class="article-filter-clear">Clear</a>
                        </div>
                    </form>

                    <div class="article-bulk-row">
                        <span class="text-sm text-gray-600 whitespace-nowrap">Bulk (queue)</span>
                        <form action="{{ route('admin.article.trashed.force-delete') }}"
                            class="flex flex-wrap items-center gap-2" method="post"
                            id="trashed-bulk-purge-form"
                            onsubmit="var h=document.getElementById('trashedBulkIds'); if(!h||!h.value.trim()){alert('Select at least one article.');return false;} return confirm('Queue permanent deletion of the selected deleted used articles? Cannot be undone.');">
                            @csrf
                            <input type="hidden" name="bulk_ids" id="trashedBulkIds" value="">
                            <button type="submit"
                                class="flex !p-3 !px-4 text-sm font-normal justify-center duration-600 transition-all bg-red-600 hover:bg-red-700 text-white rounded cursor-pointer">
                                Purge selected
                            </button>
                        </form>
                    </div>

                    <div class="flex flex-wrap overflow-x-auto !mt-6">
                        <table
                            class="w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                            <thead>
                                <tr class="bg-black text-white">
                                    <th class="border border-gray-200 !px-2 !py-3">
                                        <input type="checkbox" id="bulk-checkBox-selector-trashed" class="text-lg">
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
                                            <input type="checkbox" class="multi-check-trashed" value="{{ $article->id }}">
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
                                            <form action="{{ route('admin.article.trashed.destroy', $article->id) }}"
                                                method="POST" class="inline-flex"
                                                onsubmit="return confirm('Queue permanent deletion of this deleted used article? Cannot be undone.');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="bg-red-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-red-700 cursor-pointer"
                                                    title="Purge permanently">
                                                    <span class="material-symbols-outlined !text-[16px] text-white">
                                                        delete_forever
                                                    </span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="bg-gray-100 !p-3 text-center">No deleted <strong>used</strong>
                                            articles in trash.
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
    {{-- Plain script (no ES module): ensures bulk_ids sync works even if general.js import fails --}}
    <script>
    (function () {
        function initTrashedArticleBulkCheckboxes() {
            var bulk = document.getElementById('bulk-checkBox-selector-trashed');
            var hidden = document.getElementById('trashedBulkIds');
            if (!bulk || !hidden) return;
            var boxes = document.querySelectorAll('.multi-check-trashed');
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
            document.addEventListener('DOMContentLoaded', initTrashedArticleBulkCheckboxes);
        } else {
            initTrashedArticleBulkCheckboxes();
        }
    })();
    </script>
@endpush
