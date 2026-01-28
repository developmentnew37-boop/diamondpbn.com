@extends('admin.layout.layout')


@section('title', 'Edit ' . $domain->name)



@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Edit Domains </h2>
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
                        <a href="#" class="breadcrumb-link">Edit</a>
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
            <div class=" w-full mx-auto content-card !p-8">
                {{-- <div class="content-card"> --}}
                <div class="px-6 pt-6 flex flex-col gap-3 justify-between">
                    {{-- heading here --}}

                    <h2 class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold  capitalize w-fit">
                        Edit {{ $domain->name }} <span class="material-symbols-outlined !text-sm">
                            arrow_cool_down
                        </span>
                    </h2>

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

                    </div>


                    <div class="w-full items-center duration-600 transition-all">

                        <form action="{{ route('admin.domain.update', $domain->id) }}" class="w-full flex flex-col gap-5"
                            method="post">
                            @csrf
                            @method('PUT')


                            <div class="w-full flex flex-col gap-2 justify-between">
                                <label for="domain_name"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">name
                                </label>
                                <input type="text" name="name" id="domain_name" placeholder="Enter Domain url"
                                    value="{{ $domain->name }}"
                                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                @error('name')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="w-full flex flex-col gap-2 justify-between">
                                <label for="domain_category_id"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Domain
                                    category
                                </label>
                                <select name="domain_category_id" id=""
                                    class="bg-gray-100 border border-gray-200 !w-full !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    <option value="">select domain category</option>
                                    @if ($domainCategories && count($domainCategories) > 0)
                                        @foreach ($domainCategories as $domn)
                                            <option value="{{ $domn->id }}"
                                                {{ $domn->id == $domain->domain_category_id ? 'selected' : '' }}>
                                                {{ $domn->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('domain_category_id')
                                    <p class="text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex flex-wrap justify-between w-full">
                                <div class="w-[49.5%] flex flex-col gap-2">
                                    <label for="domain_authority"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">DA(domain
                                        Authority)
                                    </label>
                                    <input type="text" name="da" id="domain_authority" placeholder="Enter DA"
                                        value="{{ $domain->da }}"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('da')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-[49.5%] flex flex-col gap-2">
                                    <label for="domain_rating"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">DR(domain
                                        rating)
                                    </label>
                                    <input type="text" name="dr" id="domain_rating" placeholder="Enter DR"
                                        value="{{ $domain->dr }}"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('dr')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="flex flex-wrap justify-between w-full">
                                <div class="w-[49.5%] flex flex-col gap-2">
                                    <label for="domain_trust_flow"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">TF(Trust
                                        Flow)
                                    </label>
                                    <input type="text" name="tf" id="domain_trust_flow" placeholder="Enter TF"
                                        value={{ $domain->tf }}
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('tf')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-[49.5%] flex flex-col gap-2">
                                    <label for="domain_spam_score"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">SS(spam
                                        score)
                                    </label>
                                    <input type="text" name="ss" id="domain_spam_score" placeholder="Enter SS"
                                        value="{{ $domain->ss }}"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('ss')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="flex flex-wrap justify-between w-full">
                                <div class="w-[49.5%] flex flex-col gap-2">
                                    <label for="ip_address" class="text-sm flex items-center ">IP Address
                                    </label>
                                    <input type="text" name="ip" id="ip_address" placeholder="Enter Ip Address"
                                        value="{{ $domain->ip }}"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('ip')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="w-[49.5%] flex flex-col gap-2">
                                    <label for="api_key"
                                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">Api
                                        Key
                                    </label>
                                    <input type="password" name="api_key" id="api_key" placeholder="Enter api key"
                                        value="{{ $domain->api_key }}"
                                        class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                                    @error('api_key')
                                        <p class="text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                            <div class="w-full">
                                <input type="submit" value="update domain"
                                    class="!p-3 text-[16px] cursor-pointer bg-black text-white rounded hover:bg-[var(--primary-color)] w-fit">
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

            {{-- </div> --}}
        </div>

    </div>

    </div>


@endsection



@push('scripts')

    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>


    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
            integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
        <script>
            $(document).ready(function() {
                $('#myTable').DataTable({
                    pageLength: 50,
                    lengthMenu: [
                        [10, 50, 100, 250, 500, -1], // -1 means “All”
                        [10, 50, 100, 250, 500, "All"] // labels shown in dropdown
                    ],
                    responsive: true,
                    order: [
                        [1, 'asc']
                    ], // default sort by sno
                    columnDefs: [{
                            orderable: false,
                            targets: [0, 7]
                        }, // disable sort for checkbox + action
                        {
                            className: "text-center align-middle",
                            targets: [0, 1, 3, 4, 5, 6, 7]
                        } // center align checkbox + numeric cols
                    ],
                    language: {
                        search: "Search:",
                        lengthMenu: "Show _MENU_ entries",
                    }
                });
            });
        </script>
    @endpush

@endpush
