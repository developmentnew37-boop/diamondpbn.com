@extends('admin.layout.layout')

@section('title', 'Dashboard - Diamond PBN')



@section('main-content')
    {{-- bread-crumbs --}}
    <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <div class="breadcrumb">
            <div class="breadcrumb-item">
                <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
            </div>
        </div>
    </div>

    {{-- Welcome Message --}}
    <div class="content-card !mb-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Welcome back, {{ $admin->name }}!</h2>
                <p class="text-sm text-gray-500 !mt-1">
                    Role: <span class="font-medium text-gray-700">{{ $admin->getRoleName() }}</span>
                    @if ($admin->isSuperAdmin())
                        <span class="!ml-2 !px-2 !py-1 bg-green-100 text-green-700 text-xs rounded-full">Full Access</span>
                    @endif
                </p>
            </div>
            <a href="{{ route('admin.profile') }}"
                class="bg-black !px-4 !py-2 rounded text-sm font-sans text-white cursor-pointer duration-300 hover:bg-gray-700 flex items-center gap-2">
                <span class="material-symbols-outlined !text-[18px]">person</span>
                View Profile
            </a>
        </div>
    </div>

    <div class="content-card">
        <h2 class="card-title font-sans">PBN Analytics</h2>
        <h2 class="text-sm text-gray-700 font-sans">
            @if ($admin->isSuperAdmin())
                System-wide analytics overview
            @else
                Your personal analytics overview
            @endif
        </h2>
        <div class="w-full flex p-2 flex-wrap gap-2 !gap-y-6 justify-between !mt-4">
            @foreach ($stats as $stat)
                @if ($stat['visible'])
                    <div
                        class="w-[13%] text-sm bg-gray-50 rounded-md flex flex-col !p-4 items-center gap-2 border border-[#eee]">
                        <div class="w-[50px] h-[50px] {{ $stat['bg'] }} rounded-full flex items-center justify-center">
                            <svg viewBox="0 0 24 24" width="24" height="24" fill="none"
                                stroke="{{ $stat['stroke'] }}" stroke-width="2">
                                {!! $stat['icon'] !!}
                            </svg>
                        </div>
                        <h2 class="font-sans font-bold text-3xl capitalize">{{ number_format($stat['count']) }}</h2>
                        <h2 class="text-md text-gray-600 font-sans">{{ $stat['label'] }}</h2>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- graphics --}}
    <div class="w-full flex flex-wrap gap-4 justify-center overflow-hidden">
        <div class="max-w-7xl w-[74%] mx-auto content-card">
            <div class="px-6 pt-6 flex items-center justify-between">
                <h2 class="text-lg font-semibold">PBN Data Analytics</h2>
                <div class="hidden sm:flex items-center gap-4 text-sm">
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-[#ff4a17]"></span> PBN Post Campaigns
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span> PBN Blogroll Campaigns
                    </span>
                    <span class="inline-flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span> PBN Hidden Link Campaigns
                    </span>
                </div>
            </div>
            <div class="p-2 sm:p-6">
                <div id="pbncampaigns-data" class="h-[340px] w-full"></div>
            </div>
        </div>

        <div class="w-1/4 max-w-[600px] flex flex-col content-card">
            <div class="flex items-center justify-between mb-4 w-full">
                <h2 class="text-lg font-semibold">Articles Usage</h2>
                <span class="!px-3 !py-1.5 text-sm font-sans bg-slate-100 rounded-lg font-medium">
                    @if ($admin->isSuperAdmin())
                        All Users
                    @else
                        Your Articles
                    @endif
                </span>
            </div>
            <div id="articlesChart"></div>
            <div id="articlesPercent" class="text-4xl text-center font-bold mt-2 -translate-y-[100%]"
                style="color: var(--primary-color)">{{ $articleUsage['percentage'] }}%</div>
            <div class="mt-4 grid grid-cols-3 justify-center text-sm text-slate-600 w-full !mb-4">
                <div class="flex flex-col gap-2 items-center">
                    <div class="font-medium text-slate-500">Total Articles</div>
                    <div id="TotalCount" class="text-base font-semibold">{{ number_format($articleUsage['total']) }}</div>
                </div>
                <div class="flex flex-col gap-2 items-center">
                    <div class="font-medium text-slate-600">Used</div>
                    <div id="usedCount" class="text-base font-semibold">{{ number_format($articleUsage['used']) }}</div>
                </div>
                <div class="flex flex-col gap-2 items-center">
                    <div class="font-medium text-slate-500">Remaining</div>
                    <div id="remainingCount" class="text-base font-semibold">
                        {{ number_format($articleUsage['remaining']) }}</div>
                </div>
            </div>
            <div class="!mt-6 rounded bg-indigo-50 !px-4 !py-3 text-sm text-slate-700">
                <span id="usageMsg">
                    You've used <strong>{{ $articleUsage['percentage'] }}%</strong> of your articles.
                    <strong>{{ number_format($articleUsage['remaining']) }}</strong> remain available.
                </span>
            </div>
        </div>
    </div>

    {{-- Articles by Language Chart --}}
    <div class="w-full flex flex-wrap gap-4 justify-center overflow-hidden !mt-4">
        <div class="w-full content-card">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold">Articles by Language</h2>
                <a href="{{ route('admin.articles.language.index') }}" class="text-sm text-blue-600 hover:underline">Manage
                    Languages</a>
            </div>
            @if (count($articlesByLanguage['labels']) > 0)
                <div class="flex items-center gap-8">
                    <div id="articlesByLanguageChart" class="w-1/2" style="min-height: 300px;"></div>
                    <div class="w-1/2">
                        <div class="grid grid-cols-2 gap-3">
                            @foreach ($articlesByLanguage['labels'] as $index => $language)
                                <div class="flex items-center gap-3 !p-3 bg-gray-50 rounded-lg">
                                    <div class="w-4 h-4 rounded-full"
                                        style="background-color: {{ $articlesByLanguage['colors'][$index] }}"></div>
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-700">{{ $language }}</p>
                                        <p class="text-lg font-bold text-gray-900">
                                            {{ number_format($articlesByLanguage['data'][$index]) }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="text-center !py-8 text-gray-500">
                    <span class="material-symbols-outlined text-4xl !mb-2">article</span>
                    <p>No articles found. Create some articles to see language distribution.</p>
                </div>
            @endif
        </div>
    </div>

    @if ($admin->isSuperAdmin())
        {{-- Super Admin: User Statistics Section --}}
        <div class="content-card !mt-4">
            <div class="flex items-center justify-between !mb-4">
                <h2 class="text-lg font-semibold">User Statistics</h2>
                <a href="{{ route('admin.user.index') }}" class="text-sm text-blue-600 hover:underline">View All
                    Users</a>
            </div>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-green-50 rounded-lg !p-4 border border-green-200">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-green-200 rounded-full flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-700">admin_panel_settings</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-green-700">
                                {{ \App\Models\Admin::where('type', 0)->count() }}</p>
                            <p class="text-sm text-green-600">Super Admins</p>
                        </div>
                    </div>
                </div>
                <div class="bg-blue-50 rounded-lg !p-4 border border-blue-200">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-blue-200 rounded-full flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-700">shield_person</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold !text-blue-700">
                                {{ \App\Models\Admin::where('type', 1)->count() }}
                            </p>
                            <p class="text-sm !text-blue-700">Admins</p>
                        </div>
                    </div>
                </div>
                <div class="bg-purple-50 rounded-lg !p-4 border border-purple-200">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-purple-200 rounded-full flex items-center justify-center">
                            <span class="material-symbols-outlined text-purple-700">group</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-purple-700">
                                {{ \App\Models\Admin::where('type', 2)->count() }}</p>
                            <p class="text-sm text-purple-600">Members</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="w-full flex flex-wrap gap-4 justify-center overflow-hidden">
        <div class="max-w-7xl w-[74%] mx-auto content-card">
            <div class="px-6 pt-6 flex justify-between">
                <h2 class="text-lg font-semibold">PBN Orders Campaigns Data</h2>
            </div>
            <div class="flex flex-wrap gap-2 items-center !mt-5">
                @php
                    $campaignTypes = [
                        ['label' => 'PBN Post Campaigns', 'type' => 'post', 'active' => true],
                        ['label' => 'PBN Sidebar Post Campaigns', 'type' => 'sidebar', 'active' => false],
                        ['label' => 'PBN Dripfeed Campaigns', 'type' => 'dripfeed', 'active' => false],
                        ['label' => 'PBN Sticky Post Campaigns', 'type' => 'sticky', 'active' => false],
                    ];
                @endphp

                @foreach ($campaignTypes as $item)
                    <button type="button" data-campaign-type="{{ $item['type'] }}"
                        class="campaign-tab-btn {{ $item['active'] ? 'active-bg' : 'bg-black' }} !p-3 rounded text-center text-sm font-sans text-white cursor-pointer duration-300 hover:bg-[var(--primary-color)]">
                        {{ $item['label'] }}
                    </button>
                @endforeach
            </div>

            <div class="flex flex-wrap overflow-x-auto !mt-6">
                <table class="w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">
                    <thead>
                        <tr class="bg-black text-white active-border-color">
                            @php
                                $tHead = [
                                    'S.No',
                                    'Campaign',
                                    'Category',
                                    'Quantity',
                                    'Links',
                                    'Domains',
                                    'Date',
                                    'Action',
                                ];
                            @endphp
                            @foreach ($tHead as $t)
                                <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitalize text-left">
                                    {{ $t }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody id="campaigns-table-body">
                        @forelse ($campaigns as $index => $campaign)
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $index + 1 }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $campaign['campaign'] }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $campaign['domain'] }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $campaign['quantity'] }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $campaign['links'] }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $campaign['keywords'] }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">{{ $campaign['date'] }}</td>
                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    <div class="flex flex-wrap gap-2 justify-center">
                                        <a href="{{ route('admin.campaign.show', $campaign['id']) }}"
                                            class="bg-green-600 text-white flex items-center justify-center rounded w-fit !px-3 !py-1 duration-500 hover:bg-green-700">
                                            view
                                        </a>

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8"
                                    class="border border-gray-200 font-sans !px-2 !py-4 text-center text-gray-500">
                                    No campaigns found
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="w-1/4 max-w-[600px] flex flex-col content-card">
            <div class="flex flex-col gap-5 mb-4 w-full">
                <h2 class="text-lg font-semibold">Domain Categories</h2>
                <div class="flex flex-wrap overflow-x-auto w-full">
                    <table class="w-full border border-gray-200 border-collapse text-sm whitespace-nowrap">
                        <thead>
                            <tr class="active-bg text-white active-border-color">
                                @php
                                    $tHead2 = ['S.No', 'Category', 'Domains', 'Action'];
                                @endphp
                                @foreach ($tHead2 as $t)
                                    <th
                                        class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitalize text-left">
                                        {{ $t }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($domainCategories as $index => $domain)
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">{{ $index + 1 }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">{{ $domain['domain'] }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-3">
                                        {{ number_format($domain['quantity']) }}</td>
                                    <td class="border border-gray-200 font-sans !px-2 !py-2">
                                        <div class="flex flex-wrap gap-2 justify-center">
                                            <a href="{{ route('admin.domain.index', [
                                                'category_id' => $domain['id'],
                                            ]) }}"
                                                class="bg-black flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-gray-700">
                                                <span
                                                    class="material-symbols-outlined !text-[16px] text-white">visibility</span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4"
                                        class="border border-gray-200 font-sans !px-2 !py-4 text-center text-gray-500">
                                        No domain categories found
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="w-full flex justify-end">
                    <a href="{{ route('admin.domain.category.index') }}"
                        class="bg-black !p-3 rounded text-center text-sm font-sans text-white cursor-pointer duration-300 hover:bg-gray-700">
                        View More
                    </a>
                </div>
            </div>
        </div>
    </div>




@endsection

@push('scripts')
    {{-- <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script> --}}
    <script>
        // Chart data from server
        const chartData = @json($chartData);
        const articleUsage = @json($articleUsage);

        // PBN Campaigns Chart
        const categories = chartData.categories;
        const pbnPostCampaigns = chartData.pbnPostCampaigns;
        const pbnBlogrollCampaigns = chartData.pbnBlogrollCampaigns;
        const pbnStickyCampaigns = chartData.pbnStickyCampaigns;

        const kFmt = (val) => (val === 0 ? "0" : val < 1000 ? val.toString() : `${Math.round(val/1000)}K`);

        const options = {
            chart: {
                height: 340,
                type: "line",
                toolbar: {
                    show: false
                },
                foreColor: "#64748b",
                fontFamily: "Outfit, sans-serif",
            },
            series: [{
                    name: "PBN Posts",
                    type: "column",
                    data: pbnPostCampaigns,
                    color: "#ff4a17"
                },
                {
                    name: "PBN Blogroll",
                    type: "column",
                    data: pbnBlogrollCampaigns,
                    color: "#cbd5e1"
                },
                {
                    name: "PBN Hidden Links",
                    type: "line",
                    data: pbnStickyCampaigns,
                    color: "#ff4a17"
                },
            ],
            stroke: {
                width: [0, 0, 3],
                curve: "smooth"
            },
            dataLabels: {
                enabled: false
            },
            markers: {
                size: [0, 0, 3],
                strokeWidth: 0
            },
            grid: {
                borderColor: "#e2e8f0",
                strokeDashArray: 4,
                padding: {
                    left: 8,
                    right: 8
                },
            },
            xaxis: {
                categories,
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                },
                labels: {
                    minHeight: 22,
                    style: {
                        fontSize: "12px",
                        fontFamily: "Outfit, sans-serif"
                    }
                },
            },
            yaxis: {
                min: 0,
                tickAmount: 7,
                labels: {
                    style: {
                        fontFamily: "Outfit, sans-serif"
                    },
                    formatter: (val) => kFmt(Math.round(val)),
                },
            },
            plotOptions: {
                bar: {
                    columnWidth: "26%",
                    borderRadius: 6
                }
            },
            tooltip: {
                shared: true,
                intersect: false,
                style: {
                    fontFamily: "Outfit, sans-serif"
                },
                y: {
                    formatter: (val) => val
                },
            },
            legend: {
                show: false,
                fontFamily: "Outfit, sans-serif"
            },
            responsive: [{
                    breakpoint: 1024,
                    options: {
                        plotOptions: {
                            bar: {
                                columnWidth: "32%"
                            }
                        }
                    },
                },
                {
                    breakpoint: 640,
                    options: {
                        yaxis: {
                            tickAmount: 5
                        },
                        grid: {
                            padding: {
                                left: 0,
                                right: 0
                            }
                        },
                        plotOptions: {
                            bar: {
                                columnWidth: "38%",
                                borderRadius: 5
                            }
                        },
                        markers: {
                            size: [0, 0, 2.5]
                        },
                    },
                },
            ],
        };

        // Articles Usage Chart
        const options2 = {
            chart: {
                type: "radialBar",
                height: 220,
                toolbar: {
                    show: false
                },
                fontFamily: "Outfit, sans-serif",
            },
            series: [articleUsage.percentage],
            labels: ["Used"],
            colors: ["#ff4a17"],
            fill: {
                type: "gradient",
                gradient: {
                    shade: "light",
                    gradientToColors: ["#fa7e5c"],
                    stops: [0, 100],
                },
            },
            plotOptions: {
                radialBar: {
                    startAngle: -90,
                    endAngle: 90,
                    hollow: {
                        size: "60%"
                    },
                    track: {
                        background: "#e2e8f0",
                        strokeWidth: "90%",
                        margin: 4,
                    },
                    dataLabels: {
                        name: {
                            show: false
                        },
                        value: {
                            show: false
                        },
                    },
                },
            },
            stroke: {
                lineCap: "round"
            },
            tooltip: {
                enabled: true,
                y: {
                    formatter: (val) => `${val}%`
                },
                style: {
                    fontFamily: "Outfit, sans-serif"
                },
            },
        };

        // Articles by Language Chart
        const articlesByLanguage = @json($articlesByLanguage);

        const languageChartOptions = {
            chart: {
                type: 'donut',
                height: 300,
                fontFamily: 'Outfit, sans-serif',
            },
            series: articlesByLanguage.data,
            labels: articlesByLanguage.labels,
            colors: articlesByLanguage.colors,
            plotOptions: {
                pie: {
                    donut: {
                        size: '55%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total Articles',
                                formatter: () => articlesByLanguage.data.reduce((a, b) => a + b, 0).toLocaleString()
                            }
                        }
                    }
                }
            },
            legend: {
                show: false
            },
            dataLabels: {
                enabled: true,
                formatter: (val, opts) => {
                    return opts.w.config.series[opts.seriesIndex];
                }
            },
            tooltip: {
                y: {
                    formatter: (val) => val.toLocaleString() + ' articles'
                }
            }
        };

        document.addEventListener("DOMContentLoaded", () => {
            new ApexCharts(document.querySelector("#pbncampaigns-data"), options).render();
            new ApexCharts(document.querySelector("#articlesChart"), options2).render();

            // Articles by Language chart
            if (articlesByLanguage.data.length > 0) {
                new ApexCharts(document.querySelector("#articlesByLanguageChart"), languageChartOptions).render();
            }

            // Campaign tabs functionality
            const campaignTabs = document.querySelectorAll('.campaign-tab-btn');
            const campaignTableBody = document.getElementById('campaigns-table-body');
            let url = '';
            campaignTabs.forEach(tab => {
                tab.addEventListener('click', async function() {
                    // Update active state
                    campaignTabs.forEach(t => {
                        t.classList.remove('active-bg');
                        t.classList.add('bg-black');
                    });
                    this.classList.remove('bg-black');
                    this.classList.add('active-bg');

                    // Fetch campaigns
                    const type = this.dataset.campaignType;

                    console.log(type)
                    if (type == 'dripfeed') {
                        url = '/admin/campaign/post/schedule/'
                    } else if (type == 'sidebar') {
                        url = '/admin/sidebar/campaign/'
                    } else {
                        url = '/admin/campaign/'
                    }
                    try {
                        const response = await fetch(
                            `{{ route('admin.dashboard.campaigns') }}?type=${type}`);
                        const data = await response.json();

                        if (data.success) {
                            renderCampaigns(data.campaigns,url);
                        }
                    } catch (error) {
                        console.error('Error fetching campaigns:', error);
                    }
                });
            });

            function renderCampaigns(campaigns, url) {
                if (campaigns.length === 0) {
                    campaignTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="border border-gray-200 font-sans !px-2 !py-4 text-center text-gray-500">
                        No campaigns found
                    </td>
                </tr>
            `;
                    return;
                }

                campaignTableBody.innerHTML = campaigns.map((campaign, index) => `
            <tr class="hover:bg-gray-50">
                <td class="border border-gray-200 font-sans !px-2 !py-2">${index + 1}</td>
                <td class="border border-gray-200 font-sans !px-2 !py-2">${campaign.campaign}</td>
                <td class="border border-gray-200 font-sans !px-2 !py-2">${campaign.domain}</td>
                <td class="border border-gray-200 font-sans !px-2 !py-2">${campaign.quantity}</td>
                <td class="border border-gray-200 font-sans !px-2 !py-2">${campaign.links}</td>
                <td class="border border-gray-200 font-sans !px-2 !py-2">${campaign.keywords}</td>
                <td class="border border-gray-200 font-sans !px-2 !py-2">${campaign.date}</td>
                <td class="border border-gray-200 font-sans !px-2 !py-2">
                    <div class="flex flex-wrap gap-2 justify-center">
                 
                        <a href="${url}${campaign.id}" class="bg-green-600 text-white flex items-center justify-center rounded w-fit !px-3 !py-1 duration-500 hover:bg-green-700">
                            view
                        </a>
                    </div>
                </td>
            </tr>
        `).join('');
            }
        });
    </script>
@endpush
