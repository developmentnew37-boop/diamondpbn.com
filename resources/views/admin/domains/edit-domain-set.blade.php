@extends('admin.layout.layout')

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
@endpush

@section('title', 'Create Domain Sets')


@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Add Domains </h2>
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
                        <a href="#" class="breadcrumb-link">Edit {{ $set->name }}</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                {{-- <a href="javascript:void(0)" id="make_article_Set"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">+
                    Add Article Set
                </a> --}}
            </div>
        </div>
    </div>

    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}


    <form action="{{ route('admin.set.update', $set->id) }}" method="post">
        @csrf
        @method('PUT')


        <div class="w-full flex flex-wrap gap-8 justify-center  ">
            <div class="w-full flex flex-wrap gap-4 justify-center ">
                <div class=" w-full mx-auto content-card ">
                    {{-- alerts --}}
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
                    <div class="px-6 pt-6 flex flex-col gap-3 justify-between">
                        {{-- heading here --}}

                        {{-- selecting domains types --}}

                        <div
                            class="text-xl bg-[var(--primary-color)] flex justify-center !py-5 rounded font-semibold w-full">
                            <p class="text-sm bg-white font-normal !px-6 !py-3 rounded w-fit">
                                {{ $set->DomainCategory->name }}
                            </p>
                            {{--  domain Holder --}}
                            <input type="hidden" name="set" id="set" value="{{ $set->id }}">
                            {{--  domain Holder --}}
                            <input type="hidden" name="domains" id="domains-holder">
                            {{--  domain add method type --}}
                            <input type="hidden" name="method" id="method-type" value="0">
                        </div>

                        {{--  domains Upload Buttons --}}



                        <div class="w-full flex flex-wrap items-center !mt-2">
                            <div class="w-1/2 flex flex-wrap items-center gap-2">
                                <button type="button"
                                    class="bg-black !px-4 !py-3 rounded cursor-pointer text-sm text-white domain-add-opts duration-300 transition-all"
                                    data-id="select-method">Select Domains</button>
                                <button type="button"
                                    class="bg-gray-200 !px-4 !py-3 rounded cursor-pointer text-sm domain-add-opts duration-300 transition-all"
                                    data-id="manual-add">Manual Add</button>
                            </div>
                            <div class="w-1/2 flex flex-wrap justify-end ">
                                <p class="bg-gray-200 !p-2 text-sm rounded" id="selectSetDomainsCount">Selected: <span
                                        id="showingSelectCount">{{ $set->qty }}</span></p>
                            </div>

                        </div>



                    </div>




                    {{-- table + select domain + create set button code here --}}

                    <div class="flex flex-col gap-2 w-full duration-400 transition-all !mt-3 add-domains"
                        id="select-method">
                        <div class="overflow-x-auto !mt-3 w-full">
                            <table id="myTable"
                                class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">

                                {{-- percentage based column widths --}}
                                <colgroup>
                                    <col style="width: 5%;"> {{-- checkbox --}}
                                    <col style="width: 7%;"> {{-- SNo --}}
                                    <col style="width: 28%;"> {{-- URL --}}
                                    <col style="width: 10%;"> {{-- DA --}}
                                    <col style="width: 10%;"> {{-- TF --}}
                                    <col style="width: 10%;"> {{-- DR --}}
                                    <col style="width: 10%;"> {{-- SS --}}
                                    {{-- remaining % auto distributes --}}
                                </colgroup>

                                <thead>
                                    <tr class="bg-gray-800 text-white">
                                        <th>
                                            <input type="checkbox" name="bulk_category_selector" class="scale-125 "
                                                id="bulkSelectDomains">
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
                                    @foreach ($domains as $index => $domain)
                                        @php
                                            $decodedDomains = json_decode($set->domains, true);
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center ">

                                                <input type="checkbox" class="scale-125 cursor-pointer sel-domains"
                                                    name="bulk_category_select[]" value="{{ $domain['id'] }}"
                                                    id=""
                                                    {{ in_array($domain['id'], $decodedDomains) ? 'checked' : '' }}>
                                            </td>
                                            </td>
                                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                                {{ $domain['name'] }}</td>
                                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                                {{ $domain['da'] }}</td>
                                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                                {{ $domain['tf'] }}</td>
                                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                                {{ $domain['dr'] }}</td>
                                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                                {{ $domain['ss'] }}</td>

                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>



                    <div class="w-full flex flex-col gap-1 !mt-4 duration-400 transition-all translate-y-[20px] opacity-0 hidden add-domains"
                        id="manual-add">
                        <div class="flex flex-col gap-2 bg-gray-50 border-gray-300 border rounded !p-4">
                            <label for="" class="text-sm flex items-center "><span
                                    class="after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Domains</span></label>
                            {{-- manual_domains | selector for further process --}}
                            <textarea name="manual_domains" id="manualDomains"
                                class="w-full bg-gray-100 rounded border border-gray-300 !p-2 text-sm" rows="20"></textarea>
                            <p>Selected: <span id="manualselectedCount">0</span></p>
                        </div>
                    </div>

                    <div class="w-full flex items-center !mt-2">
                        <input type="submit" value="update Set"
                            class="bg-[var(--primary-color)] text-white !px-5 !py-3 rounded cursor-pointer">
                    </div>


                </div>

                {{-- </div> --}}
            </div>

        </div>
    </form>
    {{-- </div> --}}


@endsection



@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
        integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    {{-- <script src="{{ asset('js/general.js') }}"></script> --}}
    <script type="module" src="{{ asset('js/domain-set/create-set-script.js') }}"></script>
@endpush
