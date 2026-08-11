@extends('admin.layout.layout')

@section('title', 'Articles here')

@push('style')
    <style>
        .articles-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .articles-toolbar-group {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        .articles-toolbar-bulk-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }

        @media (min-width: 768px) and (max-width: 1023.98px) {
            .articles-toolbar {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.75rem;
            }

            .articles-toolbar-group {
                grid-column: 1 / -1;
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.75rem;
                width: 100%;
            }

            .articles-toolbar-group > .articles-control-select {
                width: 100%;
                min-width: 0;
            }

            .articles-toolbar-divider {
                display: none;
            }

            .articles-toolbar-bulk-form {
                grid-column: 1 / -1;
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 0.75rem;
                width: 100%;
            }

            .articles-toolbar-bulk-form .articles-control-select {
                width: 100%;
                min-width: 0;
            }

            .articles-toolbar-bulk-form .articles-control-btn {
                width: auto;
            }

            .articles-search-wrap {
                grid-column: 1 / -1;
                width: 100%;
                max-width: none;
            }
        }

        @media (min-width: 1024px) {
            .articles-toolbar-group {
                width: auto;
                flex: 1 1 auto;
                min-width: 0;
            }

            .articles-toolbar-bulk-form {
                width: auto;
            }
        }

        .articles-toolbar-divider {
            display: none;
            width: 1px;
            height: 2.5rem;
            background-color: #e5e7eb;
            flex-shrink: 0;
        }

        @media (min-width: 768px) {
            .articles-toolbar-divider {
                display: block;
            }
        }

        .articles-control-select {
            width: 100%;
            min-height: 46px;
        }

        @media (min-width: 640px) {
            .articles-control-select {
                width: auto;
                min-width: 11rem;
            }
        }

        .articles-control-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            white-space: nowrap;
            border-radius: 0.25rem;
            transition: background-color 0.3s ease;
        }

        .articles-search-wrap {
            width: 100%;
        }

        @media (min-width: 1024px) {
            .articles-search-wrap {
                width: auto;
                min-width: 280px;
                max-width: 320px;
                flex-shrink: 0;
            }
        }
    </style>
@endpush

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div
            class="w-full flex flex-col gap-3 md:flex-row md:flex-nowrap md:items-start md:justify-between md:gap-4">
            <div class="min-w-0">
                <h2 class="page-title">Articles</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Articles</a>
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0 w-full md:w-auto justify-start md:justify-end">
                <a href="{{ route('admin.article.trashed.index') }}"
                    class="flex flex-1 sm:flex-initial !p-2 !py-3 text-sm font-normal justify-center duration-300 bg-gray-700 hover:bg-gray-600 text-white rounded whitespace-nowrap">
                    Delete Articles
                </a>
                <a href="{{ route('admin.articles.opt') }}"
                    class="flex flex-1 sm:flex-initial !p-2 !py-3 text-[16px] font-normal min-w-[140px] justify-center duration-300 bg-black hover:bg-[var(--primary-color)] text-white rounded whitespace-nowrap">+
                    Add Article</a>
            </div>
        </div>
    </div>
    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1 !px-1">
            @if (session()->has('cus__success'))
                <div class="w-full content-card ">
                    <div class="!p-4  text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium"> {{ session('cus__success') }}</span>
                    </div>
                </div>
            @endif
            @if (session()->has('cus__error'))
                <div class="w-full content-card">
                    <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium"> {{ session('cus__error') }}</span>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}

    <div class="w-full flex flex-wrap gap-8 justify-center  ">
        <div class="w-full flex flex-wrap gap-4 justify-center ">
            <div class=" w-full mx-auto content-card ">
                {{-- <div class="content-card"> --}}
                <div class="px-6 pt-6 flex flex-col gap-3 justify-between">
                    {{-- heading here --}}
                    <h2 class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold  capitalize w-fit">
                        All Articles Here <span class="material-symbols-outlined !text-sm">
                            arrow_cool_down
                        </span>
                    </h2>


                    <div class="articles-toolbar !mt-3">
                        <div class="articles-toolbar-group">
                            <select name="" id="article_category"
                                class="articles-control-select bg-gray-100 border border-gray-200 !p-3 text-sm rounded outline-none focus:border-[var(--primary-color)]">
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

                            <select name="" id="article_langauge"
                                class="articles-control-select bg-gray-100 border border-gray-200 !p-3 text-sm rounded outline-none focus:border-[var(--primary-color)]">
                                <option value="">select language</option>
                                @if (isset($languages) && count($languages) > 0)
                                    @foreach ($languages as $language)
                                        <option value="{{ $language->id }}"
                                            {{ request('language') == $language->id ? 'selected' : '' }}>
                                            {{ $language->name }}</option>
                                    @endforeach
                                @endif
                            </select>

                            <span class="articles-toolbar-divider" aria-hidden="true"></span>

                            <form action="{{ route('admin.articles.delete') }}"
                                class="articles-toolbar-bulk-form" method="post">
                                @csrf
                                <select name="actions"
                                    class="articles-control-select bg-gray-100 border border-gray-200 !p-3 text-sm rounded outline-none focus:border-[var(--primary-color)]">
                                    <option value="">Bulk actions</option>
                                    <option value="1">Delete</option>
                                </select>
                                <input type="hidden" name="bulk_ids" id="valHolders">
                                <button type="submit"
                                    class="articles-control-btn bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white cursor-pointer w-full sm:w-auto">
                                    Apply
                                </button>
                            </form>
                        </div>

                        <div class="articles-search-wrap relative max-h-12">
                            <form method="GET" action="{{ url()->current() }}" class="relative w-full">
                                @foreach (request()->except('search') as $key => $value)
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endforeach

                                <input type="search" name="search" placeholder="search here" id="search_category"
                                    value="{{ request('search') }}"
                                    class="bg-gray-100 shadow border border-gray-200 !p-3 !pr-[50px] max-h-12 text-sm w-full rounded outline-none focus:border-[var(--primary-color)]">

                                <button type="submit"
                                    class="w-12 h-12 flex items-center justify-center bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] absolute top-0 right-0 rounded-r transition-colors">
                                    <svg class="w-5 h-5 text-white !text-sm" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>


                    <div class="overflow-x-auto !mt-6 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                        <table
                            class="w-full min-w-[980px] border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                            <colgroup>
                                <col style="width:4%">
                                <col style="width:5%">
                                <col style="width:30%">
                                <col style="width:12%">
                                <col style="width:10%">
                                <col style="width:9%">
                                <col style="width:10%">
                                <col style="width:10%">
                                <col style="width:10%">
                            </colgroup>

                            <thead>
                                <tr class="bg-black text-white ">
                                    <th><input type="checkbox" name="bulk_category_select[]" id="bulk-checkBox-selector"
                                            class="text-lg"></th>
                                    @php
                                        $tHead = [
                                            'sno',
                                            'title',
                                            'category',
                                            'language',
                                            'status',
                                            'user',
                                            'Date',
                                            'Action',
                                        ];
                                    @endphp
                                    @foreach ($tHead as $t)
                                        <th
                                            class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                            {{ $t }}
                                        </th>
                                    @endforeach
                                </tr>


                            </thead>
                            <tbody>

                                @forelse ($articles as $index => $article)
                                     <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 font-sans !px-2 !py-2 text-center"> <input
                                                type="checkbox" name="bulk_category_select[]" class="multi-check"
                                                value="{{ $article->id }}" id="">
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $index + 1 + $offset }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->name }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->category->name ?? '-' }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->language->name ?? '-' }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            @if ($article->status == '0')
                                                <span
                                                    class="flex !px-3 !py-2 bg-green-100 text-green-600 rounded text-sm w-fit">non-used</span>
                                            @elseif ($article->status == '1')
                                                <span
                                                    class="flex !px-3 !py-2 bg-red-100 text-red-600 rounded text-sm w-fit">used</span>
                                            @endif



                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->admin->name }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->created_at->format('d-m-Y') }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            <div class="flex  gap-2 justify-center">
                                                {{-- {{ route('view-article', $article_id) }} --}}
                                                <a href="{{ route('admin.article.show', $article->id) }}"
                                                    class="bg-black flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-gray-700 relative overflow ">
                                                    <span class="material-symbols-outlined !text-[16px] text-white">
                                                        visibility
                                                    </span>
                                                </a>
                                                {{-- {{ route('edit-article', $article_id) }} --}}
                                                <a href="{{ route('admin.article.edit', $article->id) }}"
                                                    class="bg-yellow-500 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-600">
                                                    <span class="material-symbols-outlined !text-[16px] text-white">
                                                        edit_square
                                                    </span>
                                                </a>
                                                <form action="{{ route('admin.article.destroy', $article->id) }}"
                                                    method="POST"
                                                    onsubmit="return confirm('Are you sure you want to delete this article?');"
                                                    class="inline-flex">

                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit"
                                                        class="bg-red-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-red-700 cursor-pointer">
                                                        <span class="material-symbols-outlined !text-[16px] text-white">
                                                            delete
                                                        </span>
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="100" class="bg-gray-100 !p-3 text-center">No Articles Found</td>
                                    </tr>
                                @endforelse


                              
                            </tbody>
                        </table>
                    </div>

                    <div class="w-full">
                        {{ $articles->links() }}
                    </div>

                </div>

                {{-- </div> --}}
            </div>

        </div>

    </div>


@endsection


@push('scripts')
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>
    <script type="module" src="{{ asset('js/bulk-select/script.js') }}"></script>
@endpush
