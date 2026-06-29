@extends('admin.layout.layout')

@section('title', 'View User - ' . $user->name)

@php
    $currentAdmin = Auth::guard('admin')->user();
    $selectedPeriodTotal = collect($creationSummary['rows'] ?? [])->sum('count');
@endphp

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="md:w-1/2 w-full flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">User Details</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.user.index') }}" class="breadcrumb-link">Users</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-current">{{ $user->name }}</span>
                    </div>
                </div>
            </div>
            <div class="md:w-1/2 w-full flex flex-wrap md:justify-end justify-start items-center gap-2 !mt-3 md:!mt-0">
                @if($currentAdmin->isSuperAdmin() || $currentAdmin->id === $user->id)
                    @if(!$user->isSuperAdmin() || $currentAdmin->id === $user->id)
                    <a href="{{ route('admin.user.edit', $user->id) }}"
                        class="flex items-center !px-3 !py-2 text-[14px] font-medium justify-center duration:300 bg-yellow-500 hover:bg-yellow-600 text-white rounded gap-1 shadow-sm">
                        <span class="material-symbols-outlined !text-[18px]">edit</span>
                        Edit User
                    </a>
                    @endif
                @endif
                <a href="{{ route('admin.user.index') }}"
                    class="flex items-center !px-3 !py-2 text-[14px] font-medium justify-center duration:300 bg-gray-500 hover:bg-gray-600 text-white rounded gap-1 shadow-sm">
                    <span class="material-symbols-outlined !text-[18px]">arrow_back</span>
                    Back
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- User Profile Card --}}
        <div class="lg:col-span-1">
            <div class="content-card user-profile-card">
                <div class="flex flex-col items-center text-center">
                    {{-- Avatar --}}
                    <div class="w-24 h-24 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white text-3xl font-bold !mb-4">
                        {{ strtoupper(substr($user->name, 0, 2)) }} {{-- HA --}}
                        
                    </div>
                    
                    <h2 class="text-xl font-semibold text-gray-800">{{ $user->name }}</h2>
                    <p class="text-sm text-gray-500 !mt-1">{{ $user->email }}</p>
                    
                    {{-- Role Badge --}}
                    <div class="!mt-3">
                        @php
                            $roleColors = [
                                0 => 'bg-green-100 text-green-700 border-green-300',
                                1 => 'bg-blue-100 text-blue-700 border-blue-300',
                                2 => 'bg-purple-100 text-purple-700 border-purple-300',
                            ];
                            $roleColor = $roleColors[$user->type] ?? 'bg-gray-100 text-gray-700 border-gray-300';
                        @endphp
                        <span class="!px-3 !py-1 text-sm font-medium rounded-full border {{ $roleColor }}">
                            {{ $user->getRoleName() }}
                        </span>
                    </div>
                    
                    {{-- Member Since --}}
                    <div class="!mt-4 text-sm text-gray-500">
                        <span class="material-symbols-outlined text-sm align-middle">calendar_today</span>
                        Member since {{ $user->created_at->format('M d, Y') }}
                    </div>
                </div>
                
                {{-- Quick Info --}}
                <div class="!mt-6 !pt-6 border-t border-gray-200 flex flex-col gap-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">User ID</span>
                        <span class="font-medium">#{{ $user->id }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Slug</span>
                        <span class="font-medium">{{ $user->slug }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500">Last Updated</span>
                        <span class="font-medium">{{ $user->updated_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Statistics --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Stats Grid --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="content-card !p-4 user-stat-card">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-purple-600">article</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['articles']) }}</p>
                            <p class="text-xs text-gray-500">Articles</p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card !p-4 user-stat-card">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-orange-600">language</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['domains']) }}</p>
                            <p class="text-xs text-gray-500">Domains</p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card !p-4 user-stat-card">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600">folder</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['domain_categories']) }}</p>
                            <p class="text-xs text-gray-500">Categories</p>
                        </div>
                    </div>
                </div>
                
                <div class="content-card !p-4 user-stat-card">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-600">campaign</span>
                        </div>
                        <div>
                            <p class="text-lg font-bold text-gray-800">{{ number_format($stats['campaigns']) }}</p>
                            <p class="text-xs text-gray-500">Campaigns</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Campaign Breakdown --}}
            <div class="content-card">
                @php
                    $campaignBreakdownTotal = (int) $stats['campaigns']
                        + (int) $stats['sidebar_campaigns']
                        + (int) $stats['hidden_link_campaigns']
                        + (int) $stats['schedule_campaigns'];
                @endphp
                <div class="flex items-center justify-between !mb-4">
                    <h3 class="text-lg font-semibold">Campaign Breakdown</h3>
                    <span class="inline-flex items-center !px-2.5 !py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">
                        {{ number_format($campaignBreakdownTotal) }} total
                    </span>
                </div>

                <div class="grid grid-cols-2 xl:grid-cols-5 gap-3">
                    <div class="campaign-breakdown-card border-blue-100 bg-blue-50/70">
                        <div class="flex items-center justify-between !mb-2">
                            <span class="material-symbols-outlined text-blue-600">article</span>
                            <span class="campaign-chip text-blue-700 bg-blue-100">PBN</span>
                        </div>
                        <p class="text-2xl font-bold text-blue-600">{{ number_format($stats['campaigns']) }}</p>
                        <p class="text-xs text-gray-600 !mt-1">PBN Posts</p>
                    </div>

                    <div class="campaign-breakdown-card border-green-100 bg-green-50/70">
                        <div class="flex items-center justify-between !mb-2">
                            <span class="material-symbols-outlined text-green-600">view_sidebar</span>
                            <span class="campaign-chip text-green-700 bg-green-100">Sidebar</span>
                        </div>
                        <p class="text-2xl font-bold text-green-600">{{ number_format($stats['sidebar_campaigns']) }}</p>
                        <p class="text-xs text-gray-600 !mt-1">Sidebar Campaigns</p>
                    </div>

                    <div class="campaign-breakdown-card border-purple-100 bg-purple-50/70">
                        <div class="flex items-center justify-between !mb-2">
                            <span class="material-symbols-outlined text-purple-600">link</span>
                            <span class="campaign-chip text-purple-700 bg-purple-100">Hidden</span>
                        </div>
                        <p class="text-2xl font-bold text-purple-600">{{ number_format($stats['hidden_link_campaigns']) }}</p>
                        <p class="text-xs text-gray-600 !mt-1">Hidden Links</p>
                    </div>

                    <div class="campaign-breakdown-card border-orange-100 bg-orange-50/70">
                        <div class="flex items-center justify-between !mb-2">
                            <span class="material-symbols-outlined text-orange-600">schedule</span>
                            <span class="campaign-chip text-orange-700 bg-orange-100">Scheduled</span>
                        </div>
                        <p class="text-2xl font-bold text-orange-600">{{ number_format($stats['schedule_campaigns']) }}</p>
                        <p class="text-xs text-gray-600 !mt-1">Schedule Campaigns</p>
                    </div>

                    <div class="campaign-breakdown-card border-gray-300 bg-gray-100/80">
                        <div class="flex items-center justify-between !mb-2">
                            <span class="material-symbols-outlined text-gray-700">monitoring</span>
                            <span class="campaign-chip text-gray-800 bg-white">All</span>
                        </div>
                        <p class="text-2xl font-bold text-gray-800">{{ number_format($campaignBreakdownTotal) }}</p>
                        <p class="text-xs text-gray-600 !mt-1">All Campaigns</p>
                    </div>
                </div>
            </div>

            {{-- Created Data Summary (Period Filter) --}}
            <div class="content-card">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 !mb-4">
                    <div>
                        <h3 class="text-lg font-semibold">User Created Data Summary</h3>
                        <div class="flex items-center gap-2">
                            <p class="text-sm text-gray-500">{{ $creationSummary['range_label'] }}</p>
                            <span class="inline-flex items-center !px-2 !py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                {{ number_format($selectedPeriodTotal) }} items
                            </span>
                        </div>
                    </div>

                    <form method="GET" action="{{ route('admin.user.show', $user->id) }}" class="flex items-center gap-2">
                        <label for="range" class="text-sm text-gray-600">Period</label>
                        <select id="range" name="range" onchange="this.form.submit()"
                            class="!px-3 !py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="day" {{ $range === 'day' ? 'selected' : '' }}>1 Day</option>
                            <option value="week" {{ $range === 'week' ? 'selected' : '' }}>1 Week</option>
                            <option value="year" {{ $range === 'year' ? 'selected' : '' }}>1 Year</option>
                        </select>
                    </form>
                </div>

                <div class="overflow-x-auto rounded-xl border border-gray-200">
                    <table class="w-full text-sm user-summary-table">
                        <thead class="bg-gray-50 text-gray-700">
                            <tr>
                                <th class="text-left !px-4 !py-3 font-semibold">Module</th>
                                <th class="text-right !px-4 !py-3 font-semibold">Created Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($creationSummary['rows'] as $row)
                                <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50/60' }} hover:bg-orange-50/40 transition">
                                    <td class="!px-4 !py-3 border-t border-gray-200">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                            <span class="text-gray-700">{{ $row['label'] }}</span>
                                        </div>
                                    </td>
                                    <td class="!px-4 !py-3 border-t border-gray-200 text-right">
                                        <span class="inline-flex items-center justify-center min-w-[52px] !px-2 !py-1 rounded bg-gray-100 text-gray-800 font-semibold">
                                            {{ number_format($row['count']) }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                            <tr class="bg-orange-50/70">
                                <td class="!px-4 !py-3 border-t border-orange-200 font-semibold text-orange-800">Total (Selected Period)</td>
                                <td class="!px-4 !py-3 border-t border-orange-200 text-right">
                                    <span class="inline-flex items-center justify-center min-w-[52px] !px-2 !py-1 rounded bg-orange-100 text-orange-700 font-bold">
                                        {{ number_format($selectedPeriodTotal) }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Total Summary --}}
            <div class="content-card bg-gradient-to-r from-orange-50 to-red-50">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">Total Activity</h3>
                        <p class="text-sm text-gray-500">All content created by this user</p>
                    </div>
                    <div class="text-right">
                        <p class="text-3xl font-bold text-orange-600">
                            {{ number_format($stats['articles'] + $stats['domains'] + $stats['campaigns'] + $stats['sidebar_campaigns'] + $stats['hidden_link_campaigns'] + $stats['schedule_campaigns']) }}
                        </p>
                        <p class="text-sm text-gray-500">Total Items</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('style')
<style>
    .user-profile-card,
    .user-stat-card {
        box-shadow: 0 6px 24px rgba(15, 23, 42, 0.05);
        border: 1px solid #edf0f4;
    }

    .user-stat-card {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .user-stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
    }

    .user-summary-table th,
    .user-summary-table td {
        border-color: #e5e7eb !important;
    }

    .campaign-breakdown-card {
        border-width: 1px;
        border-radius: 12px;
        padding: 14px;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .campaign-breakdown-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 20px rgba(15, 23, 42, 0.08);
    }

    .campaign-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 9999px;
        padding: 2px 8px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.2;
    }
</style>
@endpush
