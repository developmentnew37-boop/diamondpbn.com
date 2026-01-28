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

    @php
        $categories = [
            ['id' => 1, 'name' => 'Technology'],
            ['id' => 2, 'name' => 'Business'],
            ['id' => 3, 'name' => 'Marketing'],
            ['id' => 4, 'name' => 'Design'],
            ['id' => 5, 'name' => 'Development'],
            ['id' => 6, 'name' => 'Sales'],
            ['id' => 7, 'name' => 'Finance'],
            ['id' => 8, 'name' => 'Human Resources'],
            ['id' => 9, 'name' => 'Customer Support'],
            ['id' => 10, 'name' => 'Operations'],
            ['id' => 11, 'name' => 'Product Management'],
            ['id' => 12, 'name' => 'Quality Assurance'],
            ['id' => 13, 'name' => 'Research & Development'],
            ['id' => 14, 'name' => 'Legal'],
            ['id' => 15, 'name' => 'Administration'],
            ['id' => 16, 'name' => 'Consulting'],
            ['id' => 17, 'name' => 'Training'],
            ['id' => 18, 'name' => 'Public Relations'],
            ['id' => 19, 'name' => 'Content Writing'],
            ['id' => 20, 'name' => 'Data Analytics'],
            ['id' => 21, 'name' => 'Project Management'],
            ['id' => 22, 'name' => 'Cybersecurity'],
            ['id' => 23, 'name' => 'Cloud Computing'],
            ['id' => 24, 'name' => 'Mobile Development'],
            ['id' => 25, 'name' => 'Web Development'],
        ];
    @endphp

    <div class="w-full flex flex-wrap gap-8 justify-center">
        <div class="w-full flex flex-wrap gap-4 justify-center ">
            {{-- sec 1 max-w-[600px] --}}
            <div class="w-1/3  flex flex-col content-card">
                <div class="flex flex-col gap-5 mb-4 w-full ">
                    <h2 class="text-xl font-semibold  capitalize">Add category here</h2>
                    <form action="" method="post" class="w-full flex-col">
                        <div class="w-full flex flex-col gap-5">
                            <div class="w-full flex flex-col gap-3 p-2">
                                <label for="category_title"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Title
                                </label>
                                <input type="text" name="category_title" placeholder="Add category title here"
                                    id="category_title"
                                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                            </div>
                            <div class="w-full flex flex-col gap-3 p-2 relative">
                                <label for="category_title" class="text-sm flex items-center ">Parent
                                    Category
                                </label>

                                {{-- Hidden input for form submission --}}
                                <input type="hidden" id="hidden-category" name="category" value="{{ old('category') }}">

                                {{-- Select Button --}}
                                <button type="button" id="selectBtn"
                                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                    <span id="selectText" class="text-gray-400">Select category...</span>
                                    <svg id="chevron" class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                {{-- Dropdown --}}
                                <div id="dropdown"
                                    class="hidden absolute z-50 w-full top-full !mt-2 bg-white border border-gray-200 rounded-lg shadow-xl overflow-hidden">
                                    {{-- Search Box --}}
                                    <div class="!p-3 border-b border-gray-200">
                                        <div class="relative">
                                            <svg class="absolute !left-3 !top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                            <input type="text" id="searchInput"
                                                class="w-full !pl-10 !pr-4 !py-2 bg-gray-100 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)]   text-sm"
                                                placeholder="Search...">
                                        </div>
                                    </div>

                                    {{-- Options List --}}
                                    <div id="optionsList" class="max-h-64 overflow-y-auto">
                                        @if (isset($categories) && count($categories) > 0)
                                            @foreach ($categories as $category)
                                                <button type="button"
                                                    class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                                    data-value="{{ $category['id'] }}" data-label="{{ $category['name'] }}">
                                                    <span class="text-sm ">{{ $category['name'] }}</span>
                                                </button>
                                            @endforeach
                                        @else
                                            <div class="px-4 py-8 text-center text-gray-400 text-sm">
                                                No options available
                                            </div>
                                        @endif
                                    </div>


                                </div>
                            </div>
                            <div class="w-full flex flex-col gap-3 p-2">
                                <label for="category_bio"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Description
                                </label>
                                <textarea name="category_bio" id="category_bio" rows="4" placeholder="Category description here"
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
            <div class=" w-[65%] mx-auto content-card ">
                {{-- <div class="content-card"> --}}
                {{-- <div class="px-6 pt-6 flex justify-between">
                   
                </div> --}}
                <div class="w-full flex flex-wrap">
                    <div class="w-1/2 flex flex-wrap justify-start items-center gap-2">
                        <select name="actions" id=""
                            class="bg-gray-100  border border-gray-200 !w-2/5 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                            <option value="">Bulk actions</option>
                            <option value="">Delete</option>
                        </select>
                        <button type="button"
                            class="flex !p-3  !px-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                            Apply
                        </button>
                    </div>
                    <div class="w-1/2 flex justify-end items-center gap-2">
                        <div class="relative w-1/2 max-h-12 overflow-hidden">
                            <input type="text" name="search_category" placeholder="search here" id="search_category"
                                class="bg-gray-100 shadow border border-gray-200 !p-3 !pr-[50px] max-h-12 text-sm w-full rounded outline-none ">
                            <button
                                class="w-12 h-12 flex items-center justify-center bg-[var(--sidebar-bg)] absolute top-0 right-0">
                                <svg class="w-5 h-5 text-white !text-sm" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap overflow-x-auto !mt-6">
                    <table class="w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">
                        <thead>
                            <tr class="bg-gray-300 active-border-color ">
                                <th><input type="checkbox" name="bulk_category_select[]"></th>
                                @php
                                    $tHead = ['sno', 'category', 'parent category', 'user', 'Date', 'Action'];
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

                            @php
                                // ✅ Generate 10 dummy campaign rows
                                $categories_data = [
                                    [
                                        'name' => 'technology',
                                        'parent_category' => '-',
                                        'user' => 'admin',
                                        'date' => '10-05-2025',
                                    ],
                                    [
                                        'name' => 'business',
                                        'parent_category' => '-',
                                        'user' => 'editor',
                                        'date' => '12-05-2025',
                                    ],
                                    [
                                        'name' => 'gaming',
                                        'parent_category' => 'entertainment',
                                        'user' => 'john_doe',
                                        'date' => '15-05-2025',
                                    ],
                                    [
                                        'name' => 'gadgets',
                                        'parent_category' => 'technology',
                                        'user' => 'admin',
                                        'date' => '18-05-2025',
                                    ],
                                    [
                                        'name' => 'cryptocurrency',
                                        'parent_category' => 'finance',
                                        'user' => 'sarah',
                                        'date' => '20-05-2025',
                                    ],
                                    [
                                        'name' => 'artificial intelligence',
                                        'parent_category' => 'technology',
                                        'user' => 'mike',
                                        'date' => '22-05-2025',
                                    ],
                                    [
                                        'name' => 'health',
                                        'parent_category' => '-',
                                        'user' => 'editor',
                                        'date' => '25-05-2025',
                                    ],
                                    [
                                        'name' => 'entertainment',
                                        'parent_category' => '-',
                                        'user' => 'jane',
                                        'date' => '28-05-2025',
                                    ],
                                ];

                            @endphp

                            @foreach ($categories_data as $index => $category)
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 font-sans !px-2 !py-2 text-center"> <input
                                            type="checkbox" name="bulk_category_select[]" value="{{ $index + 1 }}"
                                            id="">
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $index + 1 }}
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $category['name'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $category['parent_category'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $category['user'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $category['date'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        <div class="flex flex-wrap gap-2 justify-center">
                                            <a href="javascript:void(0)"
                                                class="bg-black flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-gray-700 relative overflow ">
                                                <span class="material-symbols-outlined !text-[16px] text-white">
                                                    visibility
                                                </span>
                                            </a>
                                            <a href="javascript:void(0)"
                                                class="bg-yellow-500 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-600">
                                                <span class="material-symbols-outlined !text-[16px] text-white">
                                                    edit_square
                                                </span>
                                            </a>
                                            <a href="javascript:void(0)"
                                                class="bg-red-600  flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-green-700">
                                                <span class="material-symbols-outlined !text-[16px] text-white">
                                                    delete
                                                </span>
                                            </a>
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

    {{-- languages category starts here --}}



    <div class="w-full flex flex-wrap gap-8 !mt-6 justify-center">
        <div class="w-full flex flex-wrap gap-4 justify-center ">
            {{-- sec 1 max-w-[600px] --}}
            <div class="w-1/3  flex flex-col content-card">
                <div class="flex flex-col gap-5 mb-4 w-full ">
                    <h2 class="text-xl font-semibold  capitalize">Add Language here</h2>
                    <form action="" method="post" class="w-full flex-col">
                        <div class="w-full flex flex-col gap-5">
                            <div class="w-full flex flex-col gap-3 p-2">
                                <label for="category_title"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">name
                                </label>
                                <input type="text" name="category_title" placeholder="Add language here"
                                    id="category_title"
                                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                            </div>


                            <div class="w-full flex items-center p-2">
                                <button type="submit"
                                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration:600 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                    Add Language</button>
                            </div>


                        </div>

                    </form>
                </div>

            </div>
            {{-- sec 2 max-w-7xl --}}
            <div class=" w-[65%] mx-auto content-card ">
                {{-- <div class="content-card"> --}}
                {{-- <div class="px-6 pt-6 flex justify-between">
                   
                </div> --}}
                <div class="w-full flex flex-wrap justify-end">
                    <div class="w-1/2 flex justify-start items-center gap-2">
                        <h2 class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold  capitalize ">
                            All Languages data <span class="material-symbols-outlined !text-sm">
                                arrow_cool_down
                            </span></h2>
                    </div>
                    <div class="w-1/2 flex justify-end items-center gap-2">
                        <div class="relative w-1/2 max-h-12 overflow-hidden">
                            <input type="text" name="search_language" placeholder="search here" id="search_language"
                                class="bg-gray-100 shadow border border-gray-200 !p-3 !pr-[50px] max-h-12 text-sm w-full rounded outline-none ">
                            <button
                                class="w-12 h-12 flex items-center justify-center bg-[var(--sidebar-bg)] absolute top-0 right-0">
                                <svg class="w-5 h-5 text-white !text-sm" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap overflow-x-auto !mt-6">
                    <table class="w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">
                        <thead>
                            <tr class="bg-black text-white active-border-color ">
                                @php
                                    $tHead2 = ['sno', 'name', 'user', 'Date', 'Action'];
                                @endphp
                                @foreach ($tHead2 as $t)
                                    <th
                                        class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                        {{ $t }}
                                    </th>
                                @endforeach
                            </tr>


                        </thead>
                        <tbody>

                            @php
                                $languages = [
                                    [
                                        'name' => 'English',
                                        'user' => 'admin',
                                        'date' => '01-06-2025',
                                    ],
                                    [
                                        'name' => 'Spanish',
                                        'user' => 'john_doe',
                                        'date' => '03-06-2025',
                                    ],
                                    [
                                        'name' => 'French',
                                        'user' => 'editor',
                                        'date' => '05-06-2025',
                                    ],
                                    [
                                        'name' => 'Korean',
                                        'user' => 'sofia',
                                        'date' => '07-06-2025',
                                    ],
                                    [
                                        'name' => 'Japanese',
                                        'user' => 'akira',
                                        'date' => '09-06-2025',
                                    ],
                                    [
                                        'name' => 'Arabic',
                                        'user' => 'hassan',
                                        'date' => '11-06-2025',
                                    ],
                                ];

                            @endphp

                            @foreach ($languages as $index => $language)
                                <tr class="hover:bg-gray-50">

                                    <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $index + 1 }}
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $language['name'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $category['user'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        {{ $language['date'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        <div class="flex flex-wrap gap-2 justify-center">

                                            <a href="javascript:void(0)"
                                                class="bg-yellow-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-600">
                                                <span class="material-symbols-outlined !text-[16px] text-white">
                                                    edit_square
                                                </span>
                                            </a>
                                            <a href="javascript:void(0)"
                                                class="bg-red-600  flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-green-700">
                                                <span class="material-symbols-outlined !text-[16px] text-white">
                                                    delete
                                                </span>
                                            </a>
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

    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>

@endsection
