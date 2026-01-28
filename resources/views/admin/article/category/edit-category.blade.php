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
                        {{-- {{ route('article') }} --}}
                        <a href="" class="breadcrumb-link">Articles</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        {{-- {{ route('category') }} --}}
                        <a href="#" class="breadcrumb-link">Category</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
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



    <div class="w-full flex flex-wrap gap-4 justify-center">
        {{-- sec 1 max-w-[600px] --}}
        <div class="w-1/3  flex flex-col content-card">
            <div class="flex flex-col gap-5 mb-4 w-full ">
                <h2 class="text-xl font-semibold  capitalize">Edit <span
                        class="text-[var(--primary-color)]">{{ $category->name }}</span> Category here</h2>
                <form action="{{ route('admin.articles.category.update',$category->id) }}" method="post" class="w-full flex-col">
                    @csrf
                    @method('PUT')
                    <div class="w-full flex flex-col gap-5">
                        <div class="w-full flex flex-col gap-3 p-2">
                            <label for="category_title"
                                class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                            </label>
                            <input type="text" name="name" placeholder="Add category title here" id="category_title"
                                value="{{ $category->name }}"
                                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                            @error('name')
                                <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                            @enderror

                        </div>
                        {{-- <div class="w-full flex flex-col gap-2 p-2 relative">
                            <label for="category_title" class="text-sm flex items-center ">Select
                                Parent
                            </label>
                            <select name="parent_id" id="parent-category"
                                class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                <option value="">select parent category</option>
                                @if (isset($parentcat) && count($parentcat) > 0)
                                    @foreach ($parentcat as $cat)
                                        <option value="{{ $cat->id }}"
                                            {{ $cat->id == $cat->parent_id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                    @endforeach
                                @endif
                            </select>

                            @error('parent_id')
                                <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                            @enderror
                        </div> --}}
                        <div class="w-full flex flex-col gap-2 p-2 relative">
                            <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                                <label for="category_title" class="text-sm flex items-center ">Select
                                    Parent
                                </label>

                                {{-- Hidden input for form submission --}}
                                <input data-hidden-input type="hidden" id="parent-category" name="parent_id"
                                    value="{{ $category->parent_id }}">

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
                                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">{{ $category->description }}</textarea>
                            @error('description')
                                <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                            @enderror

                        </div>
                        <div class="w-full flex items-center p-2">
                            <button type="submit"
                                class="flex !p-2 !py-3 text-sm font-normal justify-center duration:600 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                Update Category</button>
                        </div>


                    </div>

                </form>
            </div>

        </div>



    </div>



     <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>

@endsection
