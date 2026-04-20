@extends('admin.layout.layout')

@section('title', 'Add Category')

@push('style')
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu-animate {
            animation: slideDown 0.2s ease-out;
        }
    </style>
@endpush

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <div class="min-w-0">
                <h2 class="page-title">Dashboards</h2>
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
                        <a href="#" class="breadcrumb-link">Category</a>
                    </div>
                </div>
            </div>
        </div>
    </div>



    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}

    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1 !px-3">
            @if (session()->has('cus__success'))
                <div class="w-full content-card">
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



    <div class="w-full flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-6 min-w-0">
        {{-- Form: full width when stacked; fixed comfortable width when beside table --}}
        <div class="w-full lg:w-96 lg:flex-shrink-0 flex flex-col content-card min-w-0">
            <div class="flex flex-col gap-5 mb-4 w-full text-left">
                <h2 class="text-xl font-semibold capitalize">Add category here</h2>
                <form action="{{ route('admin.articles.category.store') }}" method="post" class="w-full flex-col">
                    @csrf
                    <div class="w-full flex flex-col gap-5">
                        <div class="w-full flex flex-col gap-3 p-2">
                            <label for="category_title"
                                class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                            </label>
                            <input type="text" name="name" placeholder="Add category title here" id="category_title"
                                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                            @error('name')
                                <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                            @enderror

                        </div>
                        <div class="w-full flex flex-col gap-2 p-2 relative">
                            <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                                <label for="category_title" class="text-sm flex items-center ">Select
                                    Parent
                                </label>

                                {{-- Hidden input for form submission --}}
                                <input data-hidden-input type="hidden" id="parent-category" name="parent_id"
                                    value="{{ old('category') }}">

                                {{-- Select Button --}}
                                <button data-select-btn type="button" id="selectBtn"
                                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                    <span data-select-text id="selectText" class="text-gray-400">Select category...</span>
                                    <svg data-chevron id="chevron"
                                        class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                {{-- Dropdown --}}
                                <div data-dropdown id="dropdown"
                                    class="hidden absolute z-50 w-full top-full !mt-2 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden">
                                    {{-- Search Box --}}
                                    <div class="!p-3 border-b border-gray-200">
                                        <div class="relative">
                                            <svg class="absolute !left-3 !top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                            <input data-search-input type="text" id="searchInput"
                                                class="w-full !pl-10 !pr-4 !py-2 bg-gray-100 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)]   text-sm"
                                                placeholder="Search...">
                                        </div>
                                    </div>

                                    {{-- Options List --}}
                                    <div data-options-list id="optionsList" class="max-h-64 overflow-y-auto">
                                        @if (isset($parentcat) && count($parentcat) > 0)
                                            @foreach ($parentcat as $cat)
                                                <button data-option-item type="button"
                                                    class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                                    data-value="{{ $cat->id }}" data-label="{{ $cat->name }}">
                                                    <span class="text-sm ">{{ $cat->name }}</span>
                                                </button>
                                            @endforeach
                                        @else
                                            {{-- <div class="px-4 py-8 text-center text-gray-400 text-sm">
                                               No options available
                                             </div> --}}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @error('parent_id')
                                <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="w-full flex flex-col gap-3 p-2">
                            <label for="category_bio"
                                class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Description
                            </label>
                            <textarea name="description" id="category_bio" rows="4" placeholder="Category description here"
                                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600"></textarea>
                            @error('description')
                                <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                            @enderror

                        </div>
                        <div class="w-full flex items-stretch sm:items-center p-2">
                            <button type="submit"
                                class="w-full sm:w-auto flex !p-2 !py-3 text-sm font-normal justify-center duration:600 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                Add Category</button>
                        </div>


                    </div>

                </form>
            </div>

        </div>
        <div class="w-full lg:flex-1 lg:min-w-0 content-card min-w-0">
            <div class="w-full flex flex-col gap-3 md:flex-row md:flex-wrap md:items-start md:justify-between md:gap-4">
                <div class="w-full md:flex-1 md:min-w-0 flex flex-col gap-2">
                    <form action="{{ route('admin.articles.category.delete') }}" method="post"
                        class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2 w-full">
                        @csrf
                        <select name="actions" id=""
                            class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full sm:flex-1 sm:min-w-[10rem] rounded outline-none focus:border-orange-600">
                            <option value="">Bulk actions</option>
                            <option value="1">Delete</option>
                        </select>
                        <input type="hidden" name="bulk_ids" id="valHolders">
                        <button type="submit"
                            class="flex !p-3 !px-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer shrink-0 w-full sm:w-auto">
                            Apply
                        </button>
                    </form>

                    @error('actions')
                        <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                            <span class="font-medium"> {{ $message }}</span>
                        </div>
                    @enderror
                    @error('bulk_ids')
                        <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                            <span class="font-medium"> {{ $message }}</span>
                        </div>
                    @enderror
                </div>
                <div class="w-full md:w-auto md:max-w-md flex justify-start md:justify-end items-center gap-2 min-w-0">
                    <form method="GET" action="{{ url()->current() }}" class="relative w-full min-w-0">

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
            <div class="overflow-x-auto !mt-6 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                <table
                    class="w-full min-w-[900px] border border-gray-100 border-collapse text-sm whitespace-nowrap searchable-table">
                    <thead>
                        <tr class="bg-black text-white   ">
                            <th class="border border-gray-200">
                                <input type="checkbox" name="bulk_category_select[]" id="bulk-checkBox-selector">
                            </th>
                            @php
                                $tHead = ['sno', 'category', 'parent category', 'user', 'Date', 'Action'];
                            @endphp
                            @foreach ($tHead as $t)
                                <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                    {{ $t }}
                                </th>
                            @endforeach
                        </tr>


                    </thead>
                    <tbody>

                        @forelse($categories as $index => $category)
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-200 font-sans !px-2 !py-2 text-center"> <input
                                        type="checkbox" name="bulk_category_select[]" class="multi-check"
                                        value="{{ $category->id }}" id="">
                                </td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $index + 1 }}
                                </td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    {{ $category->name }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    {{ $category->parent->name ?? '-' }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    {{ $category->admin->name }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    {{ $category->created_at->format('d-m-Y') }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    <div class="flex flex-wrap gap-2 justify-center">

                                        <a href="{{ route('admin.articles.category.edit', $category->id) }}"
                                            class="bg-yellow-500 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-600">
                                            <span class="material-symbols-outlined !text-[16px] text-white">
                                                edit_square
                                            </span>
                                        </a>
                                        {{-- <a href="{{ route('admin.articles.category.destroy',$category->id) }}"
                                            class="bg-red-600  flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-red-700">
                                            <span class="material-symbols-outlined !text-[16px] text-white">
                                                delete
                                            </span>
                                        </a> --}}
                                        <form action="{{ route('admin.articles.category.destroy', $category->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this category?');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                class="bg-red-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-red-700">
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
                                <td colspan="100" class="text-center !py-4 text-gray-500 bg-gray-100 font-sans">
                                    No categories found...
                                </td>
                            </tr>
                        @endforelse





                    </tbody>
                </table>
            </div>

            <div class="w-full !p-2 !mt-2">
                {{ $categories->links() }}
            </div>
            {{-- </div> --}}
        </div>


    </div>



    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>
    <script type="module" src="{{ asset('js/bulk-select/script.js') }}"></script>
@endsection
