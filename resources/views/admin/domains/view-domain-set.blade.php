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

@section('title', 'view .us domains')

@php
    $domains = [
        ['id' => 1, 'name' => '.us domains'],
        ['id' => 2, 'name' => '.id domains'],
        ['id' => 3, 'name' => '.my.id domains'],
        ['id' => 4, 'name' => '.uk domains'],
        ['id' => 5, 'name' => '.xyz domains'],
        ['id' => 6, 'name' => '.cyou domains'],
        ['id' => 7, 'name' => '.biz.id domains'],
        ['id' => 8, 'name' => '.kr domains'],
    ];
    $languages = [
        ['id' => 6, 'name' => 'English'],
        ['id' => 2, 'name' => 'Thai'],
        ['id' => 3, 'name' => 'Indonesian'],
        ['id' => 4, 'name' => 'Korean'],
        ['id' => 5, 'name' => 'Russian'],
        ['id' => 1, 'name' => 'other'],
    ];
    $sites = [
        ['id' => 1, 'url' => 'thevirtualassistantservice.com', 'DA' => 52, 'TF' => 3, 'DR' => 8, 'SS' => 6],
        ['id' => 2, 'url' => '51entertainmentgroup.com', 'DA' => 52, 'TF' => 0, 'DR' => 21, 'SS' => 8],
        ['id' => 3, 'url' => 'baika8.com', 'DA' => 51, 'TF' => 4, 'DR' => 5, 'SS' => 2],
        ['id' => 4, 'url' => 'infiniteoursandtravels.com', 'DA' => 52, 'TF' => 16, 'DR' => 18, 'SS' => 0],
        ['id' => 5, 'url' => 'lederglitz.com', 'DA' => 55, 'TF' => 20, 'DR' => 20, 'SS' => 2],
        ['id' => 6, 'url' => 'taysidethistleproperties.com', 'DA' => 52, 'TF' => 16, 'DR' => 12, 'SS' => 7],
        ['id' => 7, 'url' => 'ze-fille.com', 'DA' => 53, 'TF' => 5, 'DR' => 18, 'SS' => 3],
        ['id' => 8, 'url' => 'burstforum.com', 'DA' => 53, 'TF' => 16, 'DR' => 13, 'SS' => 2],
        ['id' => 9, 'url' => 'eqt-srpc.com', 'DA' => 53, 'TF' => 11, 'DR' => 12, 'SS' => 4],
        ['id' => 10, 'url' => 'green-tech-africa.com', 'DA' => 52, 'TF' => 10, 'DR' => 17, 'SS' => 6],
        ['id' => 11, 'url' => 'newlogicentertainment.com', 'DA' => 53, 'TF' => 10, 'DR' => 16, 'SS' => 8],
        ['id' => 12, 'url' => 'zumzumbikes.com', 'DA' => 53, 'TF' => 13, 'DR' => 19, 'SS' => 2],
        ['id' => 13, 'url' => 'radio-chalette.com', 'DA' => 54, 'TF' => 10, 'DR' => 20, 'SS' => 3],
        ['id' => 14, 'url' => 'samipress.net', 'DA' => 53, 'TF' => 10, 'DR' => 17, 'SS' => 2],
        ['id' => 15, 'url' => 'vistaaudiochanger.com', 'DA' => 53, 'TF' => 10, 'DR' => 14, 'SS' => 2],
        ['id' => 16, 'url' => 'alduniatours.com', 'DA' => 54, 'TF' => 13, 'DR' => 13, 'SS' => 0],
        ['id' => 17, 'url' => 'lokerjobsid123.com', 'DA' => 55, 'TF' => 10, 'DR' => 13, 'SS' => 7],
        ['id' => 18, 'url' => 'negociosabiertosspr.com', 'DA' => 54, 'TF' => 12, 'DR' => 12, 'SS' => 4],
        ['id' => 19, 'url' => 'simonandstingtour.com', 'DA' => 55, 'TF' => 8, 'DR' => 26, 'SS' => 0],
        ['id' => 20, 'url' => 'abitronixdirect.com', 'DA' => 56, 'TF' => 6, 'DR' => 1, 'SS' => 0],
        ['id' => 21, 'url' => 'bihada-beautiful.com', 'DA' => 56, 'TF' => 2, 'DR' => 15, 'SS' => 3],
        ['id' => 22, 'url' => 'sideshowssito.com', 'DA' => 58, 'TF' => 11, 'DR' => 24, 'SS' => 2],
        ['id' => 23, 'url' => 'snugsurveys.com', 'DA' => 57, 'TF' => 14, 'DR' => 5, 'SS' => 3],
        ['id' => 24, 'url' => 'stvincet-movie.com', 'DA' => 57, 'TF' => 12, 'DR' => 15, 'SS' => 0],
        ['id' => 25, 'url' => 'clyckmail.com', 'DA' => 58, 'TF' => 15, 'DR' => 15, 'SS' => 2],
        ['id' => 26, 'url' => 'siteservisleri.com', 'DA' => 61, 'TF' => 10, 'DR' => 18, 'SS' => 5],
        ['id' => 27, 'url' => 'techautnews.com', 'DA' => 53, 'TF' => 10, 'DR' => 2, 'SS' => 7],
        ['id' => 28, 'url' => 'techgravenews.com', 'DA' => 52, 'TF' => 10, 'DR' => 6, 'SS' => 8],
        ['id' => 29, 'url' => 'usamynews.com', 'DA' => 54, 'TF' => 17, 'DR' => 7, 'SS' => 9],
        ['id' => 30, 'url' => 'wejustunlock.com', 'DA' => 41, 'TF' => 0, 'DR' => 3, 'SS' => 3],
        ['id' => 31, 'url' => 'cuttlefishlearning.com', 'DA' => 40, 'TF' => 4, 'DR' => 3, 'SS' => 9],
        ['id' => 32, 'url' => 'extremeskiboats.com', 'DA' => 40, 'TF' => 0, 'DR' => 1, 'SS' => 6],
        ['id' => 33, 'url' => 'bharatfans.com', 'DA' => 42, 'TF' => 0, 'DR' => 2, 'SS' => 9],
        ['id' => 34, 'url' => 'foodfarmfilmfest.com', 'DA' => 42, 'TF' => 6, 'DR' => 7, 'SS' => 9],
        ['id' => 35, 'url' => 'ammunitionnearme.com', 'DA' => 53, 'TF' => 10, 'DR' => 5, 'SS' => 0],
    ];

@endphp

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">.Us Domains Set</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('index') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('domain') }}" class="breadcrumb-link">Domains</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('article') }}" class="breadcrumb-link">.us domain set</a>
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

                    {{-- selecting domains types --}}

                    <div
                        class="text-xl bg-[var(--primary-color)] flex justify-center !py-5 rounded font-semibold  capitalize w-full">
                        <p class="text-sm bg-white font-normal !px-6 !py-3 rounded w-fit">
                            .us domains
                        </p>
                    </div>

                    {{--  domains Upload Buttons --}}

                    {{-- table code here --}}

                    <div class="overflow-x-auto !mt-3 w-full">
                        <table id="myTable"
                            class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">
                            <thead>
                                <tr class="bg-gray-800 text-white">
                                    {{-- <th><input type="checkbox" name="bulk_category_select[]" class="scale-125 "></th> --}}
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
                                        {{-- <td class="border border-gray-200 font-sans !px-2 !py-[6px] text-center "> <input
                                                type="checkbox" name="bulk_category_select[]" value="{{ $index + 1 }}"
                                                id=""> --}}
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

                                                <a href="{{ route('domain.edit', $index) }}"
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
                    </div>




                </div>













            </div>

            {{-- </div> --}}
        </div>

    </div>

    </div>


@endsection



@push('scripts')
    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script type="module" src="{{ asset('js/general.js') }}"></script>

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
                    [0, 'asc']
                ], // default sort by sno
                columnDefs: [{
                        orderable: false,
                        targets: [6]
                    }, // disable sort for checkbox + action
                    {
                        className: "!text-center !align-middle",
                        targets: [0, 2, 3, 4, 5, 6]
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
