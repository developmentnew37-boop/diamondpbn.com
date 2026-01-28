@extends('admin.layout.layout')



@section('title', 'users')

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Users</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Create User</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                {{-- <a href="javascript:void(0)" id="make__admin__user"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">
                    Create User
                </a> --}}
            </div>
        </div>
    </div>
    <div class="w-full flex items-center justify-center">
        <form action="{{ route('admin.user.store') }}" method="post">
            @csrf
            <div class="content-card max-w-[600px] w-[480px] flex flex-col gap-1 ">
                <div class="flex items-start sm:items-center !py-3 !px-2 !mb-2 text-sm text-yellow-700 bg-yellow-100 border border-yellow-500 rounded-xl "
                    role="alert">
                    <svg class="w-4 h-4 me-2 shrink-0 mt-0.5 sm:mt-0" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 11h2v5m-2 0h4m-2.592-8.5h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    {{-- warning/Alert --}}
                    <p><span class="font-medium">Note!</span> Email should be Valid.</p>
                </div>
                {{-- ---------- --}}
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
                {{-- ---------- --}}
                <div class="w-full flex flex-col gap-2">
                    <label for="name"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">User
                        Name</label>
                    <input data-search-input type="text" id="name" name="name"
                        class="w-full  !px-4 !py-2 bg-gray-100 min-h-12 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)]   text-sm"
                        placeholder="Enter User name" value="">
                    @error('name')
                        <p class="!m-0 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                {{-- ---------- --}}
                <div class="w-full flex flex-col gap-2 !mt-2">
                    <label for="email"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">User
                        Email</label>
                    <input data-search-input type="text" id="email" name="email"
                        class="w-full  !px-4 !py-2 bg-gray-100 min-h-12 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)]   text-sm"
                        placeholder="Enter User name">
                    @error('email')
                        <p class="!m-0 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                {{-- ---------- --}}
                <div class="w-full flex flex-col gap-2 !mt-2">
                    <label for="roles"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">User
                        Role</label>
                    <select name="roles" id=""
                        class="bg-gray-100  border border-gray-200 !w-full !p-3 text-sm w-full rounded outline-none focus:border-orange-600">
                        <option value="">select</option>
                        @foreach ($roles as $indx => $role)
                            @if ($role->role_id != 0)
                                <option value="{{ $role->role_id }}">{{ $role['role'] }}</option>
                            @endif
                        @endforeach

                    </select>
                    @error('role')
                        <p class="!m-0 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                {{-- ---------- --}}
                <div class="w-full flex flex-col gap-2 !mt-2">
                    <label for="password"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                        Password</label>
                    <input data-search-input type="password" id="password" name="password"
                        class="w-full  !px-4 !py-2 bg-gray-100 min-h-12 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)]   text-sm"
                        placeholder="password">
                    @error('password')
                        <p class="!m-0 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                {{-- ---------- --}}
                <div class="w-full flex flex-col gap-2 !mt-2">
                    <label for="password_confirmation"
                        class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                        Confirm Password</label>
                    <input data-search-input type="password" id="password_confirmation" name="password_confirmation"
                        class="w-full  !px-4 !py-2 bg-gray-100 min-h-12 border border-gray-200 rounded outline-none focus:border-[var(--primary-color)]   text-sm"
                        placeholder="password">
                </div>
                {{-- ---------- --}}
                <div class="w-full flex items-center !mt-4">
                    <button type="submit"
                        class="w-full flex !p-3 !py-4 text-sm font-normal justify-center duration:600 transition-all bg-[var(--primary-color)] hover:bg-[var(--primary-color)]/80 text-white rounded cursor-pointer">
                        Create User</button>
                </div>
            </div>
        </form>

    @endsection
