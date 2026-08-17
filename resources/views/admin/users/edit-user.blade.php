@extends('admin.layout.layout')

@section('title', 'Edit User - ' . $user->name)

@php
    $currentAdmin = Auth::guard('admin')->user();
@endphp

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Edit User</h2>
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
                        <span class="breadcrumb-current">Edit {{ $user->name }}</span>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                <a href="{{ route('admin.user.index') }}"
                    class="flex !p-2 !py-3 text-[14px] font-normal justify-center duration:300 bg-gray-500 hover:bg-gray-600 text-white rounded gap-1">
                    <span class="material-symbols-outlined !text-[18px]">arrow_back</span>
                    Back to Users
                </a>
            </div>
        </div>
    </div>

    {{-- Flash Messages --}}
    @if(session('cus__success'))
        <div class="bg-green-100 border border-green-400 text-green-700 !px-4 !py-3 rounded !mb-4">
            {{ session('cus__success') }}
        </div>
    @endif
    @if(session('cus__error'))
        <div class="bg-red-100 border border-red-400 text-red-700 !px-4 !py-3 rounded !mb-4">
            {{ session('cus__error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- User Info Card --}}
        <div class="lg:col-span-1">
            <div class="content-card">
                <div class="flex flex-col items-center text-center">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white text-2xl font-bold !mb-4">
                        {{ strtoupper(substr($user->name, 0, 2)) }}
                    </div>
                    <h3 class="text-lg font-semibold">{{ $user->name }}</h3>
                    <p class="text-sm text-gray-500">{{ $user->email }}</p>
                    <span class="!mt-3 !px-3 !py-2 text-xs font-medium rounded-full bg-gray-100">
                        {{ $user->getRoleName() }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Edit Form --}}
        <div class="lg:col-span-2">
            <div class="content-card">
                <h3 class="text-lg font-semibold !mb-6">Update User Information</h3>
                
                <form action="{{ route('admin.user.update', $user->id) }}" method="POST" class="">
                    @csrf
                    @method('PUT')
                    
                    <div class="flex flex-col gap-4 space-y-5">
                        {{-- Name --}}
                        <div class="flex flex-col gap-1 ">
                            <label class="block text-sm font-medium text-gray-700 !mb-2">Full Name</label>
                            <input type="text" name="name" value="{{ old('name', $user->name) }}" 
                                class="w-full !px-4 !py-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                placeholder="Enter full name">
                            @error('name')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        {{-- Email --}}
                        <div class="flex flex-col gap-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" value="{{ old('email', $user->email) }}" 
                                class="w-full !px-4 !py-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                placeholder="Enter email address">
                            @error('email')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        {{-- Role (Only for Super Admin editing others) --}}
                        @if($currentAdmin->isSuperAdmin() && $currentAdmin->id !== $user->id)
                        <div class="flex flex-col gap-1">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Role</label>
                            <select name="roles" 
                                class="w-full !px-4 !py-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent">
                                @foreach($roles as $role)
                                    @if($role->role_id != 0) {{-- Cannot assign Super Admin role --}}
                                    <option value="{{ $role->role_id }}" {{ $user->type == $role->role_id ? 'selected' : '' }}>
                                        {{ $role->role }}
                                    </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('roles')
                                <p class="text-red-500 text-sm !mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        @include('admin.users.partials.feature-permissions', [
                            'featurePermissions' => $featurePermissions ?? [],
                            'assignedPermissions' => $assignedPermissions ?? [],
                        ])
                        @endif
                        
                        <hr class="my-6">
                        
                        <div class="bg-yellow-50 border border-yellow-200 rounded !p-4 !mb-4">
                            <p class="text-sm text-yellow-800">
                                <strong>Password Change:</strong> Leave the password fields empty if you don't want to change the password.
                            </p>
                        </div>
                        
                        {{-- New Password --}}
                        <div class="flex flex-col gap-1">
                            <label class="block text-sm font-medium text-gray-700 !mb-2">New Password</label>
                            <input type="password" name="password" 
                                class="w-full !px-4 !py-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                placeholder="Enter new password (min 8 characters)">
                            @error('password')
                                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                        
                        {{-- Confirm Password --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 !mb-2">Confirm New Password</label>
                            <input type="password" name="password_confirmation" 
                                class="w-full !px-4 !py-3 border border-gray-300 rounded focus:outline-none focus:ring-2 focus:ring-orange-500 focus:border-transparent"
                                placeholder="Confirm new password">
                        </div>
                        
                        {{-- Submit Button --}}
                        <div class="flex gap-3 pt-4">
                            <button type="submit" 
                                class="flex-1 bg-orange-500 text-white !py-3 !px-6 rounded hover:bg-orange-600 transition font-medium">
                                Update User
                            </button>
                            <a href="{{ route('admin.user.index') }}" 
                                class="!px-6 !py-3 bg-gray-200 text-gray-700 rounded hover:bg-gray-300 transition text-center">
                                Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
