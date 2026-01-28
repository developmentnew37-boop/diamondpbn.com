@extends('layout.layout')

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
    </style>
@endpush

@php
    // article here
    $orders = [
        ['id' => 1, 'order' => '123243 3423746 324767'],
        ['id' => 2, 'order' => '982374 8746327 112398'],
        ['id' => 3, 'order' => '543267 9982734 883721'],
        ['id' => 4, 'order' => '726381 5546218 993847'],
        ['id' => 5, 'order' => '883274 1118273 645392'],
        ['id' => 6, 'order' => '229384 7763219 447821'],
        ['id' => 7, 'order' => '993821 2347812 560987'],
        ['id' => 8, 'order' => '456732 9981237 873642'],
        ['id' => 9, 'order' => '883712 6623891 111445'],
        ['id' => 10, 'order' => '234982 3349823 987654'],
    ];

    $articles = [
        ['id' => 1, 'title' => 'Exploring the Future of Artificial Intelligence'],
        ['id' => 2, 'title' => 'How 5G Technology is Changing Connectivity'],
        ['id' => 3, 'title' => 'Top 10 Gadgets That Defined 2025'],
        ['id' => 4, 'title' => 'The Rise of Quantum Computing Explained'],
        ['id' => 5, 'title' => 'Why Cybersecurity is More Important Than Ever'],
        ['id' => 6, 'title' => 'A Beginner’s Guide to Blockchain Technology'],
        ['id' => 7, 'title' => 'How Smart Glasses Are Redefining AR Experiences'],
        ['id' => 8, 'title' => 'The Future of Gaming with Cloud Technology'],
        ['id' => 9, 'title' => 'What You Need to Know About Web3'],
        ['id' => 10, 'title' => 'Inside the World of Electric Vehicle Innovation'],
        ['id' => 11, 'title' => 'How AI is Powering Modern Healthcare Systems'],
        ['id' => 12, 'title' => 'Virtual Reality in Education: A Game Changer'],
        ['id' => 13, 'title' => 'The Evolution of Mobile Operating Systems'],
        ['id' => 14, 'title' => 'Top Programming Languages to Learn in 2025'],
        ['id' => 15, 'title' => 'The Role of AI in Content Creation'],
        ['id' => 16, 'title' => 'The Rise of Wearable Health Trackers'],
        ['id' => 17, 'title' => 'Building Smarter Cities with IoT'],
        ['id' => 18, 'title' => 'The Future of Robotics in Everyday Life'],
        ['id' => 19, 'title' => 'How Biometric Security Works'],
        ['id' => 20, 'title' => 'Augmented Reality vs Virtual Reality: Key Differences'],
        ['id' => 21, 'title' => 'The Evolution of Online Streaming Platforms'],
        ['id' => 22, 'title' => 'How AI is Transforming Financial Services'],
        ['id' => 23, 'title' => 'Exploring OpenAI’s Latest Language Models'],
        ['id' => 24, 'title' => 'What is Edge Computing and Why It Matters'],
        ['id' => 25, 'title' => 'The Impact of Social Media on Modern Marketing'],
        ['id' => 26, 'title' => 'Building Scalable Web Apps with React and Laravel'],
        ['id' => 27, 'title' => 'Explained: Neural Networks and Deep Learning'],
        ['id' => 28, 'title' => 'How Drones Are Changing Photography'],
        ['id' => 29, 'title' => 'Data Privacy in the Age of AI'],
        ['id' => 30, 'title' => 'Top Cyber Threats to Watch Out for in 2025'],
        ['id' => 31, 'title' => 'The Future of Smart Home Automation'],
        ['id' => 32, 'title' => 'How AR is Changing Online Shopping Experiences'],
        ['id' => 33, 'title' => 'The Science Behind Electric Car Batteries'],
        ['id' => 34, 'title' => 'Exploring the Metaverse: Opportunities & Risks'],
        ['id' => 35, 'title' => 'AI-Powered Chatbots: Revolutionizing Customer Service'],
        ['id' => 36, 'title' => 'A Deep Dive into Machine Learning Algorithms'],
        ['id' => 37, 'title' => 'Why Dark Mode is More Than Just a Trend'],
        ['id' => 38, 'title' => 'How Cloud Storage is Evolving in 2025'],
        ['id' => 39, 'title' => 'Understanding the Basics of Cryptocurrency'],
        ['id' => 40, 'title' => 'How Voice Assistants Are Getting Smarter'],
        ['id' => 41, 'title' => 'The Future of AI in Game Development'],
        ['id' => 42, 'title' => 'The Growing Role of Tech in Healthcare'],
        ['id' => 43, 'title' => 'AI in Photography: How It’s Changing Creativity'],
        ['id' => 44, 'title' => 'The Best Laptops for Developers in 2025'],
        ['id' => 45, 'title' => 'How Automation is Transforming Small Businesses'],
        ['id' => 46, 'title' => 'Understanding the Internet of Things (IoT)'],
        ['id' => 47, 'title' => 'What is Serverless Computing?'],
        ['id' => 48, 'title' => 'The Rise of AI Agents and Autonomous Systems'],
        ['id' => 49, 'title' => 'How Big Data is Shaping Decision Making'],
        ['id' => 50, 'title' => 'The Future of Remote Work Technology'],
        ['id' => 51, 'title' => 'Exploring Advances in Battery Technology'],
        ['id' => 52, 'title' => 'How 3D Printing is Changing Manufacturing'],
        ['id' => 53, 'title' => 'Why Developers Love TypeScript'],
        ['id' => 54, 'title' => 'The Role of AI in Climate Change Research'],
        ['id' => 55, 'title' => 'Understanding How GPUs Accelerate AI'],
        ['id' => 56, 'title' => 'The Importance of Ethical AI Development'],
        ['id' => 57, 'title' => 'How Open Source Drives Innovation'],
        ['id' => 58, 'title' => 'Smartwatches: The Next Health Revolution'],
        ['id' => 59, 'title' => 'The Role of APIs in Modern Web Development'],
        ['id' => 60, 'title' => 'How AI is Shaping Creative Industries'],
        ['id' => 61, 'title' => 'The Future of Augmented Reality in Retail'],
        ['id' => 62, 'title' => 'AI-Powered Tools Every Developer Should Know'],
        ['id' => 63, 'title' => 'Exploring Human-AI Collaboration'],
        ['id' => 64, 'title' => 'Why Data Analytics is the Future of Business'],
        ['id' => 65, 'title' => 'The Rise of Decentralized Applications (DApps)'],
        ['id' => 66, 'title' => 'How AI is Helping Detect Fake News'],
        ['id' => 67, 'title' => 'The Impact of 6G on Global Connectivity'],
        ['id' => 68, 'title' => 'Exploring the World of AI Art Generators'],
        ['id' => 69, 'title' => 'The Science Behind Brain-Computer Interfaces'],
        ['id' => 70, 'title' => 'AI in Cyber Defense: Protecting the Future'],
        ['id' => 71, 'title' => 'The Role of Automation in Software Testing'],
        ['id' => 72, 'title' => 'How AI is Transforming Video Editing'],
        ['id' => 73, 'title' => 'The Rise of Generative AI Models'],
        ['id' => 74, 'title' => 'Understanding Reinforcement Learning'],
        ['id' => 75, 'title' => 'The Future of AI Regulation and Policy'],
        ['id' => 76, 'title' => 'AI-Driven Search Engines: What’s Next?'],
        ['id' => 77, 'title' => 'The Power of Multimodal AI Systems'],
        ['id' => 78, 'title' => 'How AI is Enhancing Cybersecurity Systems'],
        ['id' => 79, 'title' => 'The Evolution of Cloud Gaming Platforms'],
        ['id' => 80, 'title' => 'What the Next Decade of AI Might Look Like'],
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
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('index') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Sticky Campaign</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Create</a>

                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                {{-- <a href="" class="flex !p-2 !py-3 text-[16px] font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">+ Add Article</a> --}}
            </div>
        </div>
    </div>



    <div class="w-full flex flex-wrap justify-between items-start content-card">
        <h2 class="text-xl capitalize !mb-4 bg-[var(--primary-color)] text-white w-fit !p-2 rounded">Sticky Post Campaigns
        </h2>
        <div class="w-full flex flex-col gap-2">
            <div class="w-full flex flex-col gap-3 ">
                <label for="campaign-domain"
                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">select
                    campaigns
                </label>
                <div data-dropdown-container class="w-full flex flex-col gap-3 p-2 relative">
                    {{-- Hidden input for form submission --}}
                    <input data-hidden-input type="hidden" id="sticky-order" name="sticky-order" value="">

                    {{-- Select Button --}}
                    <button data-select-btn type="button" id="selectBtn"
                        class="w-full bg-gray-100 border border-gray-200 outline-none rounded !p-3 text-left flex text-sm items-center justify-between  focus:border-orange-600 transition-all">
                        <span data-select-text id="selectText" class="text-gray-400">Select
                            campaigns ...</span>
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
                            <button data-option-item type="button"
                                class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                data-value="" data-label="--Select--">
                                <span class="text-sm ">--Select--</span>
                            </button>
                            @if (isset($orders) && count($orders) > 0)
                                @foreach ($orders as $order)
                                    <button data-option-item type="button"
                                        class="option-item w-full !px-4  !py-3 text-left hover:bg-gray-100 flex items-center justify-between transition-colors"
                                        data-value="{{ $order['id'] }}" data-label="{{ $order['order'] }}">
                                        <span class="text-sm ">campaign - {{ $order['order'] }}</span>
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
        </div>
    </div>
    <div class="w-full flex flex-col gap-2 justify-between items-start content-card">
        <label for="campaign-domain" class="text-sm flex items-center ">Urls</label>
        <textarea name="" id=""
            class="w-full bg-gray-100 border border-gray-300 resize-none outline-none rounded !p-2 text-sm" rows="20"></textarea>
        <button
            class="flex !p-2 !py-3 text-[16px] font-normal w-fit justify-center duration:300 bg-green-500 whitespace-nowrap hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">Submit
            Urls</button>
    </div>

    <div class="w-full flex flex-col gap-2 justify-between items-start content-card">
        <div class="w-full flex flex-col gap-2">
            <table id="StickyPostTable" class="display !w-full border border-gray-200 border-collapse text-sm ">
                <thead>
                    <tr class="bg-gray-800 text-white">
                        @php
                            $tHead = ['url', 'Date', 'Status'];
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


                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                <label for="sel-domain-check-box-{{ $index + 1 }}" class="cursor-pointer w-full">
                                    {{ $site['url'] }}</label>
                            </td>
                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">{{ now()->format('d-m-Y') }}
                            </td>
                            <td class="border border-gray-200 font-sans !px-2 !py-[6px]">
                                <span class="flex !px-3 !py-2 bg-green-100 text-green-600 text-sm w-fit">success</span>
                            </td>


                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection



@push('scripts')

    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/general.js') }}"></script>
    <script src="{{ asset('js/create-campaign.js') }}"></script>
    <script src="{{ asset('js/selectBox.js') }}"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"
        integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script src="https://cdn.datatables.net/2.3.4/js/dataTables.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        $('#StickyPostTable').DataTable({
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
                    targets: [] //1, 2
                }, // disable sort for checkbox + action
                {
                    className: "!text-center !align-middle !px-2",
                    targets: [2]
                }, // center align checkbox + numeric cols

            ],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
            }
        });
    </script>
@endpush
