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
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Category</a>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center">
            </div>
        </div>
    </div>



    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}

    <div class="w-full flex flex-wrap gap-6 justify-center">
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
        </div>

        <div class="w-full flex flex-col gap-4">
            {{-- sec 1 max-w-[600px] --}}
            <div class="w-full max-w-[980px] mx-auto flex flex-col content-card">

                <div class="flex flex-col gap-5 mb-4 w-full ">
                    <h2 class="text-xl font-semibold  capitalize">Add Domain category here</h2>
                    <form action="{{ route('admin.domain.category.store') }}" method="post" class="w-full flex-col">
                        @csrf
                        <div class="w-full flex flex-col gap-5">
                            <div class="w-full flex flex-col gap-3 p-2">
                                <label for="category_title"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                                </label>
                                <input type="text" name="name" placeholder="Add category title here"
                                    id="category_title"
                                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                @error('email')
                                    <div class="w-full text-sm text-red-600">{{ $message }}</div>
                                @enderror

                            </div>
                            <div class="w-full flex flex-col gap-3 p-2">
                                <label for="category_bio"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Description
                                </label>
                                <textarea name="description" id="category_bio" rows="4" placeholder="Category description here"
                                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600"></textarea>


                            </div>
                            <div class="w-full flex items-center p-2">
                                <button type="submit"
                                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration:600 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                    Add Category</button>
                            </div>


                        </div>

                    </form>
                </div>

            </div>
            {{-- sec 2 max-w-7xl --}}
            <div class="w-full content-card min-w-0">

                <div class="w-full flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                    <div class="w-full xl:w-auto xl:flex-1 flex flex-col gap-2 min-w-0">
                        @error('actions')
                            <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                        @enderror
                        @error('bulk_ids')
                            <p class="text-red-600 bg-red-100 !p-2 text-sm">{{ $message }}</p>
                        @enderror
                        <form action="{{ route('admin.domain.category.delete') }}"
                            class="w-full flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-2 xl:max-w-[560px]" method="post">
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
                    </div>
                    <div class="w-full xl:w-auto xl:flex-1 flex justify-start xl:justify-end items-center gap-2 min-w-0">
                        <div class="relative w-full sm:max-w-sm xl:w-[320px] max-h-12 overflow-hidden min-w-0">
                            <form method="GET" action="{{ url()->current() }}" class="relative w-full">

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

                <div class="overflow-x-auto !mt-6 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                    <table class="w-full min-w-[760px] border border-gray-200 border-collapse text-sm whitespace-nowrap"
                        id="domainCategoryTable">
                        <thead>
                            <tr class="bg-[var(--sidebar-bg)] text-white active-border-color ">
                                <th><input type="checkbox" name="bulk_category_select[]" id="bulk-checkBox-selector"></th>
                                @php
                                    $tHead = ['sno', 'category', 'user', 'Date', 'Action'];
                                @endphp
                                @foreach ($tHead as $t)
                                    <th
                                        class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitalize text-left">
                                        {{ $t }}
                                    </th>
                                @endforeach
                            </tr>


                        </thead>
                        <tbody>




                            @foreach ($categories as $index => $category)
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 font-sans !px-2 !py-2 text-center">
                                        <input type="checkbox" name="bulk_category_select[]" class="multi-check"
                                            value="{{ $category->id }}" id="">
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $index + 1 }}
                                    </td>
                                    <td
                                        class="border border-gray-200 font-sans !px-2 !py-2 bg-orange-400 text-white title">
                                        {{ $category->name }}</td>

                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $category->admin->name }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $category->updated_at->format('m-d-Y') }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        <div class="flex flex-wrap gap-2 justify-center">

                                            <a href="javascript:void(0)" data-row="{{ $index + 1 }}"
                                                data-id="{{ $category->id }}"
                                                class="bg-yellow-500 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-600 upd-pop-btn">
                                                <span class="material-symbols-outlined !text-[16px] text-white">
                                                    edit_square
                                                </span>
                                            </a>
                                            <form action="{{ route('admin.domain.category.destroy', $category->id) }}"
                                                method="post">
                                                @csrf
                                                @method('delete')
                                                <input type="hidden" name="del_id" value="{{ $category->id }}">
                                                <button type="submit"
                                                    class="bg-red-600  flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-red-700 cursor-pointer">
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
        <div class="pop-box w-[480px] flex flex-col hidden items-center !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-[70px] -translate-y-[20%] linear opacity-0 duration-600 transition-all"
            id="update-article-box">
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6>Update Article Set</h6>
                <button type="button" class="cursor-pointer article-set-close" id=''>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
            </div>
            <div class="w-full !p-4 !pb-0 flex flex-col gap-2">
                <div class="!p-4  text-sm rounded bg-green-100 text-green-700 w-full duration-500 transition-all opacity-0 hidden"
                    id="success-row" role="alert">
                    <span class="font-medium"> Success!</span> <span class="message"></span>

                </div>

                <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full duration-500 transition-all opacity-0 hidden"
                    id="error-row" role="alert">
                    <span class="font-medium"> Error!</span><span class="message"></span>

                </div>

            </div>
            <div class="w-full flex flex-col gap-3 !p-3">
                <label for="article_Set_title"
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Update
                    Category
                </label>
                <input type="text" name="upd_set_title" placeholder="Enter article title here" id="upd_set_title"
                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                <input type="hidden" name="upd_category" value="general domains" id="upd_cat_id">

            </div>
            <div class="w-full flex justify-end items-center !bg-gray-200 !px-3 !py-2">
                <a href="javascript:void(0)" type="button" data-id=""
                    class="cursor-pointer upd-domain-category bg-green-500 text-white rounded !p-3 flex items-center gap-1">
                    <div
                        class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin loader hidden">
                    </div>
                    Update
                </a>
            </div>
        </div>
    </div>
@endsection


@push('scripts')
    <script src="{{ asset('js/article-set/create-set.js') }}"></script>
    <script type="module" src="{{ asset('js/bulk-select/script.js') }}"></script>
@endpush
