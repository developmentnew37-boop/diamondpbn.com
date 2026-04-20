@extends('admin.layout.layout')

@section('title', 'Create PBN Hidden Links Campaign')

@push('style')
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.4/css/dataTables.dataTables.min.css">

    <style>
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
    </style>
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
                        <a href="{{ route('admin.hidden.link.campaign.index') }}" class="breadcrumb-link">PBN Hidden Links</a>
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
                    'sidebar_campaign_no',
                    'domain_category',
                    'sidebar_quantity',
                    'keyword_url_data',
                    'domains_method',
                    'domains',
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


    <form action="{{ route('admin.hidden.link.campaign.store') }}" method="POST"
        class="w-full max-w-full min-w-0 flex flex-wrap justify-between items-start content-card overflow-x-hidden" id="sidebar-campaign">
        @csrf
        <h2 class="text-base sm:text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-full max-w-full sm:w-fit !p-2 rounded">Create Hidden Link
            Campaigns
        </h2>
        {{-- xxxxxxxxxxxxxxxxxx campaigns button xxxxxxxxxxxxxxxxxxxxxxxxxxxx --}}
        <div class="campaign-create-tabs w-full !p-2">
            <button data-id="campaign-info" type="button"
                class="group flex flex-col gap-3  rounded !p-2 cursor-pointer text-[var(--primary-color)] duration-300 transition-all hover:text-[var(--primary-color)]  w-fit text-lg tab-switcher">
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
            <button data-id="add-keywords-url" type="button"
                class="group flex flex-col gap-3  rounded !p-2 cursor-pointer duration-300 transition-all hover:text-[var(--primary-color)]  w-fit text-lg tab-switcher">
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
                class="group flex flex-col gap-3  rounded !p-2 cursor-pointer duration-300 transition-all hover:text-[var(--primary-color)]  w-fit text-lg tab-switcher">
                <span>Select Domains</span>
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
        <div class=" w-full !p-4 duration-500 transition-all campaigns-section " id="campaign-info">
            <div class="w-full flex flex-col gap-5 bg-white  rounded">
                <div class="w-full flex flex-col gap-3">
                    <label for="campaign-no" class="text-sm flex items-center ">Campaigns
                        #
                    </label>
                    {{-- campaign_no --}}
                    <input type="text" name="campaign_no" placeholder="Add category title here" id="campaign-no"
                        value="{{ $campaignId }}  "
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                </div>
                <div class="w-full flex flex-col gap-3 ">
                    <label for="campaign-domain"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">select
                        Domain
                    </label>
                    <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                        {{-- Hidden input for form submission --}}
                        <input data-hidden-input type="hidden" id="campaign-domain" name="domain_category_id"
                            value="">

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
                                    <input data-search-input type="text" id="domainSetsearchInput"
                                        class="w-full !pl-10 !pr-4 !py-2 bg-gray-100 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)]   text-sm"
                                        placeholder="Search...">
                                </div>
                            </div>

                            {{-- Options List --}}
                            <div data-options-list id="optionsList" class="max-h-64 overflow-y-auto">
                                @if (isset($domainCategory) && count($domainCategory) > 0)
                                    @foreach ($domainCategory as $domainCat)
                                        <button data-option-item type="button"
                                            class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                            data-value="{{ $domainCat->id }}" data-label="{{ $domainCat->name }}">
                                            <span class="text-sm ">{{ $domainCat->name }}</span>
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
                    <label for="hidden-quantity"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Sidebar
                        Quantity
                    </label>
                    {{-- sidebar_quantity --}}
                    <input type="text" name="sidebar_quantity" placeholder="Enter Sidebar Quantity Here"
                        id="hidden-quantity"
                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                </div>
                <div class="w-full flex gap-3 !mt-2">
                    <a href="javascript:void(0)" id="continue_campaign_step_01"
                        class="flex !px-4 !py-3 text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded-lg ">
                        Continue
                        <span class="material-symbols-outlined">
                            arrow_circle_right
                        </span>
                    </a>
                </div>
            </div>
        </div>
        {{-- campaigns Add Keywords & Url sections | step 02   --}}
        <div class="w-full !p-4 duration-500 transition-all campaigns-section hidden" id="add-keywords-url">
            <div class="w-full">
                <button type="button" id="add-keywords-url-btn"
                    class="bg-[var(--primary-color)] !p-3 text-white text-center rounded cursor-pointer">Add keywords &
                    Urls</button>
            </div>

            {{-- table for showing data  --}}
            <div class="flex flex-wrap overflow-x-auto !mt-6">
                <table class="w-full border border-gray-200 border-collapse text-sm " id="keywords-data-table">
                    <colgroup>
                        <col style="width: 50%">
                        <col style="width: 50%">
                    </colgroup>
                    <thead>
                        <tr class="bg-[var(--primary-color)] text-white ">
                            @php
                                $tHead = ['url (Link)', 'keyword'];
                            @endphp
                            @foreach ($tHead as $t)
                                <th
                                    class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-xl capitalize">
                                    {{ $t }}
                                </th>
                            @endforeach
                        </tr>


                    </thead>
                    <tbody>
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 font-sans !p-2">
                                -
                            </td>
                            <td class="border border-gray-200 font-sans !p-2">
                                -
                            </td>

                        </tr>
                    </tbody>
                </table>
            </div>


            {{-- continue button section --}}
            <div class="w-full flex justify-between gap-3 !mt-4">
                <a href="javascript:void(0)" id=""
                    class="flex !px-4 !py-3 text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-gray-200 text-gray-400  rounded-lg ">
                    Back
                    <span class="material-symbols-outlined">
                        arrow_circle_left
                    </span>
                </a>
                <a href="javascript:void(0)" id="continue_campaign_step_02"
                    class="flex !px-4 !py-3 text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded-lg ">
                    Continue
                    <span class="material-symbols-outlined">
                        arrow_circle_right
                    </span>
                </a>

            </div>
            {{-- this is keyword data holder keywordsDataHolder --}}
            <div class="w-full">
                <input type="text" name="keywordsDataHolder" id="keywordsDataHolder" hidden>
            </div>
        </div>
        {{-- campaigns  Add Domains sections | step 03 --}}
        <div class="w-full !p-4 duration-500 transition-all campaigns-section hidden" id="select-domains">

            <div class="w-full border-b border-[var(--primary-color)] flex items-center gap-2">
                <div class="flex flex-wrap items-center gap-2 w-full">
                    <label
                        class="!p-3 bg-[var(--primary-color)] border border-[var(--primary-color)] duration-300 transition-all text-white border-b-0 text-sm text-center cursor-pointer domain-tab-btn hover:border-[var(--primary-color)] hover:bg-[var(--primary-color)] hover:text-white"
                        for="random-domains">
                        Random Domains (<span class="dy-domain-count" id="selectedHiddenDomainCount">0</span>)
                        <input type="radio" name="sel_domains" id="random-domains" class="domain-method-inp"
                            data-id="randomSelectSection" value="0" checked hidden>
                    </label>
                    <label
                        class="!p-3 bg-gray-50 border border-gray-300 border-b-0 text-sm text-center duration-300 transition-all cursor-pointer domain-tab-btn hover:border-[var(--primary-color)] hover:bg-[var(--primary-color)] hover:text-white"
                        for="domain-sets">
                        Domain Sets (Domains - <span class="dy-domain-count" id="selectedHiddenSetDomainCount">0</span>)
                        <input type="radio" name="sel_domains" id="domain-sets" class="domain-method-inp"
                            data-id="domainSetSection" value="1" hidden>
                    </label>
                    <label
                        class="!p-3 bg-gray-50 border border-gray-300 border-b-0 text-sm text-center duration-300 transition-all cursor-pointer domain-tab-btn hover:border-[var(--primary-color)] hover:bg-[var(--primary-color)] hover:text-white"
                        for="manual_domains_inp">
                        Manual Domains (<span class="dy-domain-count" id="manualDomainsCount">0</span>)
                        <input type="radio" name="sel_domains" id="manual_domains_inp" class="domain-method-inp"
                            data-id="manualDomainSection" value="2" hidden>
                    </label>
                </div>
            </div>

            <div class="w-full flex flex-col !mt-2  domains-sections" id="randomSelectSection">
                {{-- filters comment for time being --}}
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
                {{-- *** end here *** --}}
                <div class="w-full flex flex-col gap-3 max-h-[600px] overflow-x-auto overflow-y-auto">
                    {{-- filters here --}}
                    <table id="RandomDomainsTable" class="display w-full min-w-[700px] border border-gray-200 border-collapse text-sm ">

                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th>
                                    <input type="checkbox" name="bulk_domain_select[]" id="selectAllHiddenDomains"
                                        class="scale-125 ">
                                </th>
                                @php
                                    $tHead = ['sno', 'url', 'DA', 'TF', 'DR', 'SS'];
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
                            {{-- @foreach ($sites as $index => $site)
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center "> <input
                                            type="checkbox" name="bulk_sidebar_domain_select[]"
                                            id="sel-domain-check-box-{{ $index + 1 }}" class="sidebar_domains"
                                            value="{{ $index + 1 }}" id="">
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $index + 1 }}
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        <label for="sel-domain-check-box-{{ $index + 1 }}"
                                            class="cursor-pointer w-full">
                                            {{ $site->name }}</label>
                                    </td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site->da }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site->tf }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site->dr }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                        {{ $site->ss }}</td>

                                </tr>
                            @endforeach --}}
                        </tbody>
                    </table>
                    <div id="domain-pagination" class="flex flex-wrap gap-2 mt-3 justify-center">
                    </div>
                    <div class="w-full flex justify-end !mt-2">
                        <div class="flex items-center gap-2">
                            <button type="button" id="autoSelectHiddenDomainsBtn"
                                class="cursor-pointer bg-indigo-600 text-white rounded !px-3 !py-2 text-sm"
                                style="background-color:#4f46e5 !important;color:#ffffff !important;">
                                Auto Select Required
                            </button>
                            <span id="autoSelectHiddenDomainsProgress" class="text-xs text-gray-700"></span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="w-full flex flex-col hidden domains-sections" id="domainSetSection">
            <div class="w-full flex flex-wrap gap-3 items-start sm:items-center !mt-6">
                    <div class="w-full sm:w-1/5 flex flex-col">
                        <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                            {{-- Hidden input for form submission --}}
                            <input data-hidden-input type="hidden" id="domain-set" name="domain-set"
                                value="{{ old('domain-set') }}">

                            {{-- Select Button --}}
                            <button data-select-btn type="button" id="selectDomainSet"
                                class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                                <span data-select-text id="selectDomainSet" class="text-gray-400">Select
                                    Users ...</span>
                                <svg data-chevron id="chevron"
                                    class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            {{-- Dropdown --}}
                            <div data-dropdown id="domainSetdropdown"
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
                                <div data-options-list id="domainSetOptionsList" class="max-h-64 overflow-y-auto">
                                    @if (isset($domainSets) && count($domainSets) > 0)
                                        @foreach ($domainSets as $set)
                                            <button data-option-item type="button"
                                                class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                                data-value="{{ $set->id }}" data-label="{{ $set->name }}">
                                                <span class="text-sm ">{{ $set->name . ' - (' . $set->qty . ')' }}</span>
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
                    <div class="w-full sm:w-auto">
                        <button type="button"
                            class="!p-3 text-sm cursor-pointer bg-black text-white rounded hover:bg-[var(--primary-color)] w-full flex gap-2 items-center"
                            id="fetch-domains-set">
                            <div
                                class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin loader hidden">
                            </div>
                            Select Domain Set
                        </button>
                    </div>
                </div>
                <div class="w-full flex flex-col max-h-[600px] overflow-x-auto overflow-y-auto !mt-6">
                    {{-- filters here --}}
                    <table id="domainSetTable" class="display w-full min-w-[700px] border border-gray-200 border-collapse text-sm ">

                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <th><input type="checkbox" name="bulk_set_domain_select[]" id="selectAllHiddenSetDomains"
                                        class="scale-125 "></th>
                                @php
                                    $tHead = ['sno', 'url', 'DA', 'TF', 'DR', 'SS'];
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
                            {{-- @foreach ($sites as $index => $site)
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center "> <input
                                            type="checkbox" name="bulk_domain_select[]"
                                            id="sel-set-domain-check-box-{{ $index + 1 }}" class="siderBarSetDomains"
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
                            <button type="button" id="autoSelectHiddenSetDomainsBtn"
                                class="cursor-pointer bg-indigo-600 text-white rounded !px-3 !py-2 text-sm"
                                style="background-color:#4f46e5 !important;color:#ffffff !important;">
                                Auto Select Required
                            </button>
                            <span id="autoSelectHiddenSetDomainsProgress" class="text-xs text-gray-700"></span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="w-full flex flex-col gap-3 !mt-3 domains-sections hidden" id="manualDomainSection">
                <label for=""
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5 ">Domain
                    Urls</label>
                <textarea name="" id="manual-domains-area"
                    class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none rounded" rows="20"
                    placeholder="Enter Media Link Here"></textarea>
            </div>

            <div class="w-full flex justify-between gap-3 !mt-4">
                <a href="javascript:void(0)" id=""
                    class="flex !px-4 !py-3 text-lg font-normal items-center gap-1 justify-center duration:500 transition-all bg-gray-200 text-gray-400  rounded-lg ">
                    Back
                    <span class="material-symbols-outlined">
                        arrow_circle_left
                    </span>
                </a>
                <button type="submit" id="continue_campaign_step_03"
                    class="flex !px-4 !py-3 text-lg font-normal bg-green-500 items-center gap-1 justify-center duration:500 transition-all bg-black text-white rounded-lg cursor-pointer">

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

        {{-- storing the select domains in this input field --}}
        <input type="hidden" name="campaigns_domains" id="campaigns_domains_holder">

    </form>



@endsection

@section('popup')


    {{-- this is popup for keywords & url --}}

    {{-- this is first pop layer | it is keywords data pop |  | |  --}}
    <div
        class="w-screen h-screen flex justify-center items-start fixed top-0 left-0 z-1100 overflow-hidden hidden dy-key-parent-div">
        <div
            class="dy-key-set-overlay w-screen h-screen bg-black/40 absolute top-0 left-0 duration-300 opacity-0  transition-all">
        </div>
        <div class="dy-key-pop-box w-[96%] sm:w-[80%] max-w-[850px] max-h-[calc(100vh-24px)] sm:max-h-none flex flex-col items-center !shadow-2xl bg-gray-50 border border-gray-300 z-2 rounded !mt-3 sm:!mt-[70px] linear duration-600 opacity-0 -translate-y-[20%] transition-all"
            id="dy-key-article-box">
            {{-- pop top bar --}}
            <div class="w-full flex justify-between items-center !bg-gray-200 !p-3">
                <h6>URL Keywords</h6>
                <p>Post Quantity <span class="dy-post-count" id="PostQuantity">(0)</span></p>
                <button type="button" class="cursor-pointer article-set-close" id='dy-key-close-btn'>
                    <span class="material-symbols-outlined">
                        close
                    </span>
                </button>
            </div>
            <div class="w-full flex flex-col gap-3 !p-3 max-h-[560px] overflow-auto">

                <div class="w-full border-b border-[var(--primary-color)] flex items-center gap-2">
                    <label
                        class="!p-3 bg-[var(--primary-color)] border border-[var(--primary-color)] duration-300 transition-all text-white border-b-0 text-sm text-center cursor-pointer keyword-tab-btn"
                        id="add_keyword_url">
                        Add Keyword & Url
                        <input type="radio" name="keyword-data" id="add_keyword_url" class="keyword-method-inp"
                            value="normal" data-id="keyword-url-container" checked hidden>
                    </label>
                    <label
                        class="!p-3 bg-gray-50 border border-gray-300 border-b-0 text-sm text-center duration-300 transition-all cursor-pointer keyword-tab-btn"
                        for="add_bulk_keyword_url">
                        Add Bulk Keyword & Url
                        <input type="radio" name="keyword-data" id="add_bulk_keyword_url" class="keyword-method-inp"
                            value="bulk" data-id="bulk-keyword-url-container" hidden>
                    </label>
                </div>
                {{-- border-b border-b-[var(--primary-color)] --}}
                <div class="w-full flex flex-col max-h-[280px] overflow-hidden overflow-y-auto  keyword-tab-sec"
                    id="keyword-url-container">
                    {{-- keyword url box here --}}
                    <div class="flex w-full bg-orange-100 keyword-url-box static-box relative keyword-mobile-stack">
                        <div class="w-3/5 flex flex-col gap-2 !p-4 !pt-[45px] keyword-mobile-main">
                            <div class="w-full flex items-center">
                                <label for=""
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5 keyword-mobile-label">Client
                                    Url</label>
                                <div class="w-4/5 flex items-center justify-between keyword-mobile-input-wrap">
                                    <input type="text" placeholder="Enter Url"
                                        class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-[78%] client-url keyword-mobile-input-main">
                                    <input type="text" value="0"
                                        class="bg-gray-50 !p-2 text-sm outline-none text-center border border-gray-300 w-1/5 client-url-quantity num-inp keyword-mobile-qty">
                                </div>
                            </div>
                            {{-- <div class="w-full flex items-center">
                                <label for=""
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)] w-1/5 keyword-mobile-label ">Media
                                    Link</label>
                                <div class="w-4/5 flex items-center justify-between keyword-mobile-input-wrap">
                                    <textarea name="" id=""
                                        class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none media-link" rows="2"
                                        placeholder="Enter Media Link Here"></textarea>
                                </div>
                            </div> --}}
                            <div class="w-full flex items-center justify-end">
                                <button type="button"
                                    class="!p-1 bg-red-600 rounded text-sm cursor-pointer text-white remove-keyword-box">delete</button>
                            </div>
                        </div>
                        <div class="w-2/5 flex flex-col gap-1 !p-4 keyword-mobile-side">
                            <label for=""
                                class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Client
                                Keyword</label>
                            <div class="w-full flex flex-wrap justify-between keywords-area-parent">
                                <div class="w-[78%] flex flex-wrap keyword-mobile-input-main">
                                    <textarea name="" id="" rows="5"
                                        class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none keywords-area"></textarea>
                                </div>
                                <div class="w-1/5 flex flex-wrap keyword-mobile-qty">
                                    <textarea name="" id="" rows="5"
                                        class="bg-gray-50 !p-2 text-sm outline-none border border-gray-300 w-full resize-none text-center keywords-quantity-area focus:border-[var(--primary-color)] "></textarea>
                                </div>
                            </div>
                        </div>
                        <div
                            class="box-count flex !px-3 !py-1 text-sm font-semibold bg-[var(--primary-color)] text-white rounded absolute top-3 left-3">
                            01</div>
                    </div>
                </div>

                <div class="w-full flex flex-wrap justify-end !py-2 add-more-window keyword-tab-sec ">
                    <div class="w-full sm:w-1/2 flex flex-wrap sm:flex-nowrap gap-2 sm:gap-0 justify-between items-center text-sm keyword-progress-wrap">
                        <p>Total <span class="total-box-count">1</span></p>
                        <a href="javascript:void(0)" class="bg-blue-400 !p-2 text-sm rounded text-white"
                            id="add-more-keyword-URL">+Add More Url</a>
                    </div>

                </div>
                {{-- bulk url div here --}}
                <div class="w-full flex flex-wrap justify-between max-h-[320px] overflow-hidden overflow-y-auto bg-gray-100 keyword-tab-sec hidden"
                    id="bulk-keyword-url-container">
                    <div class="w-[49.5%] flex flex-col gap-2">
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
                    <div class="w-[49.5%] flex flex-col gap-2">
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

                </div>


            </div>
            <div class="w-full flex flex-col sm:flex-row gap-2 sm:gap-0 justify-between sm:items-center !bg-gray-200 !px-3 !py-2">
                <div class="flex flex-wrap gap-1 items-center">
                    <input type="checkbox" name="no_follow" id="no_follow" value="1">
                    <label for="no_follow" class="text-sm">No Follow</label>
                    <p class="text-[12px]">(Check here to get Nofollow Link)</p>

                </div>
                <a href="#" type="button" id="add-keywords-links"
                    class="cursor-pointer bg-green-500 text-white rounded !p-3">
                    Save change
                </a>
            </div>
        </div>


        <div
            class="w-screen h-screen flex justify-center items-center fixed top-0 left-0 z-1200 overflow-hidden bg-white/40 domain-loader-pop hidden opacity-0 transition-all duration-500">

            <div class="w-16 h-16 border-4 border-[var(--primary-color)] border-t-gray-100 rounded-full animate-spin ">
            </div>




        </div>

    @endsection


    @push('scripts')
        <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
        <script type="module" src="{{ asset('js/create-hidden-links-campaign.js') }}"></script>


        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
            integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>

    @endpush
