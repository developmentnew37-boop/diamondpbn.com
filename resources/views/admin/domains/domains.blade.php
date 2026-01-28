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

@section('title', 'domains')

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Domains</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Domains</a>
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



    <div class="w-full flex flex-wrap gap-8 justify-center  ">
        <div class="w-full flex flex-wrap gap-4 justify-center ">
            <div class=" w-full mx-auto content-card ">
                {{-- <div class="content-card"> --}}
                <div class="px-6 pt-6 flex flex-col gap-3 justify-between">
                    {{-- heading here --}}

                    {{-- heading here --}}
                    <h2 class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold  capitalize w-fit">
                        Domains List Here <span class="material-symbols-outlined !text-sm">
                            arrow_cool_down
                        </span>
                    </h2>

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
                                <form action="{{ route('admin.domain.delete') }}"
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

                    <div class="overflow-x-auto !mt-3 w-full">
                        <table
                            class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                            <thead>
                                <tr class="bg-gray-800 text-white">
                                    <th><input type="checkbox" name="" id="bulk-checkBox-selector"
                                            class="scale-125 "></th>
                                    @php
                                        $tHead = [
                                            'sno',
                                            'url',
                                            'DA',
                                            'TF',
                                            'DR',
                                            'SS',
                                            'Ip',
                                            'Api Key',
                                            'status',
                                            'Action',
                                        ];
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
                                @forelse ($domains as $index => $domain)
                                    <tr class="hover:bg-gray-50">
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center">
                                            <input type="checkbox" name="" class="multi-check"
                                                value="{{ $domain->id }}">
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            {{ $index + 1 + $offset }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px] title">
                                            {{ $domain->name }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $domain->da }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $domain->tf }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $domain->dr }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $domain->ss }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ $domain->ip }}
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            {{ $domain->api_key }}</td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center">
                                            <div class="flex flex-col items-center">
                                                @if ($domain->status == '1')
                                                    <span
                                                        class="flex !px-3 !py-2 bg-green-100 text-green-600 rounded text-sm w-fit">connected</span>
                                                @else
                                                    <span
                                                        class="flex !px-3 !py-2 bg-red-100 text-red-600 rounded text-sm w-fit">disconnected</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                            <div class="flex flex-wrap gap-2 justify-center">
                                                <a href="{{ route('admin.domain.edit', $domain->id) }}"
                                                    class="edit-set-btn bg-yellow-500 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-yellow-600">
                                                    <span
                                                        class="material-symbols-outlined !text-sm text-white">edit_square</span>
                                                </a>

                                                <form action="{{ route('admin.domain.destroy', $domain->id) }}"
                                                    method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')

                                                    <button type="submit"
                                                        onclick="return confirm('Are you sure you want to delete this domain?')"
                                                        class="bg-red-600 flex items-center justify-center rounded w-7 h-7 duration-500 hover:bg-red-700 cursor-pointer">
                                                        <span
                                                            class="material-symbols-outlined !text-sm text-white">delete</span>
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>

                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center !py-4 text-gray-500 bg-gray-100 font-sans">
                                            No domains found...
                                        </td>
                                    </tr>
                                @endforelse

                            </tbody>
                        </table>
                    </div>

                    <div class="w-full">
                        {{ $domains->links() }}
                    </div>

                </div>

                {{-- </div> --}}
            </div>

        </div>

    </div>


@endsection



@push('scripts')
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>
    <script type="module" src="{{ asset('js/bulk-select/script.js') }}"></script>

    {{-- making search logic --}}
@endpush
