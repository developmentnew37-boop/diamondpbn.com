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

@php
    $users = [
        ['id' => 1, 'name' => 'admin'],
        ['id' => 2, 'name' => 'editor2'],
        ['id' => 3, 'name' => 'editor3'],
        ['id' => 4, 'name' => 'Jhon doe'],
        ['id' => 5, 'name' => 'asma'],
        ['id' => 6, 'name' => 'furqan'],
        ['id' => 7, 'name' => 'shamim'],
        ['id' => 8, 'name' => 'Muhammad Ali'],
    ];
    $languages = [
        ['id' => 6, 'name' => 'English'],
        ['id' => 2, 'name' => 'Thai'],
        ['id' => 3, 'name' => 'Indonesian'],
        ['id' => 4, 'name' => 'Korean'],
        ['id' => 5, 'name' => 'Russian'],
        ['id' => 1, 'name' => 'other'],
    ];
@endphp

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Domains Set</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.domain.index') }}" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.set.index') }}" class="breadcrumb-link">Domains set</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                <a href="javascript:void(0)" id="make_domain_set"
                    class="flex !p-2 !py-3 text-[16px] font-normal min-w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">
                    Create Domain Set
                </a>
            </div>
        </div>
    </div>

    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}

    <div class="w-full flex flex-col gap-2 justify-center  ">
        <div class="w-full flex flex-col mx-auto content-card ">
            {{-- <div class="content-card"> --}}


            <div class="px-6 pt-6 flex flex-col gap-3 justify-between">
                {{-- heading here --}}

                {{-- heading here --}}

                <h2 class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold  capitalize w-fit">
                    Domains Sets Here <span class="material-symbols-outlined !text-sm">
                        arrow_cool_down
                    </span>
                </h2>

                {{-- bulk delete & category filter --}}

                <div class="w-full flex flex-wrap items-center !mt-2">
                    <div class="w-full flex flex-col gap-2">
                        @if (session()->has('cus__success'))
                            <div class="!p-4  text-sm rounded bg-green-100 text-green-700 w-full !mb-2" role="alert">
                                <span class="font-medium"> {{ session('cus__success') }}</span>
                            </div>
                        @endif
                        @if (session()->has('cus__error'))
                            <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full !mb-2" role="alert">
                                <span class="font-medium"> {{ session('cus__error') }}</span>
                            </div>
                        @endif
                        @error('action')
                            <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full !mb-2" role="alert">
                                <span class="font-medium"> {{ $message }}</span>
                            </div>
                        @enderror
                        @error('bulk_ids')
                            <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full !mb-2" role="alert">
                                <span class="font-medium"> {{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                    <div class="w-[65%] flex flex-wrap gap-2">
                        <div class="w-1/5">
                            <select name="" id="domain-category"
                                class="bg-gray-100  border border-gray-200 !w-full !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                <option value="">select category</option>
                                @if (isset($domainCategories) && count($domainCategories) > 0)
                                    @foreach ($domainCategories as $domainCategory)
                                        <option value="{{ $domainCategory->id }}">{{ $domainCategory->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="w-3/5">
                            {{-- {{ route('admin.domain.category.delete') }} --}}
                            <form action="{{ route('admin.set.delete') }}"
                                class="w-full flex flex-wrap justify-start items-center gap-1" method="post">
                                @csrf
                                <select name="actions" id=""
                                    class="bg-gray-100 border border-gray-200 !w-2/5 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    <option value="">Bulk actions</option>
                                    <option value="1">Delete</option>
                                </select>
                                <input type="hidden" name="bulk_ids" id="valHolders">
                                <button type="submit"
                                    class="flex !p-3  !px-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                    Apply
                                </button>
                            </form>


                        </div>
                    </div>
                    <div class="w-[35%] flex flex-wrap gap-3 justify-end">

                        {{-- Search Box --}}

                        <div class="relative w-1/2 max-h-12 overflow-hidden">
                            <form method="GET" action="{{ url()->current() }}" class="relative w-full">

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
                </div>

                {{-- table code here --}}

                <div class="flex flex-wrap overflow-x-auto !mt-6">
                    <table class="w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                        <thead>
                            <tr class="bg-black text-white ">
                                <th><input type="checkbox" name="bulk_category_select[]" id="bulkSetSelector"
                                        class="text-lg"></th>
                                @php
                                    $tHead = ['sno', 'title', 'Category', 'Qty', 'user', 'Date', 'Action'];
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



                            @foreach ($sets as $index => $set)
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 font-sans !px-2 !py-2 text-center"> <input
                                            type="checkbox" name="bulk_category_select[]" class="multiCheckBox"
                                            value="{{ $set->id }}" id="">
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">{{ $index + 1 }}
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">
                                        {{ $set->name }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">
                                        {{ $set->DomainCategory->name }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">
                                        {{ $set->qty }}</td>

                                    <td class="border border-gray-200 font-sans !px-2 !py-3">
                                        {{ Auth::guard('admin')->user()->name }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">
                                        {{ $set->updated_at->format('d-m-Y') }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">
                                        <div class="flex flex-wrap gap-2 justify-center">
                                            {{-- {{ route('domain.view.set', ['id' => 8]) }} --}}

                                            <a href="{{ route('admin.set.show', $set->id) }}"
                                                class="bg-black flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-gray-700 relative overflow ">
                                                <span class="material-symbols-outlined !text-lg text-white !text-sm">
                                                    visibility
                                                </span>
                                            </a>

                                            {{-- {{ route('domain.set.edit',['id' => 9]) }} --}}
                                            <a href="{{ route('admin.set.edit', $set->id) }}"
                                                class="bg-yellow-500 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-yellow-600">
                                                <span class="material-symbols-outlined !text-lg text-white !text-sm">
                                                    edit_square
                                                </span>
                                            </a>

                                            <form action="{{ route('admin.set.destroy', $set->id) }}" method="POST"
                                                class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    onclick="return confirm('Are you sure you want to delete this domain set')"
                                                    class="bg-red-600 flex items-center justify-center rounded !text-sm w-7 h-7 duration-300 hover:bg-red-700 cursor-pointer">

                                                    <span class="material-symbols-outlined text-white !text-sm">
                                                        delete
                                                    </span>
                                                </button>
                                            </form>


                                            {{-- <a href="javascript:void(0)"
                                                class="bg-red-600  flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-red-700">
                                                <span class="material-symbols-outlined !text-lg text-white !text-sm">
                                                    delete
                                                </span>
                                            </a> --}}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="w-full">
                    {{ $sets->links() }}
                </div>

            </div>

            {{-- </div> --}}
        </div>

    </div>


@endsection

@section('popup')

    {{-- set-parent-div | set-overlay | create-domain-set-box | article-set-close | create-set --}}

    <div
        class="w-screen h-screen flex justify-center items-start fixed top-0 left-0 z-1100 overflow-hidden hidden set-parent-div">
        <div
            class="set-overlay w-screen h-screen bg-black opacity-0 absolute top-0 left-0  hidden duration-300 transition-all">
        </div>
        <div class="pop-box w-[480px] flex flex-col items-center hidden !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-[70px] -translate-y-[20%] linear opacity-0 duration-600 transition-all"
            id="create-domain-set-box">
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6>Create Article Set</h6>
                <button type="button" class="cursor-pointer domain-set-close" id=''>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
            </div>
            <div class="w-full flex flex-col gap-3 !p-3">
                <label for="article_Set_title"
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                </label>
                <input type="text" name="title" placeholder="Enter domain set" id="title"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

            </div>
            <div data-dropdown-container class="w-full flex flex-col gap-3 !p-3 relative">

                <label for="article-set-title"
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                    Select domain category</label>

                {{-- Hidden input for form submission --}}
                <input data-hidden-input type="hidden" id="set-domain-category" name="domain-category" value="">

                {{-- Select Button --}}
                <button data-select-btn type="button" id="selectBtn"
                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                    <span data-select-text id="selectText" class="text-gray-400">Select
                        domain category ...</span>
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
                        @if (isset($domainCategories) && count($domainCategories) > 0)
                            @foreach ($domainCategories as $domain)
                                <button data-option-item type="button"
                                    class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                    data-value="{{ $domain->id }}" data-label="{{ $domain->name }}">
                                    <span class="text-sm ">{{ $domain->name }}</span>
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
                {{-- {{ route('domain.create.set', 3) }} --}}
                <a href="#" type="button"
                    class="cursor-pointer bg-green-500 text-white rounded !p-3 flex flex-wrap gap-1 items-center"
                    id="create-set">
                    <div
                        class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin loader hidden">
                    </div>
                    Proceed
                </a>
            </div>
        </div>

    </div>
@endsection


@push('scripts')
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>
    <script type="module" src="{{ asset('js/domain-set/script.js') }}"></script>
@endpush
