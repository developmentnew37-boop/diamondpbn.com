@extends('admin.layout.layout')

@section('title', $articleSet->name . ' articles')



@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 md:flex-row md:items-start md:justify-between md:gap-4">
            <div class="min-w-0">
                <h2 class="page-title break-words">{{ $articleSet->name }}</h2>
                <div class="breadcrumb flex-wrap gap-y-1">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.articles.set.index') }}" class="breadcrumb-link">Articles set</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">{{ $articleSet->name }} articles</span>
                    </div>
                </div>
            </div>
            <div class="w-full shrink-0 md:w-auto">
                <a href="{{ route('admin.articles.set.create.options', ['id' => $articleSet->id]) }}"
                    class="flex w-full md:w-auto !p-2 !py-3 text-[16px] font-normal justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded whitespace-nowrap">+
                    Add Article</a>
            </div>
        </div>
    </div>

    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}

    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1 !px-1">
            @if (session()->has('cus__success'))
                <div class="w-full content-card">
                    <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium"> {{ session('cus__success') }}</span>
                    </div>
                </div>
            @endif
            @if (session()->has('cus__error'))
                <div class="w-full content-card">
                    <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium"> {{ session('cus__error') }}</span>
                    </div>
                </div>
            @endif
        </div>
    @endif


    <div class="w-full flex flex-wrap gap-8 justify-center  ">
        <div class="w-full flex flex-wrap gap-4 justify-center ">
            <div class="w-full mx-auto content-card ">
                {{-- <div class="content-card"> --}}
                <div class="px-3 pt-4 sm:px-6 sm:pt-6 flex flex-col gap-3 justify-between min-w-0">
                    {{-- heading here --}}
                    <h2
                        class="text-lg bg-[var(--primary-color)] text-white !p-2 rounded font-semibold capitalize w-full sm:w-fit max-w-full text-left">
                        {{ $articleSet->name }} Articles Here <span class="material-symbols-outlined !text-sm align-middle">
                            arrow_cool_down
                        </span>
                    </h2>

                    {{-- this is bulk delete thing --}}

                    <div class="w-full flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end md:justify-between md:gap-4 !mt-4 sm:!mt-6 min-w-0">
                        <form method="post" action="{{ route('admin.article.set.bulk.delete') }}"
                            class="w-full md:flex-1 md:min-w-0 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
                            @csrf
                            <select name="actions" id=""
                                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full sm:flex-1 sm:min-w-[10rem] rounded outline-none focus:border-orange-600">
                                <option value="0">Bulk actions</option>
                                <option value="1">Delete</option>
                            </select>
                            <input type="hidden" name="bulk_ids" id="valHolders">
                            <input type="hidden" name="article_set_id" value="{{ $articleSet->id }}">
                            <button type="submit"
                                class="flex !p-3 !px-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer shrink-0 w-full sm:w-auto min-h-12">
                                Apply
                            </button>
                        </form>
                        <div class="w-full md:w-auto md:max-w-md flex justify-start md:justify-end items-center gap-2 min-w-0">
                            {{-- Search Box --}}
                            <div class="relative flex items-center w-full min-w-0">
                                <svg class="absolute !left-3 !top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input data-search-input type="text" id="search_category"
                                    class="w-full min-w-0 !pl-10 !pr-4 !py-2 bg-gray-100 min-h-12 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)] text-sm"
                                    placeholder="Search...">
                            </div>
                        </div>
                    </div>


                    <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                        <table
                            class="w-full min-w-[720px] border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                            <thead>
                                <tr class="bg-black text-white ">
                                    <th><input type="checkbox" name="bulk_category_select[]" id="bulk-checkBox-selector"
                                            class="text-lg"></th>
                                    @php
                                        $tHead = ['sno', 'title', 'language', 'Date', 'Action'];
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



                                @foreach ($articles as $index => $article)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 font-sans !px-2 !py-2 text-center"> <input
                                                type="checkbox" name="bulk_category_select[]" class="multi-check"
                                                value="{{ $article->id }}" id="">
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $index + 1 }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->name }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->language->name ?? '-' }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            {{ $article->created_at->format('d-m-Y') }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-2">
                                            <div class="flex gap-2 justify-center items-center">
                                                <a href="{{ route('admin.article.edit', $article->id) }}"
                                                    class="bg-yellow-500 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-600">
                                                    <span class="material-symbols-outlined !text-[16px] text-white">
                                                        edit_square
                                                    </span>
                                                </a>
                                                <form method="POST"
                                                    action="{{ route('admin.article.set.destroy.article', $article->id) }}"
                                                    onsubmit="return confirm('Remove this article from the set?')"
                                                    class="inline-flex">

                                                    @csrf
                                                    @method('DELETE')

                                                    {{-- 🔑 Required to know WHICH set --}}
                                                    <input type="hidden" name="set_id" value="{{ $articleSet->id }}">

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
                                @endforeach
                            </tbody>
                        </table>
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
