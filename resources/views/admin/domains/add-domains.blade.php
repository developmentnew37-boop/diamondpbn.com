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
    </style>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.3.4/css/dataTables.dataTables.min.css">
@endpush

@section('title', 'Add Domains')


@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
            <div class="w-full sm:w-1/2 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Add Domains </h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="" class="breadcrumb-link">Add</a>
                    </div>
                </div>
            </div>
            <div class="w-full sm:w-1/2 flex flex-wrap justify-start sm:justify-end items-center">
                {{-- <a href="javascript:void(0)" id="make_article_Set"
                    class="flex !p-2 !py-3 text-sm font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">+
                    Add Article Set
                </a> --}}
            </div>
        </div>
    </div>

    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}



    <div class="w-full flex flex-col gap-4 items-center">
        <div class="w-full max-w-[900px] content-card min-w-0">
                {{-- <div class="content-card"> --}}
                <div class="px-4 sm:px-6 pt-6 flex flex-col gap-3 justify-between min-w-0">

                    {{-- alerts here --}}
                    <div class="w-full flex flex-col gap-1">
                        @if (session()->has('cus__success'))
                            <div class="!p-4  text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                                <span class="font-medium"> {{ session('cus__success') }}</span>

                            </div>
                        @endif
                        @if (session()->has('cus__error'))
                            <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                                <span class="font-medium"> {{ session('cus__error') }}</span>

                            </div>
                        @endif
                        @error('domain_category_id')
                            <div class="w-full !p-4 text-red-600 bg-red-100 text-sm rounded">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    {{-- heading here --}}

                    {{-- selecting domains types --}}
                    <div
                        class="text-xl bg-[var(--primary-color)] text-center !py-5 rounded font-semibold capitalize w-full max-w-full min-w-0 !px-3 sm:!px-5">
                        <select name="" id="domain_category"
                            class="text-sm bg-white font-normal !p-3 rounded outline-none border border-gray-200 focus:border-2 focus:border-black w-full sm:w-auto max-w-full">
                            <option value="">select domain Type</option>
                            @if ($domainCategories && count($domainCategories) > 0)
                                @foreach ($domainCategories as $domain)
                                    <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    {{--  ** domains Upload Buttons ** --}}

                    <div class="w-full flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 !mt-4">

                        <button
                            class="bg-black !px-4 !py-3 rounded cursor-pointer text-sm text-white domain-add-opts duration-300 transition-all w-full sm:w-auto"
                            data-id="drop-file-method">Upload
                            Domains
                            File</button>
                        <button
                            class="bg-gray-200 !px-4 !py-3 rounded cursor-pointer text-sm domain-add-opts duration-300 transition-all w-full sm:w-auto"
                            data-id="manual-add-method">Add
                            Domain</button>

                    </div>

                    {{-- adding data by uploading excel file --}}
                    <div class="w-full !p-2 add-domains duration-600 transition-all" id="drop-file-method">
                        {{-- <h2 class="text-3xl font-bold text-gray-900 !mb-3">Add Domains</h2> --}}

                        <!-- Drop Zone -->
                        <div id="dropZone"
                            class="border-2 border-dashed border-gray-300 rounded-lg !p-12 flex flex-col gap-4 justify-center items-center hover:border-orange-500 transition-colors cursor-pointer bg-white">
                            <svg class="h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                <path
                                    d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02"
                                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <p class="mt-4 text-sm text-gray-600">
                                <span class="font-semibold text-orange-600">Click to upload</span> or drag and drop
                            </p>
                            <p class="text-xs text-gray-500 mt-2">Excel files (.xlsx, .xls)</p>
                            <input type="file" id="fileInput" class="hidden" accept=".xlsx,.xls">
                        </div>

                        <!-- File Info -->
                        <div id="fileInfo" class="hidden !mt-4 !p-4 bg-orange-50 border border-orange-200 rounded-lg">
                            <p class="text-sm text-orange-800">
                                <span class="font-semibold">File:</span> <span id="fileName"></span>
                            </p>
                        </div>

                        <!-- Column Selector -->
                        <div id="columnSelector"
                            class="hidden !mt-6 bg-white !p-6 rounded-lg shadow-sm border border-gray-200">
                            <label class="block text-sm font-medium text-gray-700 !mb-2">
                                Select Columns to Extract
                            </label>

                            <div class="relative">
                                <button id="dropdownButton" type="button"
                                    class="w-full bg-white border border-gray-300 rounded-lg !px-4 !py-3 text-left flex items-center justify-between hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                    <span id="selectedColumnsText" class="text-gray-700">Select columns...</span>
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>

                                <div id="dropdownMenu"
                                    class="hidden absolute z-10 !mt-2 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-80 overflow-auto">
                                    <div class="!p-2">
                                        <button id="selectAllBtn"
                                            class="w-full text-left !px-3 !py-2 text-sm text-orange-600 hover:bg-orange-50 rounded">
                                            Select All
                                        </button>
                                        <button id="clearAllBtn"
                                            class="w-full text-left !px-3 !py-2 text-sm text-red-600 hover:bg-red-50 rounded">
                                            Clear All
                                        </button>
                                    </div>
                                    <div class="border-t border-gray-200"></div>
                                    <div id="columnCheckboxes" class="p-2">
                                        <!-- Checkboxes will be populated here -->
                                    </div>
                                </div>
                            </div>

                            <!-- Selected Columns Display -->
                            <div id="selectedColumnsList" class="!mt-3 flex flex-wrap gap-2"></div>

                            <p class="!mt-2 text-sm text-gray-500">Select one or more columns to extract from your Excel
                                file</p>
                        </div>

                        <!-- Data Preview -->
                        <div id="dataPreview"
                            class="hidden !mt-6 bg-white !p-6 rounded-lg shadow-sm border border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900 !mb-4">Data Preview</h3>
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead id="tableHeader" class="bg-gray-50"></thead>
                                    <tbody id="tableBody" class="bg-white divide-y divide-gray-200"></tbody>
                                </table>
                            </div>
                            <p class="mt-4 text-sm text-gray-500">Showing first 5 rows</p>
                        </div>

                        <!-- Action Buttons -->
                        <div id="actionButtons" class="hidden !mt-6 flex gap-4">
                            <button id="processButton"
                                class="!px-6 !py-3 bg-gray-700 text-white rounded-lg hover:bg-gray-900 transition-colors font-medium">
                                Extract Data
                            </button>
                            <button id="cancelButton"
                                class="!px-6 !py-3 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                                Cancel
                            </button>
                            <button id="uploadDomains" type="button"
                                class="flex gap-2 items-center !px-6 !py-3 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors font-medium">
                                <div
                                    class="w-3 h-3 border-2 border-gray-100 border-t-transparent rounded-full animate-spin loader hidden">
                                </div>
                                Upload Domains
                            </button>
                        </div>

                        <!-- Results -->
                        <div id="results" class="hidden !mt-6 !p-6 bg-green-50 border border-green-200 rounded-lg">
                            <h3 class="text-lg font-semibold text-green-800 mb-3">Success!</h3>
                            <p class="text-sm text-green-700 mb-3">
                                <span id="recordCount"></span> records extracted with <span id="columnCount"></span>
                                columns
                            </p>
                            <details class="mt-4">
                                <summary class="cursor-pointer text-sm font-medium text-green-800 hover:text-green-900">
                                    View Extracted Data (JSON)
                                </summary>
                                <pre id="extractedData" class="!mt-3 !p-4 bg-white border border-green-200 rounded text-xs overflow-x-auto max-h-96"></pre>
                            </details>
                        </div>
                    </div>


                    {{-- adding single domains things starts here --}}

                    <div class="w-full items-center duration-600 transition-all add-domains translate-y-[20px] opacity-0 hidden"
                        id="manual-add-method">

                        <form action="{{ route('admin.domain.store') }}" class="w-full flex flex-col gap-5 min-w-0"
                            method="post">
                            @csrf
                            <input type="hidden" name="domain_category_id" id="hidden_category_id">

                            <div class="w-full flex flex-col gap-2 justify-between">
                                <label for="domain_name"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">name
                                </label>
                                <input type="text" name="name" id="domain_name" placeholder="Enter Domain url"
                                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                @error('name')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="w-full grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="w-full flex flex-col gap-2 min-w-0">
                                    <label for="domain_authority"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">DA(domain
                                        Authority)
                                    </label>
                                    <input type="text" name="da" id="domain_authority" placeholder="Enter DA"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('da')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-full flex flex-col gap-2 min-w-0">
                                    <label for="domain_rating"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">DR(domain
                                        rating)
                                    </label>
                                    <input type="text" name="dr" id="domain_rating" placeholder="Enter DR"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('dr')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="w-full grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="w-full flex flex-col gap-2 min-w-0">
                                    <label for="domain_trust_flow"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">TF(Trust
                                        Flow)
                                    </label>
                                    <input type="text" name="tf" id="domain_trust_flow" placeholder="Enter TF"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('tf')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-full flex flex-col gap-2 min-w-0">
                                    <label for="domain_spam_score"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">SS(spam
                                        score)
                                    </label>
                                    <input type="text" name="ss" id="domain_spam_score" placeholder="Enter SS"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('ss')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="w-full grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="w-full flex flex-col gap-2 min-w-0">
                                    <label for="ip_address" class="text-sm flex items-center ">IP Address
                                    </label>
                                    <input type="text" name="ip" id="ip_address" placeholder="Enter Ip Address"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('ip')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-full flex flex-col gap-2 min-w-0">
                                    <label for="api_key"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Api
                                        Key
                                    </label>
                                    <input type="password" name="api_key" id="api_key" placeholder="Enter api key"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('api_key')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="w-full">
                                <input type="submit" value="Add domain"
                                    class="!p-3 text-[16px] cursor-pointer bg-black text-white rounded hover:bg-[var(--primary-color)] w-full sm:w-fit">
                            </div>
                        </form>
                    </div>

                </div>




                {{-- table code here --}}

                {{-- <div class="overflow-x-auto !mt-3 w-full">
                        <table id="myTable"
                            class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">
                            <thead>
                                <tr class="bg-gray-800 text-white">
                                    <th><input type="checkbox" name="bulk_category_select[]" class="scale-125 "></th>
                                    @php
                                        $tHead = ['sno', 'url', 'DA', 'TF', 'DR', 'SS', 'Action'];
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
                                @foreach ($sites as $index => $site)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center "> <input
                                                type="checkbox" name="bulk_category_select[]" value="{{ $index + 1 }}"
                                                id="">
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $index + 1 }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            {{ $site['url'] }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            {{ $site['DA'] }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            {{ $site['TF'] }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            {{ $site['DR'] }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            {{ $site['SS'] }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            <div class="flex flex-wrap gap-2 justify-center">

                                                <a href=""
                                                    class="bg-green-600 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-green-800 relative overflow ">
                                                    <span class="material-symbols-outlined !text-sm  text-white">
                                                        add
                                                    </span>
                                                </a>

                                                <a href="javascript:void(0)"
                                                    class="edit-set-btn bg-yellow-500 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-yellow-600">
                                                    <span class="material-symbols-outlined !text-sm text-white">
                                                        edit_square
                                                    </span>
                                                </a>
                                                <a href="javascript:void(0)"
                                                    class="bg-red-600  flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-red-700">
                                                    <span class="material-symbols-outlined !text-sm text-white">
                                                        delete
                                                    </span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div> --}}



            </div>


@endsection



@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.17.0/xlsx.full.min.js"></script>
   
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script type="module" src="{{ asset('js/general.js') }}"></script>
    <script src="{{ asset('js/extract-excel-data.js') }}"></script>


@endpush
