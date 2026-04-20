@extends('admin.layout.layout')

@section('title', 'Dashboard - Diamond PBN')

@push('style')
    <style>
        .dashboard-stat-card {
            transition: box-shadow 0.2s ease, transform 0.2s ease;
        }
        .dashboard-stat-card:hover {
            box-shadow: 0 8px 24px -6px rgba(15, 23, 42, 0.12);
        }
        .dashboard-campaign-tabs {
            scrollbar-width: thin;
        }
        /* Fill grid column — no horizontal scroll; Apex sizes to this box */
        .dashboard-main-chart-wrap {
            width: 100%;
            max-width: 100%;
            min-width: 0;
        }
        #pbncampaigns-data {
            width: 100% !important;
            max-width: 100% !important;
            min-height: 280px;
        }
        @media (min-width: 640px) {
            #pbncampaigns-data {
                min-height: 340px;
            }
        }
        #pbncampaigns-data .apexcharts-canvas,
        #pbncampaigns-data .apexcharts-svg,
        #pbncampaigns-data svg {
            max-width: 100% !important;
        }
        /* Apex sometimes sets overflow:auto on inner wrapper — avoid stray scrollbars */
        #pbncampaigns-data .apexcharts-inner,
        #pbncampaigns-data .apexcharts-legend,
        #pbncampaigns-data {
            overflow: visible !important;
        }
    </style>
@endpush

@section('main-content')
    <div
        class="dashboard-home w-full max-w-[1600px] !mx-auto !px-2 sm:!px-0 !pb-6 sm:!pb-8 !space-y-4 sm:!space-y-5">

        {{-- Top bar --}}
        <div class="flex flex-col !gap-3 sm:!gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl sm:text-3xl font-semibold text-gray-900 tracking-tight">Dashboard</h1>
                <p class="text-sm text-gray-500 !mt-1 hidden sm:block">Overview of your PBN operations</p>
            </div>
            <a href="{{ route('admin.profile') }}"
                class="inline-flex items-center justify-center !gap-2 rounded-xl bg-[var(--primary-color)] !px-5 !py-2.5 text-sm font-medium text-white shadow-sm transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-orange-400 focus:ring-offset-2 shrink-0">
                <span class="material-symbols-outlined !text-[20px]">person</span>
                View Profile
            </a>
        </div>

        {{-- Welcome --}}
        <div
            class="relative overflow-hidden rounded-2xl border border-gray-200/80 bg-white !p-4 sm:!p-5 shadow-sm sm:flex sm:items-center sm:justify-between !gap-4">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-[var(--primary-color)] via-orange-400 to-amber-400"></div>
            <div class="flex items-start !gap-3 sm:!gap-4">
                <div
                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-orange-50 text-lg font-bold text-[var(--primary-color)] ring-1 ring-orange-100">
                    {{ strtoupper(mb_substr((string) ($admin->name ?? '?'), 0, 1)) }}
                </div>
                <div>
                    <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Welcome back, {{ $admin->name }}!</h2>
                    <p class="!mt-1 flex flex-wrap items-center !gap-2 text-sm text-gray-600">
                        <span>Role: <span class="font-medium text-gray-800">{{ $admin->getRoleName() }}</span></span>
                        @if ($admin->isSuperAdmin())
                            <span
                                class="inline-flex items-center rounded-full bg-emerald-50 !px-2.5 !py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-100">Full
                                Access</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Quick actions / Run campaigns first (especially on mobile) --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5">
            <h2 class="text-lg font-semibold text-gray-900 !mb-3">Quick actions</h2>
            <p class="text-sm text-gray-500 !mb-3 lg:hidden">Create campaigns, add content, and manage domains.</p>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 !gap-2 sm:!gap-3">
                @if ($admin->canCreateCampaigns())
                    <a href="{{ route('admin.campaign.create') }}"
                        class="group flex min-h-[5.5rem] flex-col items-center justify-center !gap-2 rounded-xl border border-gray-100 bg-gradient-to-br from-orange-50 to-white !p-3 sm:!p-4 text-center transition hover:border-[var(--primary-color)]/40 hover:shadow-md">
                        <span
                            class="material-symbols-outlined text-[var(--primary-color)] text-3xl group-hover:scale-105 transition-transform">rocket_launch</span>
                        <span class="text-xs sm:text-sm font-medium text-gray-800 leading-tight">PBN Post</span>
                    </a>
                    <a href="{{ route('admin.sidebar.campaign.create') }}"
                        class="group flex min-h-[5.5rem] flex-col items-center justify-center !gap-2 rounded-xl border border-gray-100 bg-gradient-to-br from-fuchsia-50 to-white !p-3 sm:!p-4 text-center transition hover:border-fuchsia-300 hover:shadow-md">
                        <span
                            class="material-symbols-outlined text-fuchsia-600 text-3xl group-hover:scale-105 transition-transform">view_sidebar</span>
                        <span class="text-xs sm:text-sm font-medium text-gray-800 leading-tight">Sidebar campaign</span>
                    </a>
                    <a href="{{ route('admin.hidden.link.campaign.create') }}"
                        class="group flex min-h-[5.5rem] flex-col items-center justify-center !gap-2 rounded-xl border border-gray-100 bg-gradient-to-br from-slate-50 to-white !p-3 sm:!p-4 text-center transition hover:border-slate-400 hover:shadow-md">
                        <span
                            class="material-symbols-outlined text-slate-600 text-3xl group-hover:scale-105 transition-transform">link</span>
                        <span class="text-xs sm:text-sm font-medium text-gray-800 leading-tight">Hidden links</span>
                    </a>
                @endif
                <a href="{{ route('admin.articles.opt') }}"
                    class="group flex min-h-[5.5rem] flex-col items-center justify-center !gap-2 rounded-xl border border-gray-100 bg-gradient-to-br from-violet-50 to-white !p-3 sm:!p-4 text-center transition hover:border-violet-200 hover:shadow-md">
                    <span
                        class="material-symbols-outlined text-violet-600 text-3xl group-hover:scale-105 transition-transform">post_add</span>
                    <span class="text-xs sm:text-sm font-medium text-gray-800 leading-tight">Add articles</span>
                </a>
                @if ($admin->canCreateCampaigns())
                    <a href="{{ route('admin.schedule.campaign.index') }}"
                        class="group flex min-h-[5.5rem] flex-col items-center justify-center !gap-2 rounded-xl border border-gray-100 bg-gradient-to-br from-sky-50 to-white !p-3 sm:!p-4 text-center transition hover:border-sky-200 hover:shadow-md">
                        <span
                            class="material-symbols-outlined text-sky-600 text-3xl group-hover:scale-105 transition-transform">schedule</span>
                        <span class="text-xs sm:text-sm font-medium text-gray-800 leading-tight">Schedule post</span>
                    </a>
                @endif
                <a href="{{ route('admin.select.category') }}"
                    class="group flex min-h-[5.5rem] flex-col items-center justify-center !gap-2 rounded-xl border border-gray-100 bg-gradient-to-br from-emerald-50 to-white !p-3 sm:!p-4 text-center transition hover:border-emerald-200 hover:shadow-md">
                    <span
                        class="material-symbols-outlined text-emerald-600 text-3xl group-hover:scale-105 transition-transform">language</span>
                    <span class="text-xs sm:text-sm font-medium text-gray-800 leading-tight">Domains</span>
                </a>
                <a href="{{ route('admin.articles.set.index') }}"
                    class="group flex min-h-[5.5rem] flex-col items-center justify-center !gap-2 rounded-xl border border-gray-100 bg-gradient-to-br from-amber-50 to-white !p-3 sm:!p-4 text-center transition hover:border-amber-200 hover:shadow-md">
                    <span
                        class="material-symbols-outlined text-amber-600 text-3xl group-hover:scale-105 transition-transform">folder_special</span>
                    <span class="text-xs sm:text-sm font-medium text-gray-800 leading-tight">Article set</span>
                </a>
                @if ($admin->canCreateCampaigns())
                    <a href="{{ route('admin.schedule.sidebar.campaign.index') }}"
                        class="group flex min-h-[5.5rem] flex-col items-center justify-center !gap-2 rounded-xl border border-gray-100 bg-gradient-to-br from-rose-50 to-white !p-3 sm:!p-4 text-center transition hover:border-rose-200 hover:shadow-md">
                        <span
                            class="material-symbols-outlined text-rose-600 text-3xl group-hover:scale-105 transition-transform">timeline</span>
                        <span class="text-xs sm:text-sm font-medium text-gray-800 leading-tight">Dripfeed / sidebar</span>
                    </a>
                @endif
            </div>
        </section>

        {{-- PBN Analytics: compact horizontal cards, tight gaps --}}
        <section class="rounded-2xl border border-gray-200/80 bg-white !p-4 sm:!p-5 shadow-sm">
            <div class="!mb-3 sm:!mb-4">
                <h2 class="text-lg font-semibold text-gray-900">PBN Analytics</h2>
                <p class="text-sm text-gray-500 !mt-0.5">
                    @if ($admin->isSuperAdmin())
                        System-wide analytics overview
                    @else
                        Your personal analytics overview
                    @endif
                </p>
            </div>
            <div
                class="grid grid-cols-2 md:grid-cols-4 !gap-2 sm:!gap-3 w-full min-w-0">
                @foreach ($stats as $stat)
                    @if ($stat['visible'])
                        <div
                            class="dashboard-stat-card flex min-h-0 flex-row items-center !gap-2 sm:!gap-3 rounded-xl border border-gray-100 bg-gradient-to-r from-gray-50/90 to-white !p-2.5 sm:!p-3 text-left min-w-0">
                            <div
                                class="flex h-10 w-10 sm:h-11 sm:w-11 shrink-0 items-center justify-center rounded-full {{ $stat['bg'] }} shadow-inner">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none"
                                    stroke="{{ $stat['stroke'] }}" stroke-width="2" class="shrink-0 sm:h-[22px] sm:w-[22px]">
                                    {!! $stat['icon'] !!}
                                </svg>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-lg sm:text-xl font-bold tabular-nums leading-tight text-gray-900">
                                    {{ number_format($stat['count']) }}</p>
                                <p class="text-[11px] sm:text-xs text-gray-600 leading-snug line-clamp-2">{{ $stat['label'] }}</p>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </section>

        {{-- Charts row --}}
        <div class="grid grid-cols-1 !gap-3 lg:grid-cols-12 lg:!gap-4 w-full min-w-0">
            <div
                class="lg:col-span-8 min-w-0 rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5">
                <div class="!mb-3 flex flex-col !gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">PBN Data Analytics</h2>
                    <div class="flex flex-wrap !gap-x-3 !gap-y-1 text-xs sm:text-sm text-gray-600">
                        <span class="inline-flex items-center !gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-[#ff4a17]"></span> PBN Post
                        </span>
                        <span class="inline-flex items-center !gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-slate-300"></span> Blogroll
                        </span>
                        <span class="inline-flex items-center !gap-2">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full bg-slate-400"></span> Hidden Links
                        </span>
                    </div>
                </div>
                {{-- overflow-x-auto + overflow-y-visible forces both axes to scroll in CSS; use visible only --}}
                <div class="dashboard-main-chart-wrap w-full min-w-0 !mt-1 overflow-visible">
                    <div id="pbncampaigns-data" class="w-full"></div>
                </div>
            </div>

            <div
                class="lg:col-span-4 min-w-0 rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5">
                <div class="!mb-3 flex flex-col !gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Articles Usage</h2>
                    <div
                        class="inline-flex items-center !gap-1.5 rounded-lg border border-gray-200 bg-gray-50 !px-2.5 !py-1.5 text-xs font-medium text-gray-700">
                        <span class="material-symbols-outlined !text-[16px] text-gray-500">filter_alt</span>
                        @if ($admin->isSuperAdmin())
                            All Users
                        @else
                            Your Articles
                        @endif
                    </div>
                </div>
                <div class="relative !mx-auto w-full max-w-[260px] !min-h-[200px]">
                    <div id="articlesChart" class="!mx-auto w-full"></div>
                </div>
                <div class="!mt-3 grid grid-cols-3 !gap-1 sm:!gap-2 text-center text-xs sm:text-sm text-slate-600">
                    <div>
                        <div class="font-medium text-slate-500">Total</div>
                        <div id="TotalCount" class="text-sm font-semibold text-gray-900">
                            {{ number_format($articleUsage['total']) }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-slate-500">Used</div>
                        <div id="usedCount" class="text-sm font-semibold text-gray-900">
                            {{ number_format($articleUsage['used']) }}</div>
                    </div>
                    <div>
                        <div class="font-medium text-slate-500">Remaining</div>
                        <div id="remainingCount" class="text-sm font-semibold text-gray-900">
                            {{ number_format($articleUsage['remaining']) }}</div>
                    </div>
                </div>
                <div class="!mt-3 rounded-xl bg-indigo-50/80 !px-3 !py-2.5 text-xs sm:text-sm text-slate-700 ring-1 ring-indigo-100">
                    <span id="usageMsg">
                        You've used <strong>{{ $articleUsage['percentage'] }}%</strong> of your articles.
                        <strong>{{ number_format($articleUsage['remaining']) }}</strong> remain available.
                    </span>
                </div>
            </div>
        </div>

        {{-- User stats + Articles by language --}}
        <div class="grid grid-cols-1 !gap-3 lg:grid-cols-2 lg:!gap-4">
            @if ($admin->isSuperAdmin())
                <section class="rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5">
                    <div class="!mb-3 flex flex-col !gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">User Statistics</h2>
                        <a href="{{ route('admin.user.index') }}" class="text-sm font-medium text-blue-600 hover:underline">View
                            all users</a>
                    </div>
                    <div class="grid grid-cols-1 !gap-2 sm:grid-cols-3 sm:!gap-3">
                        <div
                            class="flex items-center !gap-3 rounded-xl border border-emerald-100 bg-emerald-50/60 !p-3 sm:!p-4">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-emerald-200/80">
                                <span class="material-symbols-outlined text-emerald-800">admin_panel_settings</span>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-emerald-800">
                                    {{ \App\Models\Admin::where('type', 0)->count() }}</p>
                                <p class="text-xs font-medium text-emerald-700">Super Admins</p>
                            </div>
                        </div>
                        <div class="flex items-center !gap-3 rounded-xl border border-blue-100 bg-blue-50/60 !p-3 sm:!p-4">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-blue-200/80">
                                <span class="material-symbols-outlined text-blue-800">shield_person</span>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-blue-800">
                                    {{ \App\Models\Admin::where('type', 1)->count() }}</p>
                                <p class="text-xs font-medium text-blue-700">Admins</p>
                            </div>
                        </div>
                        <div class="flex items-center !gap-3 rounded-xl border border-purple-100 bg-purple-50/60 !p-3 sm:!p-4 sm:col-span-1">
                            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-purple-200/80">
                                <span class="material-symbols-outlined text-purple-800">group</span>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-purple-800">
                                    {{ \App\Models\Admin::where('type', 2)->count() }}</p>
                                <p class="text-xs font-medium text-purple-700">Members</p>
                            </div>
                        </div>
                    </div>
                </section>
            @endif

            <section class="rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5 lg:min-h-0 {{ $admin->isSuperAdmin() ? '' : 'lg:col-span-2' }}">
                <div class="!mb-3 flex flex-col !gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="text-lg font-semibold text-gray-900">Articles by Language</h2>
                    <a href="{{ route('admin.articles.language.index') }}"
                        class="text-sm font-medium text-blue-600 hover:underline shrink-0">Manage languages</a>
                </div>
                @if (count($articlesByLanguage['labels']) > 0)
                    <div class="flex flex-col !gap-4 lg:flex-row lg:items-center">
                        <div id="articlesByLanguageChart" class="w-full lg:w-1/2 !mx-auto min-w-0" style="min-height: 260px;"></div>
                        <div class="w-full lg:w-1/2 min-w-0">
                            <div class="grid grid-cols-1 sm:grid-cols-2 !gap-2 sm:!gap-2 max-h-[280px] overflow-y-auto !pr-1">
                                @foreach ($articlesByLanguage['labels'] as $index => $language)
                                    <div
                                        class="flex items-center !gap-2 rounded-xl border border-gray-100 bg-gray-50/80 !p-2.5 sm:!p-3">
                                        <div class="h-3.5 w-3.5 shrink-0 rounded-full"
                                            style="background-color: {{ $articlesByLanguage['colors'][$index] }}"></div>
                                        <div class="min-w-0 flex-1">
                                            <p class="text-sm font-medium text-gray-800 truncate">{{ $language }}</p>
                                            <p class="text-lg font-bold text-gray-900">
                                                {{ number_format($articlesByLanguage['data'][$index]) }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-200 py-10 text-center text-gray-500">
                        <span class="material-symbols-outlined text-4xl mb-2 opacity-60">article</span>
                        <p class="text-sm">No articles found. Create articles to see language distribution.</p>
                    </div>
                @endif
            </section>
        </div>

        {{-- Campaigns + Domain categories --}}
        <div class="grid grid-cols-1 !gap-3 xl:grid-cols-12 xl:!gap-4">
            <div class="xl:col-span-8 rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5 min-w-0">
                <div class="!mb-3">
                    <h2 class="text-lg font-semibold text-gray-900">PBN Orders Campaigns Data</h2>
                    <p class="text-sm text-gray-500 !mt-0.5">Recent campaigns by type</p>
                </div>
                @php
                    $campaignTypes = [
                        ['label' => 'PBN Post', 'type' => 'post', 'active' => true],
                        ['label' => 'Sidebar', 'type' => 'sidebar', 'active' => false],
                        ['label' => 'Dripfeed', 'type' => 'dripfeed', 'active' => false],
                        ['label' => 'Schedule Sticky', 'type' => 'schedule_sticky', 'active' => false],
                        ['label' => 'Sticky Post', 'type' => 'sticky', 'active' => false],
                    ];
                @endphp
                <div
                    class="dashboard-campaign-tabs flex flex-nowrap !gap-2 overflow-x-auto !pb-2 !-mx-1 !px-1 sm:flex-wrap sm:overflow-visible">
                    @foreach ($campaignTypes as $item)
                        <button type="button" data-campaign-type="{{ $item['type'] }}"
                            class="campaign-tab-btn shrink-0 {{ $item['active'] ? 'active-bg' : 'bg-black' }} whitespace-nowrap rounded-xl !px-4 !py-2.5 text-center text-xs sm:text-sm font-medium text-white transition duration-300 hover:bg-[var(--primary-color)]">
                            {{ $item['label'] }}
                        </button>
                    @endforeach
                </div>

                <div class="!mt-3 overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-[720px] w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-800 text-white">
                                @php
                                    $tHead = ['S.No', 'Campaign', 'Category', 'Quantity', 'Links', 'Domains', 'Date', 'Action'];
                                @endphp
                                @foreach ($tHead as $t)
                                    <th class="border-b border-slate-700 !px-2 !py-3 text-left font-medium first:rounded-tl-xl last:rounded-tr-xl">
                                        {{ $t }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody id="campaigns-table-body">
                            @forelse ($campaigns as $index => $campaign)
                                <tr class="border-b border-gray-100 hover:bg-gray-50/80">
                                    <td class="!px-2 !py-2.5">{{ $index + 1 }}</td>
                                    <td class="!px-2 !py-2.5 max-w-[200px] truncate" title="{{ $campaign['campaign'] }}">{{ $campaign['campaign'] }}</td>
                                    <td class="!px-2 !py-2.5">{{ $campaign['domain'] }}</td>
                                    <td class="!px-2 !py-2.5">{{ $campaign['quantity'] }}</td>
                                    <td class="!px-2 !py-2.5">{{ $campaign['links'] }}</td>
                                    <td class="!px-2 !py-2.5">{{ $campaign['keywords'] }}</td>
                                    <td class="!px-2 !py-2.5 whitespace-nowrap">{{ $campaign['date'] }}</td>
                                    <td class="!px-2 !py-2.5">
                                        <a href="{{ route('admin.campaign.show', $campaign['id']) }}"
                                            class="inline-flex items-center justify-center rounded-lg bg-emerald-600 !px-3 !py-1.5 text-xs font-medium text-white transition hover:bg-emerald-700">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="!px-2 !py-8 text-center text-gray-500">No campaigns found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="xl:col-span-4 rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5 min-w-0 flex flex-col">
                <div class="!mb-3 flex items-center justify-between !gap-2">
                    <h2 class="text-lg font-semibold text-gray-900">Domain Categories</h2>
                </div>
                <div class="flex-1 overflow-x-auto rounded-xl border border-gray-200">
                    <table class="min-w-full w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-800 text-white">
                                @foreach (['S.No', 'Category', 'Domains', 'Action'] as $t)
                                    <th class="border-b border-slate-700 !px-2 !py-3 text-left font-medium">{{ $t }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($domainCategories as $index => $domain)
                                <tr class="border-b border-gray-100 hover:bg-gray-50/80">
                                    <td class="!px-2 !py-3">{{ $index + 1 }}</td>
                                    <td class="!px-2 !py-3">{{ $domain['domain'] }}</td>
                                    <td class="!px-2 !py-3 tabular-nums">{{ number_format($domain['quantity']) }}</td>
                                    <td class="!px-2 !py-2">
                                        <a href="{{ route('admin.domain.index', ['category_id' => $domain['id']]) }}"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-slate-800 text-white transition hover:bg-slate-700"
                                            title="View domains">
                                            <span class="material-symbols-outlined !text-[18px]">visibility</span>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="!px-2 !py-8 text-center text-gray-500">No domain categories found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="!mt-3 flex justify-end">
                    <a href="{{ route('admin.domain.category.index') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-900 !px-4 !py-2.5 text-sm font-medium text-white transition hover:bg-slate-800">
                        View more
                    </a>
                </div>
            </div>
        </div>

        {{-- Addons + shortcuts --}}
        <div class="grid grid-cols-1 !gap-3 lg:grid-cols-2 lg:!gap-4">
            @if ($admin->canCreateCampaigns())
                <section class="rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5">
                    <h2 class="text-lg font-semibold text-gray-900 !mb-3">Addons</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 !gap-2 sm:!gap-3">
                        <a href="{{ route('admin.sticky.campaign.index') }}"
                            class="flex items-center !gap-3 rounded-xl border border-gray-100 bg-gray-50/80 !p-3 sm:!p-4 transition hover:border-[var(--primary-color)]/30 hover:bg-white hover:shadow-sm">
                            <span class="material-symbols-outlined text-[var(--primary-color)]">push_pin</span>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">Sticky campaigns</p>
                                <p class="text-xs text-gray-500">Manage sticky post flows</p>
                            </div>
                        </a>
                        <a href="{{ route('admin.wp.schedule.campaign.index') }}"
                            class="flex items-center !gap-3 rounded-xl border border-gray-100 bg-gray-50/80 !p-3 sm:!p-4 transition hover:border-[var(--primary-color)]/30 hover:bg-white hover:shadow-sm">
                            <span class="material-symbols-outlined text-sky-600">calendar_clock</span>
                            <div>
                                <p class="font-semibold text-gray-900 text-sm">WP Scheduled</p>
                                <p class="text-xs text-gray-500">WordPress-native schedules</p>
                            </div>
                        </a>
                    </div>
                </section>
            @endif

            <section class="rounded-2xl border border-gray-200/80 bg-white !p-4 shadow-sm sm:!p-5 {{ $admin->canCreateCampaigns() ? '' : 'lg:col-span-2' }}">
                <h2 class="text-lg font-semibold text-gray-900 !mb-3">Shortcuts</h2>
                <div class="!space-y-1.5 text-sm">
                    @if ($admin->isSuperAdmin())
                        <a href="{{ route('admin.user.index') }}"
                            class="flex items-center justify-between rounded-lg !px-3 !py-2 text-gray-700 hover:bg-gray-50">
                            <span>Manage users</span>
                            <span class="material-symbols-outlined !text-lg text-gray-400">chevron_right</span>
                        </a>
                    @endif
                    <a href="{{ route('admin.domain.category.index') }}"
                        class="flex items-center justify-between rounded-lg !px-3 !py-2 text-gray-700 hover:bg-gray-50">
                        <span>Domain categories</span>
                        <span class="material-symbols-outlined !text-lg text-gray-400">chevron_right</span>
                    </a>
                    <a href="{{ route('admin.article.index') }}"
                        class="flex items-center justify-between rounded-lg !px-3 !py-2 text-gray-700 hover:bg-gray-50">
                        <span>All articles</span>
                        <span class="material-symbols-outlined !text-lg text-gray-400">chevron_right</span>
                    </a>
                    @if ($admin->canCreateCampaigns())
                        <a href="{{ route('admin.campaign.index') }}"
                            class="flex items-center justify-between rounded-lg !px-3 !py-2 text-gray-700 hover:bg-gray-50">
                            <span>Campaign reporting (PBN Post)</span>
                            <span class="material-symbols-outlined !text-lg text-gray-400">chevron_right</span>
                        </a>
                    @endif
                </div>
            </section>
        </div>

        {{-- Activity placeholder (no backend feed yet) --}}
        <section class="rounded-2xl border border-dashed border-gray-200 bg-gray-50/50 !p-5 sm:!p-6 text-center">
            <span class="material-symbols-outlined text-gray-400 text-3xl !mb-2">history</span>
            <p class="text-sm font-medium text-gray-700">Activity feed</p>
            <p class="text-xs text-gray-500 !mt-1 !max-w-md !mx-auto">Operational alerts and campaign events can be surfaced here in a future update. All existing notifications still work elsewhere in the app.</p>
        </section>

    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
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
                width: "100%",
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
                        chart: {
                            height: 280,
                        },
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
                            show: true,
                            fontSize: "24px",
                            fontWeight: 700,
                            color: "#ff4a17",
                            offsetY: 6,
                            formatter: () => articleUsage.percentage + "%",
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
            const mainChartEl = document.querySelector("#pbncampaigns-data");
            if (mainChartEl) {
                new ApexCharts(mainChartEl, options).render();
            }
            const articlesChartEl = document.querySelector("#articlesChart");
            if (articlesChartEl) {
                new ApexCharts(articlesChartEl, options2).render();
            }

            // Articles by Language chart
            const langChartEl = document.querySelector("#articlesByLanguageChart");
            if (langChartEl && articlesByLanguage.data.length > 0) {
                new ApexCharts(langChartEl, languageChartOptions).render();
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

                    if (type == 'dripfeed' || type == 'schedule_sticky') {
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
                if (!campaignTableBody) return;
                if (campaigns.length === 0) {
                    campaignTableBody.innerHTML = `
                <tr>
                    <td colspan="8" class="!px-2 !py-8 text-center text-gray-500">
                        No campaigns found
                    </td>
                </tr>
            `;
                    return;
                }

                campaignTableBody.innerHTML = campaigns.map((campaign, index) => `
            <tr class="border-b border-gray-100 hover:bg-gray-50/80">
                <td class="!px-2 !py-2.5">${index + 1}</td>
                <td class="!px-2 !py-2.5 max-w-[200px] truncate">${campaign.campaign}</td>
                <td class="!px-2 !py-2.5">${campaign.domain}</td>
                <td class="!px-2 !py-2.5">${campaign.quantity}</td>
                <td class="!px-2 !py-2.5">${campaign.links}</td>
                <td class="!px-2 !py-2.5">${campaign.keywords}</td>
                <td class="!px-2 !py-2.5 whitespace-nowrap">${campaign.date}</td>
                <td class="!px-2 !py-2.5">
                        <a href="${url}${campaign.id}" class="inline-flex items-center justify-center rounded-lg bg-emerald-600 !px-3 !py-1.5 text-xs font-medium text-white transition hover:bg-emerald-700">
                            View
                        </a>
                </td>
            </tr>
        `).join('');
            }
        });
    </script>
@endpush
