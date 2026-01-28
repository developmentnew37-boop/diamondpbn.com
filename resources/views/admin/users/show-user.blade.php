@extends('admin.layout.layout')

@section('title', 'View User - ' . $user->name)

@php
    $currentAdmin = Auth::guard('admin')->user();
@endphp

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
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
            <div class="w-1/2 flex flex-wrap justify-end items-center gap-2">
                @if($currentAdmin->isSuperAdmin() || $currentAdmin->id === $user->id)
                    @if(!$user->isSuperAdmin() || $currentAdmin->id === $user->id)
                    <a href="{{ route('admin.user.edit', $user->id) }}"
                        class="flex !p-2 !py-3 text-[14px] font-normal justify-center duration:300 bg-yellow-500 hover:bg-yellow-600 text-white rounded gap-1">
                        <span class="material-symbols-outlined !text-[18px]">edit</span>
                        Edit User
                    </a>
                    @endif
                @endif
                <a href="{{ route('admin.user.index') }}"
                    class="flex !p-2 !py-3 text-[14px] font-normal justify-center duration:300 bg-gray-500 hover:bg-gray-600 text-white rounded gap-1">
                    <span class="material-symbols-outlined !text-[18px]">arrow_back</span>
                    Back
                </a>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- User Profile Card --}}
        <div class="lg:col-span-1">
            <div class="content-card">
                <div class="flex flex-col items-center text-center">
                    {{-- Avatar --}}
                    <div class="w-24 h-24 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white text-3xl font-bold !mb-4">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
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
                <div class="!mt-6 !pt-6 border-t border-gray-200 flex flex-col gap-3 space-y-3">
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
                <div class="content-card !p-4">
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
                
                <div class="content-card !p-4">
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
                
                <div class="content-card !p-4">
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
                
                <div class="content-card !p-4">
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
                <h3 class="text-lg font-semibold !mb-4">Campaign Breakdown</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-blue-600">{{ $stats['campaigns'] }}</p>
                        <p class="text-sm text-gray-600">PBN Posts</p>
                    </div>
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-green-600">{{ $stats['sidebar_campaigns'] }}</p>
                        <p class="text-sm text-gray-600">Sidebar</p>
                    </div>
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-purple-600">{{ $stats['hidden_link_campaigns'] }}</p>
                        <p class="text-sm text-gray-600">Hidden Links</p>
                    </div>
                    <div class="text-center !p-4 bg-gray-50 rounded-lg">
                        <p class="text-2xl font-bold text-orange-600">{{ $stats['schedule_campaigns'] }}</p>
                        <p class="text-sm text-gray-600">Scheduled</p>
                    </div>
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
