@extends('admin.layout.layout')

@section('title', 'Create Articles')

@push('style')
    <style>
        #article-container .ck-editor__editable_inline {
            min-height: 500px !important;
            /* increase as needed */
        }
    </style>
    <link rel="stylesheet" href="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.css" crossorigin>
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
                        <a href="{{ route('admin.article.index') }}" class="breadcrumb-link">Articles</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.article.create') }}" class="breadcrumb-link">Create</a>

                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                {{-- <a href="" class="flex !p-2 !py-3 text-[16px] font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">+ Add Article</a> --}}
            </div>
        </div>
    </div>


    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1 !px-1">
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

    <form action="{{ route('admin.article.store') }}" method="POST"
        class="w-full flex flex-wrap justify-between items-start">
        @csrf
        <div class="flex flex-col gap-2 w-[74%] content-card justify-start">
            <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">Create post/article
            </h2>

            <div class="w-full flex flex-col gap-3 p-2">
                <label for="category_title"
                    class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                </label>
                <input type="text" name="name" placeholder="Add category title here" id="category_title"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                @error('name')
                    <p class="text-red-400 bg-red-100 text-sm !p-2 rounded">{{ $message }}</p>
                @enderror

            </div>

            <div class="w-full flex flex-col gap-3 p-2 !mt-6" id="article-container">
                <label for="description"
                    class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Description
                </label>
                <textarea name="description" id="editor" rows="20"></textarea>
                @error('description')
                    <p class="text-red-400 bg-red-100 text-sm !p-2 rounded">{{ $message }}</p>
                @enderror

            </div>

            {{-- <div class="w-full">
                <textarea name="post_description" id="editor" rows="10"></textarea>
            </div> --}}
        </div>
        <div class="w-[25%] flex flex-col gap-3 content-card">
            {{-- <h2 class="text-2xl capitalize">Categories & language here</h2> --}}
            <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                <label for="category_title"
                    class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Select
                    Category
                </label>

                {{-- Hidden input for form submission --}}
                <input data-hidden-input type="hidden" id="hidden-category" name="category" value="{{ old('category') }}">



                {{-- Select Button --}}
                <button data-select-btn type="button" id="selectBtn"
                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                    <span data-select-text id="selectText" class="text-gray-400">Select category...</span>
                    <svg data-chevron id="chevron" class="w-5 h-5 text-gray-400 transition-transform duration-200"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
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
                        @if (isset($categories) && count($categories) > 0)
                            @foreach ($categories as $category)
                                <button data-option-item type="button"
                                    class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                    data-value="{{ $category->id }}" data-label="{{ $category->name }}">
                                    <span class="text-sm ">{{ $category->name }}</span>
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
            @error('category')
                <p class="text-red-400 bg-red-100 text-sm !p-2 rounded">{{ $message }}</p>
            @enderror
            <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                <label for="category_title"
                    class="text-[16px] flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Select
                    Languages
                </label>

                {{-- Hidden input for form submission --}}
                <input data-hidden-input type="hidden" id="hidden-language" name="language" value="{{ old('language') }}">

                {{-- Select Button --}}
                <button data-select-btn type="button" id="selectBtn"
                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                    <span data-select-text id="selectText" class="text-gray-400">Select language...</span>
                    <svg data-chevron id="chevron" class="w-5 h-5 text-gray-400 transition-transform duration-200"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
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
                        @if (isset($languages) && count($languages) > 0)
                            @foreach ($languages as $language)
                                <button data-option-item type="button"
                                    class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                    data-value="{{ $language->id }}" data-label="{{ $language->name }}">
                                    <span class="text-sm ">{{ $language->name }}</span>
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
            @error('language')
                <p class="text-red-400 bg-red-100 text-sm !p-2 rounded">{{ $message }}</p>
            @enderror
            <div class="w-full !mt-6">
                <input type="hidden" name="type" value="0">
                <button
                    class="!p-3 text-[16px] cursor-pointer bg-black text-white rounded hover:bg-[var(--primary-color)] w-full">Add
                    Article</button>
            </div>
        </div>
    </form>



@endsection

@push('scripts')
    <script type="importmap">
{
    "imports": {
        "ckeditor5": "{{ asset('ckeditor/ckeditor5.js') }}",
        "ckeditor5/": "{{ asset('ckeditor/') }}/",
        "ckeditor5-premium-features": "{{ asset('ckeditor/ckeditor5-premium-features.js') }}",
        "ckeditor5-premium-features/": "{{ asset('ckeditor/') }}/"
    }
}
</script>

    <script src="https://cdn.ckbox.io/ckbox/2.6.1/ckbox.js" crossorigin></script>

    <script type="module" src="{{ asset('ckeditor/main.js') }}"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            setTimeout(() => {
                console.log(document.querySelector('.ck-content'))
                document.querySelector('.ck-content').addEventListener('click', () => {
                    document.querySelector('.ck-evaluation-badge') ? document.querySelector(
                        '.ck-evaluation-badge').remove() : '';
                    document.querySelector('.ck-powered-by') ? document.querySelector(
                        '.ck-powered-by').remove() : '';

                })
                if (document.querySelector('.ck-evaluation-badge')) {
                    document.querySelector('.ck-evaluation-badge').remove();
                }
                if (document.querySelector('.ck-powered-by')) {
                    document.querySelector('.ck-powered-by').remove();
                }
            }, 2000);
        });
    </script>

    <script src="https://cdn.ckeditor.com/ckeditor5/45.2.0/ckeditor5.umd.js" crossorigin></script>

    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
@endpush
