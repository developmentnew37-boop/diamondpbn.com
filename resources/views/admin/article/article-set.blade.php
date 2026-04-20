@extends('admin.layout.layout')

@push('style')
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-150%);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .article-set-box-animate {
            animation: slideDown 0.5s ease-out;
        }
    </style>
@endpush

@section('title', 'Articles Sets')

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 md:flex-row md:items-start md:justify-between md:gap-4">
            <div class="min-w-0">
                <h2 class="page-title">Articles Set</h2>
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
                        <span class="breadcrumb-link">Articles set</span>
                    </div>
                </div>
            </div>
            <div class="w-full shrink-0 md:w-auto">
                <a href="javascript:void(0)" id="make_article_Set"
                    class="flex w-full md:w-auto !p-2 !py-3 text-[16px] font-normal justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded whitespace-nowrap">+
                    Add Article Set
                </a>
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



    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}

    <div class="w-full flex flex-wrap gap-8 justify-center  ">
        <div class="w-full flex flex-wrap gap-4 justify-center ">
            <div class=" w-full mx-auto content-card ">
                {{-- <div class="content-card"> --}}
                <div class="px-3 pt-4 sm:px-6 sm:pt-6 flex flex-col gap-3 justify-between min-w-0">
                    {{-- heading here --}}

                    {{-- heading here --}}
                    <h2
                        class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold capitalize w-full sm:w-fit max-w-full">
                        All Articles Set Here <span class="material-symbols-outlined !text-sm align-middle">
                            arrow_cool_down
                        </span>
                    </h2>

                    <div
                        class="w-full flex flex-col gap-4 xl:flex-row xl:flex-wrap xl:items-end xl:gap-4 !mt-4 sm:!mt-6 min-w-0">
                        <form method="GET" action="{{ url()->current() }}"
                            class="w-full xl:flex-1 xl:min-w-0 flex flex-col gap-3 md:flex-row md:flex-wrap md:items-end">
                            <div class="w-full md:flex-1 md:min-w-0 flex flex-col min-w-0">
                                <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                                    {{-- Hidden input for form submission --}}
                                    <input data-hidden-input type="hidden" id="hidden-user" name="user"
                                        value="{{ old('user') }}">

                                    {{-- Select Button --}}
                                    <button data-select-btn type="button" id="selectBtn"
                                        class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                        <span data-select-text id="selectText" class="text-gray-400">Select
                                            Users ...</span>
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
                                            @if (isset($users) && count($users) > 0)
                                                @foreach ($users as $user)
                                                    <button data-option-item type="button"
                                                        class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                                        data-value="{{ $user->id }}" data-label="{{ $user->name }}">
                                                        <span class="text-sm ">{{ $user->name }}</span>
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
                            </div>
                            <div class="w-full md:flex-1 md:min-w-0 flex flex-col min-w-0">
                                <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                                    {{-- Hidden input for form submission --}}
                                    <input data-hidden-input type="hidden" id="language" name="language"
                                        value="{{ old('language') }}">

                                    {{-- Select Button --}}
                                    <button data-select-btn type="button" id="selectBtn"
                                        class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                        <span data-select-text id="selectText" class="text-gray-400">Select
                                            language...</span>
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
                                            @if (isset($languages) && count($languages) > 0)
                                                @foreach ($languages as $language)
                                                    <button data-option-item type="button"
                                                        class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                                        data-value="{{ $language->id }}"
                                                        data-label="{{ $language->name }}">
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
                            </div>
                            <div class="w-full md:w-auto shrink-0">
                                <button type="submit"
                                    class="!p-3 text-sm cursor-pointer bg-black text-white rounded hover:bg-[var(--primary-color)] w-full md:w-auto min-h-12">Filter
                                    Articles</button>
                            </div>
                        </form>
                        <div class="w-full xl:w-72 xl:flex-shrink-0 min-w-0">
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

                    {{-- table code here --}}

                    <div class="overflow-x-auto !mt-6 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                        <table
                            class="w-full min-w-[900px] border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                            <thead>
                                <tr class="bg-black text-white ">
                                    <th><input type="checkbox" name="bulk_category_select[]" class="text-lg"></th>
                                    @php
                                        $tHead = ['sno', 'title', 'Qty', 'language', 'user', 'Date', 'Action'];
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

                                @forelse ($articleSets as $index => $set)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 font-sans !px-2 !py-2 text-center"> <input
                                                type="checkbox" name="bulk_category_select[]"
                                                value="{{ $index + 1 }}" id="">
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-3">{{ $index + 1 }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-3">
                                            {{ $set->name }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-3">
                                            {{ $set->articles_count }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-3">
                                            {{ $set->language->name ?? '-' }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-3">
                                            {{ $set->admin->name }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-3">
                                            {{ $set->created_at->format('d-m-Y') }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-3">
                                            <div class="flex gap-2 justify-center">
                                                {{-- {{ route('set-articles', ['id' => 8]) }} --}}
                                                <a href="{{ route('admin.articles.set.show', $set->id) }}"
                                                    class="bg-black flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-gray-700 relative overflow ">
                                                    <span class="material-symbols-outlined !text-[16px] text-white">
                                                        visibility
                                                    </span>
                                                </a>


                                                <a href="{{ route('admin.articles.set.create.options', ['id' => $set->id]) }}"
                                                    class="bg-green-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-green-800 relative overflow ">
                                                    <span class="material-symbols-outlined !text-[16px]  text-white">
                                                        add
                                                    </span>
                                                </a>

                                                <a href="javascript:void(0)"
                                                    class="edit-set-btn bg-yellow-500 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-600">
                                                    <span class="material-symbols-outlined !text-[16px] text-white">
                                                        edit_square
                                                    </span>
                                                </a>

                                                <form method="POST"
                                                    action="{{ route('admin.articles.set.destroy', $set->id) }}"
                                                    onsubmit="return confirm('Are you sure you want to delete this article set?')"
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
                                        <td colspan="100" class="bg-gray-50 text-sm text-center !p-4">no article set
                                            present...</td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </div>

                    <div class="w-full">
                        {{ $articleSets->links() }}
                    </div>

                </div>

                {{-- </div> --}}
            </div>

        </div>

    </div>



@endsection

@section('popup')

    <div
        class="w-screen h-screen flex justify-center items-start fixed top-0 left-0 z-1100 overflow-hidden hidden set-parent-div">
        <div
            class="set-overlay w-screen h-screen bg-black opacity-0 absolute top-0 left-0  hidden duration-300 transition-all">
        </div>
        <div class="pop-box w-[min(100vw-1.5rem,480px)] max-w-[calc(100vw-1.5rem)] flex flex-col items-center hidden !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-[70px] -translate-y-[20%] linear opacity-0 duration-600 transition-all mx-3"
            id="create-article-box">
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6>Create Article Set</h6>
                <button type="button" class="cursor-pointer article-set-close" id=''>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
            </div>
            <div class="w-full flex flex-col gap-3 !p-3">
                <label for="article_set_title"
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                </label>
                <input type="text" name="name" placeholder="Enter article title here" id="article_set_title"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
            </div>
            <div data-dropdown-container class="w-full flex flex-col gap-3 !p-3 relative">

                <label for="article-set-title"
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                    Select Language </label>

                {{-- Hidden input for form submission --}}
                <input data-hidden-input type="hidden" id="article_set_language" name="language"
                    value="{{ old('language') }}">

                {{-- Select Button --}}
                <button data-select-btn type="button" id="selectBtn"
                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                    <span data-select-text id="selectText" class="text-gray-400">Select
                        Language ...</span>
                    <svg data-chevron id="chevron" class="w-5 h-5 text-gray-400 transition-transform duration-200"
                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                {{-- Dropdown --}}
                <div data-dropdown id="dropdown"
                    class="hidden absolute z-50 w-full top-full left-0 !mt-2 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden">
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
            <div class="w-full flex justify-end items-center !bg-gray-200 !px-3 !py-2">
                <a href="javascript:void(0)" type="button" id="proceed_article_set"
                    class="cursor-pointer bg-green-500 text-white rounded !p-3 flex items-center gap-1">
                    <div
                        class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin loader hidden">
                    </div>
                    Proceed
                </a>
            </div>
        </div>
        <div class="pop-box w-[min(100vw-1.5rem,480px)] max-w-[calc(100vw-1.5rem)] flex flex-col hidden items-center !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-[70px] -translate-y-[20%] linear opacity-0 duration-600 transition-all mx-3"
            id="update-article-box">
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6>Update Article Set</h6>
                <button type="button" class="cursor-pointer article-set-close" id=''>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
            </div>
            <div class="w-full flex flex-col gap-3 !p-3">
                <label for="upd_artilce_set_title"
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                </label>
                <input type="text" name="upd_artilce_set_title" placeholder="Enter article title here"
                    id="upd_artilce_set_title"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

            </div>
            <div class="w-full flex justify-end items-center !bg-gray-200 !px-3 !py-2">
                <a href="javascript:void(0)" type="button" class="cursor-pointer bg-green-500 text-white rounded !p-3">
                    Update
                </a>
            </div>
        </div>
    </div>
@endsection


@push('scripts')
    <script>
        document.addEventListener('submit', function(e) {
            const form = e.target;
            if (form.method.toUpperCase() !== 'GET') return;

            // disable empty hidden inputs so they don't appear in query string
            form.querySelectorAll('input[type="hidden"][name]').forEach((inp) => {
                const val = (inp.value ?? '').trim();
                if (val === '') inp.disabled = true;
            });
        }, true);
    </script>

    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/article-set/create-set.js') }}"></script>
    <script src="{{ asset('js/article-set/create-article-set.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>
@endpush
