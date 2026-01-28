@extends('layout.layout')

@section('title', 'Test')

@push('style')
    <style>
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
@section('main-content')

    {{-- 
    <div class="w-full max-w-lg bg-white rounded-2xl shadow !p-6">
        <h2 class="text-xl font-semibold !mb-4 text-gray-700">Upload PDF Files (≤ 100 KB)</h2>

        <!-- Dropzone -->
        <div id="dropzone"
            class="border-2 border-dashed border-gray-300 rounded-xl !p-10 text-center cursor-pointer hover:border-orange-400 transition">
            <p class="text-gray-500">Drag & Drop your PDF files here<br>or click to upload</p>
            <input id="fileInput" type="file" accept=".pdf" multiple class="hidden" />
        </div>

        <!-- Results -->
        <div class="!mt-6">
            <p class="text-sm text-gray-700">✅ Uploaded PDFs: <span id="successCount"
                    class="font-semibold text-green-600">0</span></p>
            <p class="text-sm text-gray-700">❌ Invalid files: <span id="errorCount"
                    class="font-semibold text-red-600">0</span></p>
        </div>

        <!-- File List -->
        <ul id="fileList" class="mt-4 text-sm text-gray-600 space-y-1"></ul>
    </div> --}}

    @php
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

    <div class="w-full content-card">
        <h2 class="text-2xl  !mb-4">Users</h2>
        <div class="overflow-x-auto !mt-3 w-full">
            <table id="myTable" class="display w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        <th><input type="checkbox" name="bulk_category_select[]" class="scale-125 "></th>
                        @php
                            $tHead = ['sno', 'url', 'DA', 'TF', 'DR', 'SS', 'Action'];
                        @endphp
                        @foreach ($tHead as $t)
                            <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
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
        </div>
    </div>




@endsection

@push('scripts')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
        integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#myTable').DataTable({
                pageLength: 10,
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
