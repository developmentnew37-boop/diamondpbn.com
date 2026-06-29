@extends('admin.layout.layout')

@section('title', 'Create PBN Post Campaign')

@push('style')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.4/css/dataTables.dataTables.min.css">
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

        .page-btn {
            min-width: 36px;
            height: 36px;
            padding-left: 0.5rem;
            /* px-2 */
            padding-right: 0.5rem;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 0.375rem;
            /* rounded */
            border: 1px solid #d1d5db;
            /* border-gray-300 */
            background-color: #ffffff;

            font-size: 0.875rem;
            /* text-sm */
            cursor: pointer;

            transition: background-color 0.15s ease-in-out;
        }

        .page-btn:hover {
            background-color: #e5e7eb;
            /* hover:bg-gray-200 */
        }

        .page-active {
            background-color: #2563eb;
            /* bg-blue-600 */
            color: #ffffff;
            border-color: #2563eb;
        }

        .page-disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-loader {
            position: absolute;
            top: 15px;
            right: 40px;
        }

        .article-set-box-animate {
            animation: slideDown 0.5s ease-out;
        }

        table.dataTable thead .sorting:before,
        table.dataTable thead .sorting:after,
        table.dataTable thead .sorting_asc:before,
        table.dataTable thead .sorting_asc:after,
        table.dataTable thead .sorting_desc:before,
        table.dataTable thead .sorting_desc:after {
            color: #fff !important;
            /* white color */
            opacity: 1 !important;
        }

        .dt-length,
        .dt-layout-cell {
            font-size: 14px !important;
        }

        /* Center the S.No column */
        table.dataTable th:nth-child(1) .dt-column-title {
            text-align: center;
        }

        table.dataTable td:nth-child(2),
        table.dataTable th:nth-child(2) {
            text-align: left !important;
            vertical-align: middle !important;
        }

        .sortable-ghost {
            opacity: 0.5;
        }

        /* Multi-bulk pairs: obvious horizontal scrollbar for clients */
        .multi-bulk-x-scroll {
            scrollbar-width: auto;
            scrollbar-color: #94a3b8 #e2e8f0;
        }

        .multi-bulk-x-scroll::-webkit-scrollbar {
            height: 12px;
        }

        .multi-bulk-x-scroll::-webkit-scrollbar-track {
            background: #e2e8f0;
            border-radius: 6px;
        }

        .multi-bulk-x-scroll::-webkit-scrollbar-thumb {
            background: #94a3b8;
            border-radius: 6px;
            border: 2px solid #e2e8f0;
        }

        .multi-bulk-x-scroll::-webkit-scrollbar-thumb:hover {
            background: #64748b;
        }

        /* Custom dropdowns: avoid clipping + page-wide horizontal scroll */
        #campaign-form {
            overflow-x: clip;
        }

        #campaign-form .campaigns-section,
        #campaign-form .article-opt-box,
        #campaign-form .domains-sections,
        #campaign-form [data-dropdown-container] {
            overflow: visible;
        }

        #campaign-form [data-dropdown-container]:focus-within {
            z-index: 40;
        }

        #campaign-form [data-dropdown] {
            z-index: 50;
            max-width: 100%;
        }

        /* URL Keywords modal — mobile layout */
        .dy-key-pop-box {
            display: flex;
            flex-direction: column;
        }

        .keyword-method-tabs {
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
        }

        .keyword-method-tabs-inner {
            display: flex;
            flex-wrap: nowrap;
            gap: 0.25rem;
            min-width: min-content;
        }

        @media (min-width: 640px) {
            .keyword-method-tabs-inner {
                flex-wrap: wrap;
                min-width: 0;
            }
        }

        .keyword-tab-btn {
            flex: 0 0 auto;
            white-space: nowrap;
            line-height: 1.25;
        }

        .keyword-modal-rel-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem 1rem;
            align-items: center;
        }

        @media (max-width: 639px) {
            .keyword-modal-rel-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.5rem 0.75rem;
            }

            .keyword-modal-rel-item {
                display: flex;
                align-items: center;
                gap: 0.375rem;
                min-height: 2rem;
            }

            .keyword-modal-rel-item input[type="checkbox"] {
                width: 1rem;
                height: 1rem;
                flex-shrink: 0;
            }

            .keyword-modal-rel-item label {
                font-size: 0.8125rem;
                line-height: 1.2;
            }
        }
    </style>

    {{-- ------ pushing the link -------- --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
        integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
@endpush




@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.campaign.index') }}" class="breadcrumb-link">PBN Post</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Create</a>

                    </div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center">
                {{-- <a href="" class="flex !p-2 !py-3 text-[16px] font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">+ Add Article</a> --}}
            </div>
        </div>
    </div>

    {{-- ******************* success & errors alerts ***************** --}}

    <div class="w-full flex flex-wrap items-center !mt-2">
        <div class="w-full flex flex-col gap-2">

            @if (session('cus__success'))
                <div class="!p-4 text-sm rounded bg-green-100 text-green-700 w-full !mb-2" role="alert">
                    <span class="font-medium">{{ session('cus__success') }}</span>
                </div>
            @endif

            @if (session('cus__error'))
                <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full !mb-2" role="alert">
                    <span class="font-medium">{{ session('cus__error') }}</span>
                </div>
            @endif

            @php
                $fields = [
                    'campaign_no',
                    'domain_category',
                    'post_quantity',
                    'article_niche',
                    'sel_articles_opt',
                    'selected_articles_val',
                    'keywordmethod',
                    'keywordsDataHolder',
                    'sel_domains',
                    'campaigns_domains',
                    'is_sticky',
                ];
            @endphp

            @foreach ($fields as $field)
                @error($field)
                    <div class="!p-4 text-sm rounded bg-red-100 text-red-700 w-full !mb-2" role="alert">
                        <span class="font-medium">{{ $message }}</span>
                    </div>
                @enderror
            @endforeach

        </div>
    </div>


    {{-- ******************* Ends here  ***************** --}}


    <form action="{{ route('admin.campaign.store') }}" method="POST" id="campaign-form"
        class="w-full max-w-full min-w-0 flex flex-wrap justify-between items-start content-card">
        @csrf

        <h2 class="text-base sm:text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-full max-w-full sm:w-fit !p-2 rounded">Create Post Campaigns
        </h2>
        {{-- xxxxxxxxxxxxxxxxxx campaigns button xxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}
        <div class="campaign-create-tabs w-full !p-2">
            <button data-id="campaign-info" type="button"
                class="group flex flex-col gap-1.5 sm:gap-3 rounded !p-1.5 sm:!p-2 cursor-pointer text-[var(--primary-color)] duration-300 transition-all hover:text-[var(--primary-color)] w-fit text-xs sm:text-base lg:text-lg tab-switcher">
                <span>Campaign Info</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span
                            class="w-1 h-1 flex rounded-full bg-[var(--primary-color)] duration-300 transition-all heading-dots group-hover:bg-[var(--primary-color)]"></span>
                        <span
                            class="w-1 h-1 flex rounded-full bg-[var(--primary-color)] duration-300 transition-all heading-dots group-hover:bg-[var(--primary-color)]"></span>
                        <span
                            class="w-1 h-1 flex rounded-full bg-[var(--primary-color)] duration-300 transition-all heading-dots group-hover:bg-[var(--primary-color)]"></span>
                    </div>
                    <div
                        class="w-15 h-1 flex bg-[var(--primary-color)] heading-line rounded duration-300 transition-all group-hover:bg-[var(--primary-color)]">
                    </div>
                </div>
            </button>
            <button data-id="add-articles" type="button"
                class="group flex flex-col gap-1.5 sm:gap-3 rounded !p-1.5 sm:!p-2 cursor-pointer duration-300 transition-all hover:text-[var(--primary-color)] w-fit text-xs sm:text-base lg:text-lg tab-switcher">
                <span>Add Articles</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                    </div>
                    <div
                        class="w-15 h-1 flex bg-gray-300 heading-line rounded duration-300 transition-all group-hover:bg-[var(--primary-color)]">
                    </div>
                </div>
            </button>
            <button data-id="add-keywords-url" type="button"
                class="group flex flex-col gap-1.5 sm:gap-3 rounded !p-1.5 sm:!p-2 cursor-pointer duration-300 transition-all hover:text-[var(--primary-color)] w-fit text-xs sm:text-base lg:text-lg tab-switcher">
                <span>Add keywords & Url</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                    </div>
                    <div
                        class="w-15 h-1 flex bg-gray-300 heading-line rounded duration-300 transition-all group-hover:bg-[var(--primary-color)]">
                    </div>
                </div>
            </button>
            <button data-id="select-domains" type="button"
                class="group flex flex-col gap-1.5 sm:gap-3 rounded !p-1.5 sm:!p-2 cursor-pointer duration-300 transition-all hover:text-[var(--primary-color)] w-fit text-xs sm:text-base lg:text-lg tab-switcher">
                <span>Select Domains Category</span>
                <div class="w-full flex items-center gap-1">
                    <div class="flex items-center gap-1">
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                        <span
                            class="w-1 h-1 flex rounded-full bg-gray-300 heading-dots duration-300 transition-all group-hover:bg-[var(--primary-color)]"></span>
                    </div>
                    <div
                        class="w-15 h-1 flex bg-gray-300 heading-line rounded duration-300 transition-all group-hover:bg-[var(--primary-color)]">
                    </div>
                </div>
            </button>

        </div>
        {{-- campaigns Basic Info section | step 01 --}}
        <div class=" w-full !p-4 duration-500 transition-all opacity-0 translate-y-5 campaigns-section" id="campaign-info">
            <div class="w-full flex flex-col gap-5 bg-white  rounded">
                <div class="w-full flex flex-col gap-3">
                    <label for="campaign-no" class="text-sm flex items-center ">Campaigns
                        #
                    </label>
                    <input type="text" name="campaign_no" placeholder="Add category title here" id="campaign-no"
                        value="  {{ $campaignId }}"
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                </div>
                <div class="w-full flex flex-col gap-3 ">
                    <label for="campaign-domain"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">select
                        Domain
                    </label>
                    <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                        {{-- Hidden input for form submission --}}
                        <input data-hidden-input type="hidden" id="campaign-domain" name="domain_category" value="">

                        {{-- Select Button --}}
                        <button data-select-btn type="button" id="selectBtn"
                            class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                            <span data-select-text id="selectText" class="text-gray-400">Select
                                Domains ...</span>
                            <svg data-chevron id="chevron" class="w-5 h-5 text-gray-400 transition-transform duration-200"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7">
                                </path>
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
                                @if (isset($domainCategory) && count($domainCategory) > 0)
                                    @foreach ($domainCategory as $category)
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

                </div>
                <div class="w-full flex flex-col gap-3 ">
                    <label for="post-quantity"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Post
                        Quantity

                    </label>
                    <input type="text" name="post_quantity" placeholder="Enter Post Quantity Here" id="post-quantity"
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                </div>
                <div class="w-full flex gap-3 !mt-2">
                    <a href="javascript:void(0)" id="continue_campaign_step_01"
                        class="flex !px-3 sm:!px-4 !py-2.5 sm:!py-3 text-sm sm:text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded-lg">
                        Continue
                        <span class="material-symbols-outlined">
                            arrow_circle_right
                        </span>
                    </a>

                </div>
            </div>
        </div>
        {{-- campaigns Add Article section | step 02  --}}
        <div class=" w-full !p-4 duration-500 transition-all opacity-0 translate-y-5 campaigns-section" id="add-articles">
            <div class="w-full flex flex-wrap gap-5 bg-white  rounded">
                {{-- ***** select article values here **** --}}
                <input type="text" name="selected_articles_val" id="selected_articles_val" hidden>
                {{-- *** selected articles here *** --}}
                <div class="flex flex-col gap-4 w-full lg:w-[49%] campaign-article-main-col">
                    <div class="w-full flex flex-col gap-3">
                        <label for="campaign-no" class="text-sm flex items-center ">Campaigns #
                            (Id)
                        </label>
                        <div class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600"
                            id="campiagn-id">
                            {{ $campaignId }}
                        </div>
                        {{-- selected articles it contains all selected articles --}}
                    </div>
                    <div class="w-full flex flex-col gap-3 ">
                        <label for="campaign-domain"
                            class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">select
                            Articles Niche
                        </label>
                        <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                            {{-- Hidden input for form submission --}}
                            <input data-hidden-input type="hidden" id="article-niche" name="article_niche"
                                value="">

                            {{-- Select Button --}}
                            <button data-select-btn type="button" id="selectBtn"
                                class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                <span data-select-text id="selectText" class="text-gray-400">Select
                                    Article ...</span>
                                <svg data-chevron id="chevron"
                                    class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7">
                                    </path>
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
                                    @if (isset($articleCategory) && count($articleCategory) > 0)
                                        @foreach ($articleCategory as $category)
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

                    </div>

                    <div class="w-full flex flex-col gap-3 ">
                        <label for=""
                            class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Article
                            Type
                        </label>
                        {{-- Radio buttons option of selecting article --}}
                        <div class="flex flex-wrap gap-4">
                            <div class="">
                                <label for="own_articles"
                                    class="flex gap-2 items-center cursor-pointer articles-opts-label">
                                    <span
                                        class='w-6 h-6 flex border border-gray-300 bg-gray-100 rounded-full items-center justify-center duration-300 transition-all'>
                                        <span
                                            class="material-symbols-outlined !text-[16px] text-white duration-300 transition-all opacity-0">
                                            check
                                        </span>
                                    </span>
                                    Use Own Articles
                                </label>
                                <input type="radio" name="sel_articles_opt" id="own_articles" value="own_article"
                                    class="article-sel-opts" hidden>
                            </div>
                            <div>
                                <label for="system_articles"
                                    class="flex gap-2 items-center cursor-pointer articles-opts-label">
                                    <span
                                        class='w-6 h-6 flex border border-gray-300 bg-gray-100 rounded-full items-center justify-center duration-300 transition-all'>
                                        <span
                                            class="material-symbols-outlined !text-[16px] text-white duration-300 transition-all opacity-0">
                                            check
                                        </span>
                                    </span>
                                    Use System Articles
                                </label>
                                <input type="radio" name="sel_articles_opt" value="system_article"
                                    id="system_articles" class="article-sel-opts" hidden>
                            </div>
                            <div>
                                <label for="language_articles"
                                    class="flex gap-2 items-center cursor-pointer articles-opts-label">
                                    <span
                                        class='w-6 h-6 flex border border-gray-300 bg-gray-100 rounded-full items-center justify-center duration-300 transition-all'>
                                        <span
                                            class="material-symbols-outlined !text-[16px] text-white duration-300 transition-all opacity-0">
                                            check
                                        </span>
                                    </span>
                                    Use Language Articles
                                </label>
                                <input type="radio" name="sel_articles_opt" value="language_article"
                                    id="language_articles" class="article-sel-opts" hidden>
                            </div>
                        </div>


                    </div>
                    {{-- this own article option box --}}
                    <div class="w-full flex flex-col gap-3 !mt-4 opacity-0  duration-500 transition-all hidden article-opt-box"
                        id="own_article">
                        <div class="w-full flex flex-col gap-4 bg-gray-50 rounded border border-gray-300 !p-4">
                            <label for="own-articles"
                                class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                                category - (own articles & set)
                            </label>
                            <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                                {{-- Hidden input for form submission --}}
                                <input data-hidden-input type="hidden" id="own-articles" name="own-articles"
                                    value="">

                                {{-- Select Button --}}
                                <button data-select-btn type="button" id="selectBtn"
                                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                    <span data-select-text id="selectText" class="text-gray-400">Select
                                        article sets ...</span>
                                    <svg data-chevron id="chevron"
                                        class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7">
                                        </path>
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
                                        @if (isset($articleSet) && count($articleSet) > 0)
                                            @foreach ($articleSet as $set)
                                                <button data-option-item type="button"
                                                    class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                                    data-value="{{ $set->id }}" data-label="{{ $set->name }}">
                                                    <span class="text-sm ">
                                                        {{ $set->name }}
                                                        {{ $set->articles_count > 0 ? ' - (' . $set->articles_count . ')' : '' }}</span>
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

                            <div class="w-full flex">
                                <a href="javascript:void(0)" id="add_own_article"
                                    class="flex !p-2 gap-2 items-center !py-3  relative text-sm font-normal w-fit  justify-center duration:300 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded ">
                                    <div
                                        class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin article-set-loader hidden">
                                    </div>
                                    Add Articles
                                </a>
                            </div>

                        </div>

                    </div>
                    {{-- this article search option box --}}
                    <div class="w-full flex flex-col gap-3 !mt-4 opacity-0 duration-500 transition-all hidden article-opt-box"
                        id="system_article">
                        <div class="w-full flex flex-col gap-4 bg-gray-50 rounded border border-gray-300 !p-4">
                            <h4 class="text-[16px]">Search Article by Niche</h4>
                            <input type="text" name="search-article-value" placeholder="search..."
                                id="search-article-inp" value=""
                                class="bg-white border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                            <div class="w-full flex" id="append_items_here"></div>

                            <div class="flex flex-wrap gap-4 !mt-2">
                                <div class="">
                                    <label for="in_title"
                                        class="flex gap-2 items-center cursor-pointer text-sm articles-search-label">
                                        <span
                                            class='w-5 h-5 flex border border-gray-300 bg-gray-100 rounded-full items-center justify-center duration-300 transition-all'>
                                            <span
                                                class="material-symbols-outlined !text-sm text-white duration-300 transition-all opacity-0">
                                                check
                                            </span>
                                        </span>
                                        In Title
                                    </label>
                                    <input type="radio" name="search_articles_opt" id="in_title" value="in_title"
                                        class="article-search-opts" hidden>
                                </div>
                                <div>
                                    <label for="in_article"
                                        class="flex gap-2 items-center cursor-pointer text-sm articles-search-label">
                                        <span
                                            class='w-5 h-5 flex border border-gray-300 bg-gray-100 rounded-full items-center justify-center duration-300 transition-all'>
                                            <span
                                                class="material-symbols-outlined !text-sm text-white duration-300 transition-all opacity-0">
                                                check
                                            </span>
                                        </span>
                                        In Article
                                    </label>
                                    <input type="radio" name="search_articles_opt" value="in_article" id="in_article"
                                        class="article-search-opts" hidden>
                                </div>
                            </div>
                            <div class="flex flex-wrap justify-between w-full">
                                <div class="w-[48%] flex items-center">
                                    <select name="searching-type" id="searching-type"
                                        class="bg-white border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                        <option value="1">similar</option>
                                        <option value="2">Exact match</option>
                                    </select>
                                </div>
                                <div class="w-[48%] flex items-center">
                                    <a href="javascript:void(0)" id="add_search_article"
                                        class="flex gap-3 !p-2 !py-3 !pl-5 relative text-sm font-normal w-full  justify-center duration:300 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded ">
                                        <span
                                            class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin  hidden search-articles-loader"></span>
                                        Search Articles
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- this language articles option box --}}
                    <div class="w-full flex flex-col gap-3 !mt-4 opacity-0 duration-500 transition-all hidden article-opt-box"
                        id="language_article">
                        <div class="w-full flex flex-col gap-4 bg-gray-50 rounded border border-gray-300 !p-4">
                            <div class="flex items-center justify-between">
                                <h4 class="text-[16px]">Select Language to Load Articles</h4>
                                <span class="text-sm text-gray-500">Post Quantity: <strong
                                        id="postQtyDisplay">0</strong></span>
                            </div>
                            <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                                {{-- Hidden input for form submission --}}
                                <input data-hidden-input type="hidden" id="language-articles-select"
                                    name="language_articles_id" value="">

                                {{-- Select Button --}}
                                <button data-select-btn type="button" id="selectBtn"
                                    class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                    <span data-select-text id="selectText" class="text-gray-400">Select
                                        Language ...</span>
                                    <svg data-chevron id="chevron"
                                        class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none"
                                        stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 9l-7 7-7-7">
                                        </path>
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
                                                placeholder="Search language...">
                                        </div>
                                    </div>

                                    {{-- Options List --}}
                                    <div data-options-list id="optionsList" class="max-h-64 overflow-y-auto">
                                        @if (isset($articleLanguages) && count($articleLanguages) > 0)
                                            @foreach ($articleLanguages as $lang)
                                                <button data-option-item type="button"
                                                    class="option-item w-full !px-4 !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                                    data-value="{{ $lang->id }}" data-label="{{ $lang->name }}"
                                                    data-article-count="{{ $lang->article_count }}">
                                                    <span class="text-sm">{{ $lang->name }}</span>
                                                    <span
                                                        class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded">{{ $lang->article_count }}
                                                        articles</span>
                                                </button>
                                            @endforeach
                                        @else
                                            <div class="!px-4 !py-8 text-center text-gray-400 text-sm">
                                                No languages with articles available
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Info message about available articles --}}
                            <div id="language-articles-info" class="hidden">
                                <div class="flex items-center gap-2 p-3 rounded" id="language-articles-status">
                                    <span class="material-symbols-outlined !text-lg"
                                        id="language-articles-icon">info</span>
                                    <span class="text-sm" id="language-articles-message"></span>
                                </div>
                            </div>

                            <div class="w-full flex">
                                <a href="javascript:void(0)" id="add_language_article"
                                    class="flex !p-2 gap-2 items-center !py-3 relative text-sm font-normal w-fit justify-center duration:300 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded ">
                                    <div
                                        class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin language-article-loader hidden">
                                    </div>
                                    Load Articles
                                </a>
                            </div>

                        </div>
                    </div>



                </div>
                <div class="hidden lg:flex w-[49%]"></div>
                {{-- continue button section --}}
                <div class="w-full flex justify-between gap-3 !mt-2">
                    <a href="javascript:void(0)" id=""
                        class="flex !px-3 sm:!px-4 !py-2.5 sm:!py-3 text-sm sm:text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-gray-200 text-gray-400 rounded-lg">
                        Back
                        <span class="material-symbols-outlined">
                            arrow_circle_left
                        </span>
                    </a>
                    <a href="javascript:void(0)" id="continue_campaign_step_02"
                        class="flex !px-3 sm:!px-4 !py-2.5 sm:!py-3 text-sm sm:text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded-lg">
                        Continue
                        <span class="material-symbols-outlined">
                            arrow_circle_right
                        </span>
                    </a>

                </div>
            </div>
        </div>
        {{-- campaigns Add Keywords & Url sections | step 03 --}}
        <div class="w-full !p-4 duration-500 transition-all campaigns-section" id="add-keywords-url">
            <div class="w-full">
                <button id="add-keywords-url-btn" type="button"
                    class="bg-[var(--primary-color)] !px-3 !py-2 sm:!p-3 text-sm sm:text-base text-white text-center rounded cursor-pointer w-full sm:w-auto">Add keywords &
                    Urls</button>
            </div>

            {{-- table for showing data  --}}
            <div class="flex flex-wrap overflow-x-auto !mt-6">
                <table class="w-full border border-gray-200 border-collapse text-sm " id="keywords-data-table">
                    <colgroup>
                        <col style="width: 33.3%">
                        <col style="width: 33.3%">
                        <col style="width: 33.3%">
                    </colgroup>
                    <thead>
                        <tr class="bg-[var(--primary-color)] text-white ">

                            @php
                                $tHead = ['url (Link)', 'keyword', 'media Links'];
                            @endphp
                            @foreach ($tHead as $t)
                                <th
                                    class="border border-gray-200 font-sans !font-normal !px-1.5 sm:!px-2 !py-2 sm:!py-3 text-center text-[11px] sm:text-sm md:text-base capitalize leading-tight whitespace-normal break-words">
                                    {{ $t }}
                                </th>
                            @endforeach
                        </tr>


                    </thead>
                    <tbody>
                        {{-- <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 font-sans !p-2">
                                xyz Link Here
                            </td>
                            <td class="border border-gray-200 font-sans !p-2">
                                xyz Keywords Here
                            </td>
                            <td class="border border-gray-200 font-sans !p-2">
                                xyz Media Link
                            </td>
                        </tr> --}}
                    </tbody>
                </table>
            </div>


            {{-- continue button section --}}
            <div class="w-full flex justify-between gap-3 !mt-4">
                <a href="javascript:void(0)" id=""
                    class="flex !px-3 sm:!px-4 !py-2.5 sm:!py-3 text-sm sm:text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-gray-200 text-gray-400 rounded-lg">
                    Back
                    <span class="material-symbols-outlined">
                        arrow_circle_left
                    </span>
                </a>
                <a href="javascript:void(0)" id="continue_campaign_step_03"
                    class="flex !px-3 sm:!px-4 !py-2.5 sm:!py-3 text-sm sm:text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded-lg">
                    Continue
                    <span class="material-symbols-outlined">
                        arrow_circle_right
                    </span>
                </a>

            </div>
            {{-- this is holding keywords data --}}
            {{-- <div class="w-full"> --}}
            <input type="text" name="keywordmethod" id="keyWordsMethod" hidden>
            <input type="text" name="keywordsDataHolder" id="keywordsDataHolder" hidden>
            {{-- </div> --}}
        </div>
        {{-- campaigns Add Domains sections | step 04 --}}
        <div class="w-full !p-4  duration-500 transition-all opacity-0 translate-y-5 campaigns-section"
            id="select-domains">

            <div class="w-full border-b border-[var(--primary-color)] flex items-center gap-2">
                <div class="flex flex-wrap items-center gap-2 w-full">
                    <label
                        class="!p-3 bg-[var(--primary-color)] border border-[var(--primary-color)] duration-300 transition-all text-white border-b-0 text-sm text-center cursor-pointer domain-tab-btn hover:border-[var(--primary-color)] hover:bg-[var(--primary-color)] hover:text-white"
                        for="random-domains">
                        Random Domains (<span class="dy-domain-count" id="selectedDomainCount">0</span>)
                        <input type="radio" name="sel_domains" id="random-domains" class="domain-method-inp"
                            data-id="randomSelectSection" value="0" checked hidden>
                    </label>
                    <label
                        class="!p-3 bg-gray-50 border border-gray-300 border-b-0 text-sm text-center duration-300 transition-all cursor-pointer domain-tab-btn hover:border-[var(--primary-color)] hover:bg-[var(--primary-color)] hover:text-white"
                        for="domain-sets">
                        Domain Sets (Domains - <span class="dy-domain-count" id="selectedSetDomainCount">0</span>)
                        <input type="radio" name="sel_domains" id="domain-sets" class="domain-method-inp"
                            data-id="domainSetSection" value="1" hidden>
                    </label>
                    <label
                        class="!p-3 bg-gray-50 border border-gray-300 border-b-0 text-sm text-center duration-300 transition-all cursor-pointer domain-tab-btn hover:border-[var(--primary-color)] hover:bg-[var(--primary-color)] hover:text-white"
                        for="manual-domains">
                        Manual Domains (<span class="dy-domain-count" id="manualDomainsCount">0</span>)
                        <input type="radio" name="sel_domains" id="manual-domains" class="domain-method-inp"
                            data-id="manualDomainSection" value="2" hidden>
                    </label>
                </div>
            </div>
            {{-- random domains based on the selected domain id in step 1 --}}
            <div class="w-full flex flex-col !mt-2  domains-sections" id="randomSelectSection">
                {{-- <div class="flex flex-wrap w-full justify-between !my-4 !pb-3 border-b border-b-gray-100">
                    <div class="w-[22%] flex flex-col gap-2 justify-center">
                        <select name="actions" id=""
                            class="bg-gray-100  border border-gray-200  !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                            <option value="">Select Domain Authority</option>
                            <option value="">90+</option>
                        </select>
                    </div>
                    <div class="w-[22%] flex flex-wrap items-center justify-center">
                        <select name="actions" id=""
                            class="bg-gray-100  border border-gray-200  !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                            <option value="">Select Domain Ranking</option>
                            <option value="">90+</option>
                        </select>
                    </div>
                    <div class="w-[22%] flex flex-wrap items-center justify-center">
                        <select name="actions" id=""
                            class="bg-gray-100  border border-gray-200  !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                            <option value="">Select Trust Flow</option>
                            <option value="">> 40</option>
                        </select>
                    </div>
                    <div class="w-[22%] flex flex-wrap items-center justify-center">
                        <select name="actions" id=""
                            class="bg-gray-100  border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                            <option value="">Select Spam Score</option>
                            <option value="">>> </option>
                        </select>
                    </div>
                    <div class="w-[10%] flex flex-wrap items-center justify-center">
                        <button type="button"
                            class="flex !p-3 w-full !px-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--sidebar-bg)] hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                            Filter
                        </button>
                    </div>
                </div> --}}
                <div class="w-full flex flex-col gap-3 max-h-[600px] overflow-hidden overflow-y-auto">

                    <table id="RandomDomainsTable" class="display w-full border border-gray-200 border-collapse text-sm ">

                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th><input type="checkbox" name="bulk_domain_select[]" id="selectAllDomains"
                                        class="scale-125 ">
                                    @php
                                        $tHead = ['sno', 'url', 'DA', 'TF', 'DR', 'SS'];
                                    @endphp
                                    @foreach ($tHead as $t)
                                <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                    {{ $t }}
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            {{-- @foreach ($sites as $index => $site)
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center "> <input
                                            type="checkbox" name="bulk_domain_select[]"
                                            id="sel-domain-check-box-{{ $index + 1 }}" class="domains"
                                            value="{{ $index + 1 }}" id="">
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $index + 1 }}
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        <label for="sel-domain-check-box-{{ $index + 1 }}"
                                            class="cursor-pointer w-full">
                                            {{ $site['url'] }}</label>
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site['DA'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site['TF'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site['DR'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site['SS'] }}</td>

                                </tr>
                            @endforeach --}}
                        </tbody>
                    </table>

                    <div id="domain-pagination" class="flex flex-wrap gap-2 mt-3 justify-center">
                    </div>
                    <div class="w-full flex justify-end !mt-2">
                        <div class="flex items-center gap-2">
                            <button type="button" id="autoSelectRandomDomainsBtn"
                                class="cursor-pointer bg-indigo-600 text-white rounded !px-3 !py-2 text-sm"
                                style="background-color:#4f46e5 !important;color:#ffffff !important;">
                                Auto Select Required
                            </button>
                            <span id="autoSelectRandomDomainsProgress" class="text-xs text-gray-700"></span>
                        </div>
                    </div>

                </div>

            </div>
            {{-- domain set thing start here --}}
            <div class="w-full flex flex-col hidden domains-sections" id="domainSetSection">
            <div class="w-full flex flex-wrap gap-3 items-start sm:items-center !mt-6">
                    <div class="w-full sm:w-1/5 flex flex-col">
                        <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                            {{-- Hidden input for form submission --}}
                            <input data-hidden-input type="hidden" id="domain-set-id" name="domain-set-id"
                                value="{{ old('category') }}">

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
                                    @if (isset($domainSets) && count($domainSets) > 0)
                                        @foreach ($domainSets as $set)
                                            <button data-option-item type="button"
                                                class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                                data-value="{{ $set->id }}" data-label="{{ $set->name }}">
                                                <span class="text-sm">{{ $set->name }} - ({{ (int) ($set->qty ?? 0) }})</span>
                                            </button>
                                        @endforeach
                                    @else
                                        <div class="!px-4 !py-8 text-center text-gray-400 text-sm">
                                            No options available
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="w-full sm:w-auto">
                        <button type="button" id="fetch-domains-set"
                            class="!p-3 flex items-center gap-2 justify-center text-sm cursor-pointer bg-black text-white rounded hover:bg-[var(--primary-color)] w-full">
                            <div
                                class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin loader hidden">
                            </div>
                            Select Domain Set
                        </button>
                    </div>
                </div>
                <div class="w-full flex flex-col max-h-[600px] overflow-hidden overflow-y-auto !mt-6">
                    {{-- filters here --}}

                    <table id="domainSetTable" class="display w-full border border-gray-200 border-collapse text-sm ">

                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th><input type="checkbox" name="bulk_set_domain_select[]" id="selectAllSetDomains"
                                        class="scale-125 ">
                                    @php
                                        $tHead = ['sno', 'url', 'DA', 'TF', 'DR', 'SS'];
                                    @endphp
                                    @foreach ($tHead as $t)
                                <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                    {{ $t }}
                                </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            {{-- @foreach ($sites as $index => $site)
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center "> <input
                                            type="checkbox" name="bulk_domain_select[]"
                                            id="sel-set-domain-check-box-{{ $index + 1 }}" class="setdomains"
                                            value="{{ $index + 1 }}" id="">
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $index + 1 }}
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        <label for="sel-set-domain-check-box-{{ $index + 1 }}"
                                            class="cursor-pointer w-full">
                                            {{ $site['url'] }}</label>
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site['DA'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site['TF'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site['DR'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site['SS'] }}</td>

                                </tr>
                            @endforeach --}}


                        </tbody>
                    </table>

                    {{-- ✅ PAGINATION GOES HERE --}}
                    <div class="w-full flex justify-center !mt-4">
                        <div id="domain-set-pagination" class="flex flex-wrap gap-2 items-center">
                        </div>
                    </div>
                    <div class="w-full flex justify-end !mt-2">
                        <div class="flex items-center gap-2">
                            <button type="button" id="autoSelectSetDomainsBtn"
                                class="cursor-pointer bg-indigo-600 text-white rounded !px-3 !py-2 text-sm"
                                style="background-color:#4f46e5 !important;color:#ffffff !important;">
                                Auto Select Required
                            </button>
                            <span id="autoSelectSetDomainsProgress" class="text-xs text-gray-700"></span>
                        </div>
                    </div>
                </div>
            </div>
            {{-- maunual-domains things in step 4 --}}
            <div class="w-full flex flex-col gap-3 !mt-3 domains-sections hidden" id="manualDomainSection">
                <label for=""
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5 ">Domain
                    Urls</label>
                <textarea name="" id="manual-domains-area"
                    class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none rounded" rows="20"
                    placeholder="Enter Media Link Here"></textarea>
            </div>
            {{-- storing the select domains in this input field --}}
            <input type="hidden" name="campaigns_domains" id="campaigns_domains_holder">
            {{-- passing the is_sticky thing so that i am explictly sending --}}
            <input type="hidden" name="is_sticky" value="{{ $is_sticky }}">
            {{-- domains storing fields end here --}}

            <div class="w-full flex justify-between gap-3 !mt-4">
                <a href="javascript:void(0)" id=""
                    class="flex !px-3 sm:!px-4 !py-2.5 sm:!py-3 text-sm sm:text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-gray-200 text-gray-400 rounded-lg">
                    Back
                    <span class="material-symbols-outlined">
                        arrow_circle_left
                    </span>
                </a>
                <button type="submit" href="javascript:void(0)" id="continue_campaign_step_04"
                    class="flex !px-3 sm:!px-4 !py-2.5 sm:!py-3 text-sm sm:text-lg font-normal bg-green-500 items-center gap-1 justify-center duration:500 transition-all text-white rounded-lg">

                    <span class="material-symbols-outlined check-icon">
                        check_circle
                    </span>
                    <div
                        class="w-5 h-5 border-3 border-gray-100 border-t-transparent rounded-full animate-spin loader hidden">
                    </div>
                    Run Campaign
                </button>

            </div>
        </div>

    </form>


@endsection

@section('popup')

    {{-- this is first pop layer --}}
    <div
        class="w-screen h-screen flex justify-center items-start fixed top-0 left-0 z-1100 overflow-hidden hidden dy-parent-div">
        {{-- overlay div --}}

        <div
            class="dy-set-overlay w-screen h-screen bg-black opacity-0 absolute top-0 left-0  duration-300 transition-all">
        </div>

        <div class="dy-pop-box w-[96%] sm:w-[80%] max-w-[850px] max-h-[calc(100vh-24px)] sm:max-h-none flex flex-col items-stretch !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-3 sm:!mt-[70px] opacity-0 -translate-y-[20%] linear  duration-600 transition-all min-w-0"
            id="dy-article-box">
            {{-- pop top bar --}}
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3 relative">
                <h6>Selected Articles <span class="dy-article-count" id="selectedCount">(0)</span></h6>
                <button type="button" class="cursor-pointer article-set-close" id='dy-close-btn'>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
                <div
                    class="w-5 h-5 border-3 border-[var(--primary-color)]  border-t-transparent rounded-full animate-spin pagination-loader hidden">
                </div>
            </div>

            <div class="w-full flex flex-col gap-3 !p-3 max-h-[560px] overflow-auto">

                <div class="overflow-x-auto !mt-3 w-full">
                    <table id="myTable" class="display w-full border border-gray-200 border-collapse text-sm ">
                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th><input type="checkbox" name="bulk_category_select[]" id="selectAll"
                                        class="scale-125 "></th>
                                @php
                                    $tHead = ['sno', 'title', 'Action'];
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
                            {{-- @foreach ($articles as $index => $article)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center "> <input
                                                type="checkbox" name="bulk_category_select[]"
                                                id="sel-check-box-{{ $index + 1 }}" class="articles"
                                                value="{{ $index + 1 }}" id="">
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $index + 1 }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            <label for="sel-check-box-{{ $index + 1 }}" class="cursor-pointer">
                                                {{ $article['title'] }}
                                        </td></label>

                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            <div class="flex flex-wrap gap-2 justify-center">

                                                <a href="javascript:void(0)" data-id="{{ $article['id'] }}"
                                                    class="bg-green-600 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-green-800 relative overflow dy-nested-pop-btn">
                                                    <span class="material-symbols-outlined !text-sm  text-white">
                                                        visibility
                                                    </span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach --}}
                        </tbody>
                    </table>
                    <!-- Pagination Container -->
                    <div id="pagination-container"
                        class="w-full flex justify-center items-center gap-1 flex-wrap !mt-3 !px-2">
                        <!-- Buttons injected by JS -->
                    </div>
                </div>

            </div>

            <div
                class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between !bg-gray-200 !px-3 !py-2 min-w-0 article-selected-modal-footer">
                <div class="flex flex-col gap-2 w-full min-w-0 sm:flex-1 sm:flex-row sm:flex-wrap sm:items-center">
                    <input type="text" name="randomSelect" id="randomSelect"
                        class="randomSelInp !p-2 bg-white rounded outline-none text-sm text-center w-full sm:w-28 sm:max-w-[10rem] shrink-0"
                        placeholder="Add random articles quantity">
                    <div class="flex flex-wrap gap-2 items-center w-full sm:w-auto sm:flex-1 min-w-0">
                        <button type="button" id="randomSelector"
                            class="randomSelBtn cursor-pointer bg-blue-500 text-white rounded !p-2 text-sm shrink-0 flex-1 min-w-[5.5rem] sm:flex-none">
                            Random
                        </button>
                        <button type="button" id="autoSelectArticlesBtn"
                            class="cursor-pointer bg-indigo-600 text-white rounded !p-2 text-sm shrink-0 flex-1 min-w-[8rem] sm:flex-none"
                            style="background-color:#4f46e5 !important;color:#ffffff !important;">
                            Auto Select Required
                        </button>
                        <span id="autoSelectArticlesProgress"
                            class="text-xs text-gray-700 w-full sm:w-auto sm:flex-1 min-w-0"></span>
                    </div>
                </div>
                <a href="#" id="multiple-articles-select"
                    class="cursor-pointer bg-green-500 text-white rounded !p-3 text-center whitespace-nowrap w-full sm:w-auto shrink-0">
                    Proceed
                </a>
            </div>

        </div>

    </div>

    {{-- this is nested popup | hidden | opacity-0 | opacity-0 -translate-y-[20%] | --}}
    <div
        class="w-screen h-screen flex justify-center items-start fixed top-0 left-0 z-1200 overflow-hidden hidden  dy-nested-parent-div">
        <div
            class="dy-nested-set-overlay w-screen h-screen bg-black/30 opacity-0 absolute top-0 left-0 backdrop-blur-3xl  duration-300 transition-all">
        </div>
        <div class="dy-nested-pop-box w-[80%] max-w-[850px] flex flex-col items-center !shadow-2xl bg-gray-50 border border-gray-300 z-2 opacity-0 -translate-y-[20%] rounded !mt-[70px]  linear  duration-600 transition-all"
            id="dy-nested-article-box">
            {{-- pop top bar --}}
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6>Selected Articles</h6>
                <button type="button" class="cursor-pointer nested-article-set-close" id='dy-nested-close-btn'>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
            </div>
            <div class="w-full flex flex-col gap-3 !p-3 max-h-[560px] overflow-auto">

                Lorem ipsum dolor sit amet consectetur adipisicing elit. Doloremque laborum voluptates aspernatur cum,
                tempora delectus architecto ipsam atque enim temporibus quo nam ab aliquid nulla nesciunt tempore quod.
                Asperiores, unde?
                Nulla provident deleniti corrupti. Alias, quibusdam! Quidem fugit modi suscipit dolore illum a aliquid
                perspiciatis rem cupiditate maiores. Reprehenderit assumenda perferendis nobis nemo tempora voluptatum
                repellendus sunt est labore cum.



            </div>

            {{-- <div class="w-full flex justify-between items-center !bg-gray-200 !px-3 !py-2">
                <div class="flex gap-2 items-center">
                    <input type="text" name="randomSelect" id="randomSelect"
                        class="randomSelInp !p-2 bg-white rounded outline-none text-sm text-center"
                        placeholder="Add random articles quantity">
                    <button id="randomSelector"
                        class="randomSelBtn cursor-pointer bg-blue-500 text-white  rounded !p-2 text-sm">
                        Random
                    </button>
                </div>
                <a href="#" type="button" class="cursor-pointer bg-green-500 text-white rounded !p-3">
                    Proceed
                </a>
            </div> --}}
        </div>


    </div>
    {{-- this is popup for keywords & url --}}

    {{-- this is first pop layer | it is keywords data pop --}}
    <div
        class="w-screen h-[100dvh] sm:h-screen flex justify-center items-start fixed top-0 left-0 z-[1100] overflow-hidden hidden dy-key-parent-div">
        <div
            class="dy-key-set-overlay w-screen h-screen bg-black opacity-0 absolute top-0 left-0 duration-300 transition-all">
        </div>
        <div class="dy-key-pop-box w-full sm:w-[90%] max-w-[min(100vw,1280px)] h-[100dvh] sm:h-auto max-h-[100dvh] sm:max-h-none flex flex-col !shadow-2xl bg-gray-50 border-0 sm:border border-gray-300 z-2 rounded-none sm:rounded !mt-0 sm:!mt-[70px] opacity-0 -translate-y-[20%] linear duration-600 transition-all min-w-0"
            id="dy-key-article-box">
            {{-- pop top bar --}}
            <div class="w-full flex items-center justify-between gap-3 !bg-gray-200 !px-3 !py-2.5 shrink-0 border-b border-gray-300">
                <div class="min-w-0">
                    <h6 class="text-sm sm:text-base font-semibold leading-tight m-0">URL Keywords</h6>
                    <p class="text-xs text-gray-600 m-0 !mt-0.5">Post Quantity <span class="dy-post-count font-medium" id="PostQuantity">(0)</span></p>
                </div>
                <button type="button" class="cursor-pointer article-set-close shrink-0 flex items-center justify-center w-9 h-9 rounded hover:bg-gray-300/60" id='dy-key-close-btn' aria-label="Close">
                    <span class="material-symbols-outlined !text-xl">close</span>
                </button>
            </div>
            <div class="dy-key-pop-body w-full flex flex-col gap-3 !p-3 flex-1 min-h-0 overflow-y-auto">

                <div class="keyword-method-tabs w-full border-b border-[var(--primary-color)] overflow-x-auto pb-0">
                    <div class="keyword-method-tabs-inner">
                    <label
                        class="keyword-tab-btn !px-2.5 !py-2 sm:!p-3 bg-[var(--primary-color)] border border-[var(--primary-color)] duration-300 transition-all text-white border-b-0 text-xs sm:text-sm text-center cursor-pointer"
                        id="add_keyword_url">
                        Add Keyword & Url
                        <input type="radio" name="keyword_data" id="add_keyword_url" class="keyword-method-inp"
                            value="normal" data-id="keyword-url-container" checked hidden>
                    </label>
                    <label
                        class="keyword-tab-btn !px-2.5 !py-2 sm:!p-3 bg-gray-50 border border-gray-300 border-b-0 text-xs sm:text-sm text-center duration-300 transition-all cursor-pointer"
                        id="add_bulk_keyword_url">
                        Bulk Keyword & Url
                        <input type="radio" name="keyword_data" id="add_bulk_keyword_url" class="keyword-method-inp"
                            value="bulk" data-id="bulk-keyword-url-container" hidden>
                    </label>
                    <label
                        class="keyword-tab-btn !px-2.5 !py-2 sm:!p-3 bg-gray-50 border border-gray-300 border-b-0 text-xs sm:text-sm text-center duration-300 transition-all cursor-pointer"
                        id="add_multi_level_keyword_url">
                        Multi Level
                        <input type="radio" name="keyword_data" id="add_multi_level_keyword_url"
                            class="keyword-method-inp" value="multiple" data-id="multi-level-keyword-url-container"
                            hidden>
                    </label>
                    <label
                        class="keyword-tab-btn !px-2.5 !py-2 sm:!p-3 bg-gray-50 border border-gray-300 border-b-0 text-xs sm:text-sm text-center duration-300 transition-all cursor-pointer"
                        id="add_multi_bulk_keyword_url">
                        Multi-bulk
                        <input type="radio" name="keyword_data" id="add_multi_bulk_keyword_url"
                            class="keyword-method-inp" value="multi_bulk"
                            data-id="multi-bulk-keyword-url-container" hidden>
                    </label>
                    <label
                        class="keyword-tab-btn !px-2.5 !py-2 sm:!p-3 bg-gray-50 border border-gray-300 border-b-0 text-xs sm:text-sm text-center duration-300 transition-all cursor-pointer"
                        id="add_raw_html_keyword_url">
                        Raw HTML
                        <input type="radio" name="keyword_data" id="add_raw_html_keyword_url"
                            class="keyword-method-inp" value="raw_html"
                            data-id="raw-html-keyword-url-container" hidden>
                    </label>
                    </div>
                </div>
                <div class="w-full flex flex-col keyword-tab-sec min-h-0"
                    id="keyword-url-container">
                    {{-- keyword url box here --}}
                    <div class="flex w-full flex-col lg:flex-row bg-orange-100 keyword-url-box static-box relative keyword-mobile-stack rounded border border-orange-200">
                        <div class="w-full lg:w-3/5 flex flex-col gap-3 !p-3 sm:!p-4 !pt-10 keyword-mobile-main">
                            <div class="w-full flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2">
                                <label for=""
                                    class="text-sm font-medium flex items-center shrink-0 after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full sm:w-1/5 keyword-mobile-label">Client
                                    Url</label>
                                <div class="w-full sm:w-4/5 flex flex-col sm:flex-row gap-2 sm:items-center keyword-mobile-input-wrap">
                                    <input type="text" placeholder="Enter Url"
                                        class="bg-white !p-2.5 text-sm outline-none border border-gray-300 w-full sm:w-[78%] client-url keyword-mobile-input-main rounded">
                                    <input type="text" value="0"
                                        class="bg-white !p-2.5 text-sm outline-none text-center border border-gray-300 w-full sm:w-1/5 client-url-quantity num-inp keyword-mobile-qty rounded">
                                </div>
                            </div>
                            <div class="w-full flex flex-col sm:flex-row sm:items-start gap-1.5 sm:gap-2">
                                <label for=""
                                    class="text-sm font-medium flex items-center shrink-0 after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full sm:w-1/5 keyword-mobile-label">Media
                                    Link</label>
                                <div class="w-full sm:w-4/5 keyword-mobile-input-wrap">
                                    <textarea name="" id=""
                                        class="bg-white !p-2.5 text-sm outline-none border border-gray-300 w-full resize-y media-link rounded min-h-[72px]" rows="2"
                                        placeholder="Enter Media Link Here"></textarea>
                                </div>
                            </div>
                            <div class="w-full flex items-center justify-end">
                                <button type="button"
                                    class="!px-2.5 !py-1 bg-red-600 rounded text-xs sm:text-sm cursor-pointer text-white remove-keyword-box">Delete</button>
                            </div>
                        </div>
                        <div class="w-full lg:w-2/5 flex flex-col gap-1.5 !p-3 sm:!p-4 border-t lg:border-t-0 lg:border-l border-orange-200 keyword-mobile-side">
                            <label for=""
                                class="text-sm font-medium flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Client
                                Keyword</label>
                            <div class="w-full flex flex-col sm:flex-row gap-2 keywords-area-parent">
                                <div class="w-full sm:w-[78%] keyword-mobile-input-main min-w-0">
                                    <textarea name="" id="" rows="5"
                                        class="bg-white !p-2.5 text-sm outline-none border border-gray-300 w-full resize-y keywords-area rounded min-h-[100px]"></textarea>
                                </div>
                                <div class="w-full sm:w-[22%] keyword-mobile-qty min-w-0">
                                    <textarea name="" id="" rows="5"
                                        class="bg-white !p-2.5 text-sm outline-none border border-gray-300 w-full resize-y text-center keywords-quantity-area focus:border-[var(--primary-color)] rounded min-h-[60px] sm:min-h-[100px]" placeholder="Qty"></textarea>
                                </div>
                            </div>
                        </div>
                        <div
                            class="box-count flex !px-2.5 !py-0.5 text-xs sm:text-sm font-semibold bg-[var(--primary-color)] text-white rounded absolute top-2.5 left-2.5">
                            01</div>
                    </div>
                </div>

                <div class="w-full add-more-window keyword-tab-sec">
                    <div class="w-full flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-sm keyword-progress-wrap">
                        <p class="m-0">Total <span class="total-box-count font-medium">1</span></p>
                        <a href="javascript:void(0)" class="inline-flex items-center justify-center bg-blue-500 hover:bg-blue-600 !px-3 !py-2 text-sm rounded text-white w-full sm:w-auto text-center"
                            id="add-more-keyword-URL">+ Add More Url</a>
                    </div>
                </div>
                {{-- bulk url div here --}}
                <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:justify-between max-h-[320px] overflow-y-auto bg-gray-100 keyword-tab-sec hidden !p-2 sm:!p-0"
                    id="bulk-keyword-url-container">
                    <div class="w-full sm:w-[32%] flex flex-col gap-2 min-w-0">
                        <div class="flex items-center ">
                            <label for="bulk-client-urls"
                                class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                                Client Urls
                            </label>
                            <span class="bulk-count text-sm flex !ml-[2px]"></span>
                        </div>
                        <textarea name="" id="bulk-client-urls"
                            class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none bulk-input" rows="12"></textarea>
                    </div>
                    <div class="w-full sm:w-[32%] flex flex-col gap-2 min-w-0">
                        <div class="flex items-center">
                            <label for="bulk-client-keywords"
                                class="text-sm flex  items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                                Client Keywords
                            </label>
                            <span class="bulk-count text-sm flex !ml-[2px]"></span>
                        </div>
                        <textarea name="" id="bulk-client-keywords"
                            class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none bulk-input" rows="12"></textarea>
                    </div>
                    <div class="w-full sm:w-[32%] flex flex-col gap-2 min-w-0">
                        <div class="flex items-center !mt-0 sm:!mt-1">
                            <label for="bulk-media-links" class="text-sm flex items-center ">
                                Media Links
                            </label>
                            <span class="bulk-count text-sm flex !ml-[2px]"></span>
                        </div>
                        <textarea name="" id="bulk-media-links"
                            class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none bulk-input" rows="12"></textarea>
                    </div>
                </div>
                {{-- multi-bulk url & keyword section --}}
                <div class="w-full flex flex-col gap-3 max-h-[min(70vh,720px)] overflow-hidden overflow-y-auto bg-gray-100 keyword-tab-sec hidden min-w-0"
                    id="multi-bulk-keyword-url-container">
                    <div
                        class="w-full flex flex-col gap-3 rounded border border-blue-200 bg-gradient-to-r from-blue-50 to-indigo-50 !p-3 sm:!p-4">
                        <p class="text-sm text-gray-700">
                            Add up to 5 URL/Keyword pairs. Pair 1 must match full post quantity. Pair 2-5 can be shorter and apply from website 1 onward.
                        </p>
                        <div class="flex flex-wrap gap-2 text-xs sm:text-sm">
                            <span class="rounded bg-white border border-blue-200 !px-2 !py-1">
                                Post Quantity: <strong id="multi-bulk-post-qty">0</strong>
                            </span>
                            <span class="rounded bg-white border border-blue-200 !px-2 !py-1">
                                Filled Pairs: <strong id="multi-bulk-filled-pairs">0</strong>/5
                            </span>
                            <span class="rounded bg-white border border-blue-200 !px-2 !py-1">
                                Total URL Lines: <strong id="multi-bulk-total-url-lines">0</strong>
                            </span>
                            <span class="rounded bg-white border border-blue-200 !px-2 !py-1">
                                Total Keyword Lines: <strong id="multi-bulk-total-keyword-lines">0</strong>
                            </span>
                        </div>
                    </div>
                    {{-- Horizontal scroll: one wide "pair" column at a time; client scrolls X to see Pair 1–5 --}}
                    <div class="w-full min-w-0 rounded border border-dashed border-gray-300 bg-white/60 !px-2 !py-2">
                        <p class="text-xs sm:text-sm text-gray-700 !mb-2">
                            <span class="font-semibold text-[var(--primary-color)]">How to use:</span>
                            Scroll <strong>left ↔ right</strong> (scrollbar under the pairs, or trackpad / touch swipe) to open
                            <strong>Pair 1</strong> through <strong>Pair 5</strong>. Each block is one pair: URLs on top, keywords below.
                        </p>
                        <div
                            class="multi-bulk-x-scroll w-full overflow-x-auto overflow-y-visible pb-2 scroll-smooth snap-x snap-mandatory [-webkit-overflow-scrolling:touch]">
                            <div class="flex flex-nowrap gap-4 w-max min-h-0 !py-1 !pr-1">
                            @for ($pairIndex = 1; $pairIndex <= 5; $pairIndex++)
                                <div
                                    class="bg-white border border-gray-200 rounded-lg shadow-sm hover:shadow-md duration-300 !p-4 flex flex-col gap-3 multi-bulk-pair-card flex-shrink-0 snap-start w-[min(92vw,440px)] sm:w-[400px] max-w-[440px] min-w-[280px]">
                                    <div class="flex items-center justify-between">
                                        <h6 class="text-sm font-semibold text-[var(--primary-color)]">Pair {{ $pairIndex }}</h6>
                                        <span
                                            class="text-[11px] rounded bg-indigo-100 text-indigo-700 !px-2 !py-[2px]">#{{ $pairIndex }}</span>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label for="multi-bulk-url-{{ $pairIndex }}" class="text-sm">
                                            Client Url {{ $pairIndex }}
                                        </label>
                                        <div class="text-xs text-gray-500">
                                            Lines: <span class="font-semibold multi-bulk-url-count">0</span>
                                        </div>
                                        <textarea id="multi-bulk-url-{{ $pairIndex }}"
                                            class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 rounded w-full min-w-0 resize-y multi-bulk-url focus:border-[var(--primary-color)]"
                                            rows="10" placeholder="One URL per line"></textarea>
                                    </div>
                                    <div class="flex flex-col gap-1">
                                        <label for="multi-bulk-keyword-{{ $pairIndex }}"
                                            class="text-sm after:content-['*'] after:ml-1 after:text-[var(--primary-color)]">
                                            Client Keyword {{ $pairIndex }}
                                        </label>
                                        <div class="text-xs text-gray-500">
                                            Lines: <span class="font-semibold multi-bulk-keyword-count">0</span>
                                        </div>
                                        <textarea id="multi-bulk-keyword-{{ $pairIndex }}"
                                            class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 rounded w-full min-w-0 resize-y multi-bulk-keyword focus:border-[var(--primary-color)]"
                                            rows="10" placeholder="One keyword per line"></textarea>
                                    </div>
                                </div>
                            @endfor
                            </div>
                        </div>
                    </div>
                </div>
                {{-- multi url & keyword section here --}}
                <div class="w-full flex flex-col gap-1 justify-between overflow-hidden overflow-y-auto keyword-tab-sec hidden"
                    id="multi-level-keyword-url-container">
                    {{-- container for showing & attaching multi keyword & url box --}}
                    <div class="w-full flex flex-col gap-1 max-h-[320px] overflow-hidden overflow-y-auto"
                        id="multi-key-url-box-parent">
                        <div class="flex w-full flex-col multi-keyword-url-box relative">
                            {{-- apply for row --}}
                            <div
                                class="w-full flex items-center !px-4 !py-2 !pr-10  bg-[var(--primary-color)] justify-end cursor-pointer relative multi-keyword-url-collapser">
                                <div class="w-1/2 flex items-center">
                                    <span
                                        class="multi-box-count flex !px-3 !py-1 text-sm font-semibold text-white rounded">
                                        01</span>
                                </div>
                                <div class="w-1/2 flex gap-2 justify-end items-center">
                                    <label for="" class="text-white">Apply for</label>
                                    <input type="text" value="0"
                                        class="bg-gray-50 !p-2 text-sm outline-none text-center border border-gray-300 w-1/5 multi-keyword-url-box-quantity num-inp">
                                </div>
                                <span
                                    class="material-symbols-outlined  text-gray-100 absolute top-1/2 -translate-y-1/2 right-2 duration-500 transition-all arrow-rotate">
                                    keyboard_arrow_up
                                </span>
                            </div>
                            <div
                                class="w-full flex flex-col duration-300 bg-orange-100 transition-all !pb-2 multi-keyword-url-accordion">
                                {{-- media link row --}}
                                <div class="w-full flex flex-col gap-2 items-center !px-4 !py-1">
                                    <label for=""
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full ">Media
                                        Link</label>
                                    <input type="text" placeholder="Enter Url"
                                        class="bg-gray-50 !p-4 text-sm outline-none border border-gray-300 w-full multiple-box-media-link">

                                </div>
                                {{-- add multiple urls & keywords button div --}}
                                <div class="w-full flex items-center !px-4 !py-2">
                                    <div class="w-1/2 flex">
                                        <label for=""
                                            class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-full ">Add
                                            Multiple Urls & Keywords
                                        </label>
                                    </div>
                                    <div class="w-1/2 flex gap-2 justify-end items-center">
                                        <button type="button"
                                            class="w-6 h-6 cursor-pointer bg-green-500 flex items-center text-white outline-none add-multipe-box justify-center rounded">
                                            <span class="material-symbols-outlined !text-sm">
                                                add
                                            </span>
                                        </button>
                                    </div>
                                </div>
                                {{-- multiple urls & keywords divs --}}
                                <div
                                    class="w-full flex flex-col gap-1 max-h-[120px] overflow-hidden overflow-y-auto multi-url-keyword-attachment-box">
                                    <div
                                        class="w-full flex flex-col gap-2 md:flex-row mf:gap-0 md:justify-between relative !px-10 !py-1 multi-keyword-url-row">
                                        <span
                                            class="multi-keyword-url-count cursor-move flex items-center justify-center absolute top-1/2 -translate-y-1/2 left-2 text-white bg-black w-6 h-6 !p-1 text-[12px] rounded">01</span>
                                        <div class="w-full md:w-[49.5%] flex">
                                            <input type="text" placeholder="Enter Url"
                                                class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-url-inp">
                                        </div>
                                        <div class="w-full md:w-[49.5%] flex">
                                            <input type="text" placeholder="Enter Keyword"
                                                class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full multi-keyowrd-inp">
                                        </div>
                                        <button type="button"
                                            class="remove-multi-keyword-url-row w-6 h-6 bg-red-600 rounded-full text-white flex items-center justify-center absolute top-1/2 -translate-y-1/2 right-2 cursor-pointer">
                                            <span class="material-symbols-outlined !text-sm">
                                                close
                                            </span>
                                        </button>
                                    </div>

                                </div>
                                {{-- delete button div --}}
                                <div class="w-full flex flex-col items-start !px-4 !py-1 !mt-2">
                                    <button type="button"
                                        class="!p-1 bg-red-600 rounded text-sm cursor-pointer text-white remove-multi-keyword-box">delete</button>
                                </div>
                            </div>
                        </div>


                    </div>
                    {{-- Add multi keyword & url section --}}

                    <div class="w-full flex flex-wrap justify-end !py-2 add-more-multi-window">
                        <div class="w-full sm:w-1/2 flex flex-wrap sm:flex-nowrap gap-2 sm:gap-0 justify-between items-center text-sm multi-progress-wrap">
                            <p>Total <span class="multi-total-box-count">1</span></p>
                            <a href="javascript:void(0)" class="bg-yellow-400 !p-2 text-sm rounded text-white "
                                id="add-more-multi-keyword-Url">+Add More</a>
                        </div>

                    </div>
                </div>

                {{-- Raw HTML Anchors section --}}
                <div class="w-full flex flex-col gap-3 max-h-[320px] overflow-hidden overflow-y-auto bg-gray-100 keyword-tab-sec hidden"
                    id="raw-html-keyword-url-container">
                    <div class="w-full flex flex-col gap-3 !p-4">
                        <div class="w-full bg-blue-50 border border-blue-200 rounded !p-3">
                            <h4 class="text-sm font-semibold text-blue-800 !mb-2">Instructions:</h4>
                            <ul class="text-xs text-blue-700 list-disc !pl-5 space-y-1">
                                <li>Paste anchor tags directly (e.g., <code>&lt;a href="url" rel="nofollow sponsored external"&gt;keyword&lt;/a&gt;</code>)</li>
                                <li>One line per post, or separate multiple anchors per line with commas (max 5 per line)</li>
                                <li>Total lines must equal Post Quantity: <strong id="raw-html-post-qty">0</strong></li>
                                <li>System will automatically extract URLs, keywords, and all rel attributes</li>
                                <li>Supports all current and future rel attributes (nofollow, sponsored, ugc, noopener, noreferrer, external, bookmark, author, license, etc.)</li>
                            </ul>
                        </div>

                        <div class="w-full flex flex-col gap-2">
                            <div class="flex items-center justify-between">
                                <label for="raw-html-anchors"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                                    Raw HTML Anchors
                                </label>
                                <span class="text-xs text-gray-600">Lines: <strong id="raw-html-line-count">0</strong></span>
                            </div>
                            <textarea id="raw-html-anchors"
                                class="bg-gray-50 !p-3 text-sm outline-none border border-gray-300 w-full resize-none font-mono"
                                rows="14"
                                placeholder='<a href="https://example.com" rel="nofollow sponsored">keyword 1</a>
<a href="https://example2.com" rel="ugc">keyword 2</a>, <a href="https://example3.com">keyword 3</a>
<a href="https://example4.com" rel="noopener noreferrer">keyword 4</a>'></textarea>
                        </div>

                        <div class="w-full bg-yellow-50 border border-yellow-200 rounded !p-3">
                            <p class="text-xs text-yellow-800">
                                <strong>Note:</strong> Media links are not supported in Raw HTML mode. Use other tabs if you need media links.
                            </p>
                        </div>
                    </div>
                </div>


            </div>
            <div class="keyword-modal-footer w-full flex flex-col gap-3 !bg-gray-200 !px-3 !py-3 shrink-0 border-t border-gray-300">
                <div class="keyword-modal-rel-grid">
                    <div class="keyword-modal-rel-item">
                        <input type="checkbox" name="no_follow" id="no_follow" value="1">
                        <label for="no_follow">No Follow</label>
                    </div>

                    <div class="keyword-modal-rel-item">
                        <input type="checkbox" name="sponsored_link" id="sponsored_link" value="1">
                        <label for="sponsored_link">Sponsored</label>
                    </div>

                    <div class="keyword-modal-rel-item">
                        <input type="checkbox" name="ugc_link" id="ugc_link" value="1">
                        <label for="ugc_link">UGC</label>
                    </div>

                    <div class="keyword-modal-rel-item">
                        <input type="checkbox" name="noopener_link" id="noopener_link" value="1">
                        <label for="noopener_link">Noopener</label>
                    </div>

                    <div class="keyword-modal-rel-item sm:col-span-1">
                        <input type="checkbox" name="noreferrer_link" id="noreferrer_link" value="1">
                        <label for="noreferrer_link">Noreferrer</label>
                    </div>
                </div>
                <a href="#" type="button" id="add-keywords-links"
                    class="cursor-pointer bg-green-600 hover:bg-green-700 text-white rounded !py-2.5 !px-4 text-sm font-medium text-center w-full sm:w-auto sm:self-end">
                    Save change
                </a>
            </div>
        </div>
    </div>

    <div
        class="w-screen h-screen flex justify-center items-center fixed top-0 left-0 z-[1200] overflow-hidden bg-white/40 domain-loader-pop hidden opacity-0 transition-all duration-500">

        <div class="w-16 h-16 border-4 border-[var(--primary-color)] border-t-gray-100 rounded-full animate-spin ">
        </div>




    </div>
@endsection


@push('scripts')
    {{-- <script src="{{ asset('js/general.js') }}"></script> --}}
    {{-- <script src="{{ asset('js/selectBox.js') }}"></script> --}}





    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>

    <script type="module" src="{{ asset('js/create-campaign.js') }}"></script>
@endpush
