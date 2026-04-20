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

@section('title', $set->name . 'Details')


@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="w-full md:flex-1 flex flex-col gap-2 min-w-0">
                <h2 class="page-title">Domains Set</h2>
                <div class="breadcrumb flex-wrap">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.set.index') }}" class="breadcrumb-link">Domain Set</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link break-words">{{ $set->name }}</a>
                    </div>
                </div>
            </div>
            <div class="w-full md:w-auto flex flex-wrap justify-start md:justify-end items-center">
                <a href="javascript:void(0)" id="make_domain_set"
                    class="flex !px-4 !py-3 text-[16px] font-normal justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded whitespace-nowrap w-full md:w-auto">
                    Create Domain Set
                </a>
            </div>
        </div>
    </div>

    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}

    <div class="w-full flex flex-col gap-2 justify-center  ">
        <div class="w-full flex flex-col mx-auto content-card ">
            {{-- <div class="content-card"> --}}
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

            <div class="px-4 sm:px-6 pt-6 flex flex-col gap-3 justify-between">
                {{-- heading here --}}

                {{-- heading here --}}


                <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between !mt-6">
                    <div class="w-full sm:w-auto flex flex-wrap gap-3 min-w-0">
                        <h2
                            class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold  capitalize w-fit">
                            {{ $set->name }} Here <span class="material-symbols-outlined !text-sm">
                                arrow_cool_down
                            </span>
                        </h2>
                    </div>
                    <div class="w-full sm:w-auto flex flex-wrap gap-3 justify-start sm:justify-end">

                        {{-- Search Box --}}

                        {{-- <div class="relative flex items-center">
                            <svg class="absolute !left-3 !top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"
                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input data-search-input type="text" id="searchInput"
                                class="w-full !pl-10 !pr-4 !py-2 bg-gray-100 min-h-12 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)]   text-sm"
                                placeholder="Search...">
                        </div> --}}

                    </div>
                </div>

                <div class="w-full !mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <div class="w-full flex flex-col gap-2 min-w-0">
                        <label for="domain_authority"
                            class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Domain Set
                        </label>
                        <div class="flex min-h-12 bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded break-words min-w-0">
                            {{ $set->name }}
                        </div>
                        
                    </div>
                    <div class="w-full flex flex-col gap-2 min-w-0">
                        <label for="domain_rating"
                            class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Domain
                            Qty
                        </label>
                        <div class="flex min-h-12 bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded">
                            {{ $set->qty }}
                        </div>


                    </div>
                    <div class="w-full flex flex-col gap-2 min-w-0">
                        <label for="domain_rating"
                            class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Domain
                            Category
                        </label>
                        <div class="flex min-h-12 bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded break-words min-w-0">
                            {{ $set->DomainCategory->name }}
                        </div>
                      
                    </div>
                </div>


                {{-- table code here --}}

                <div class="overflow-x-auto !mt-3 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                    <table
                        class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                        <colgroup>
                            <col style="width:5%;"> {{-- sno --}}
                            <col style="width:17%"> {{-- url --}}
                            <col style="width:9%"> {{-- DA --}}
                            <col style="width:9%"> {{-- TF --}}
                            <col style="width:9%"> {{-- DR --}}
                            <col style="width:9%"> {{-- SS --}}
                            <col style="width:10%"> {{-- IP --}}
                            <col style="width:20%"> {{-- Api Key --}}
                            <col style="width:12%"> {{-- Status --}}
                        </colgroup>


                        <thead>
                            <tr class="bg-gray-800 text-white">

                                @php
                                    $tHead = ['sno', 'url', 'DA', 'TF', 'DR', 'SS', 'Ip', 'Api Key', 'status'];
                                @endphp
                                @foreach ($tHead as $ind => $t)
                                    <th
                                        class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize {{ $ind == 0 ? 'text-center' : 'text-left' }}">
                                        {{ $t }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($domains as $index => $domain)
                                <tr class="hover:bg-gray-50">

                                    <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center">
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


@endsection





