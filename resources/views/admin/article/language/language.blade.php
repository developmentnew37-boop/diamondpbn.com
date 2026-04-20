@extends('admin.layout.layout')

@section('title', 'Article Languages')

@push('style')
    <style>
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-menu-animate {
            animation: slideDown 0.2s ease-out;
        }
    </style>
@endpush

@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <div class="min-w-0">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.article.index') }}" class="breadcrumb-link">Articles</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Language</span>
                    </div>
                </div>
            </div>
        </div>
    </div>



    @if (session()->has('cus__success') || session()->has('cus__error'))
        <div class="w-full flex flex-col gap-1 !px-3">
            @if (session()->has('cus__success'))
                <div class="w-full content-card">
                    <div class="!p-4  text-sm rounded bg-green-100 text-green-700 w-full" role="alert">
                        <span class="font-medium"> {{ session('cus__success') }}</span>
                    </div>
                </div>
            @endif
            @if (session()->has('cus__error'))
                <div class="w-full content-card">
                    <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                        <span class="font-medium"> {{ session('cus__error') }}</span>
                    </div>
                </div>
            @endif

        </div>
    @endif

    <div class="w-full flex flex-col gap-6 lg:flex-row lg:items-start lg:gap-6 !mt-6 min-w-0">
        <div class="w-full lg:w-96 lg:flex-shrink-0 flex flex-col content-card min-w-0">
            <div class="flex flex-col gap-5 mb-4 w-full text-left">
                <h2 class="text-xl font-semibold capitalize">Add Language here</h2>
                <form action="{{ route('admin.articles.language.store') }}" method="post" class="w-full flex-col">
                    @csrf
                    <div class="w-full flex flex-col gap-5">
                        <div class="w-full flex flex-col gap-3 p-2">
                            <label for="language_name"
                                class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">name
                            </label>
                            <input type="text" name="name" placeholder="Add language here" id="language_name"
                                class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                            @error('name')
                                <p class="text-red-400 text-sm">{{ $message }}</p>
                            @enderror

                        </div>

                        <div class="w-full flex items-stretch sm:items-center p-2">
                            <button type="submit"
                                class="w-full sm:w-auto flex !p-2 !py-3 text-sm font-normal justify-center duration:600 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                Add Language</button>
                        </div>

                    </div>

                </form>
            </div>
        </div>

        <div class="w-full lg:flex-1 lg:min-w-0 content-card min-w-0">
            <div class="w-full flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div class="w-full sm:flex-1 sm:min-w-0 flex justify-start items-center gap-2">
                    <h2
                        class="text-xl bg-[var(--primary-color)] text-white !p-2 rounded font-semibold capitalize w-fit max-w-full">
                        All Languages data <span class="material-symbols-outlined !text-sm align-middle">
                            arrow_cool_down
                        </span></h2>
                </div>
                <div class="w-full sm:w-auto sm:max-w-md flex justify-start sm:justify-end items-center gap-2 min-w-0">
                    <form method="GET" action="{{ url()->current() }}"
                        class="relative w-full min-w-0 overflow-hidden max-h-12">

                        @foreach (request()->except('search') as $key => $value)
                            @continue(is_array($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <input type="search" name="search" placeholder="search here" id="search_category"
                            value="{{ request('search') }}"
                            class="bg-gray-100 shadow border border-gray-200 !p-3 !pr-[50px] max-h-12 text-sm w-full rounded outline-none">

                        <button type="submit"
                            class="w-12 h-12 flex items-center justify-center bg-[var(--sidebar-bg)] absolute top-0 right-0 rounded-r">
                            <svg class="w-5 h-5 text-white !text-sm" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </button>

                    </form>
                </div>
            </div>

            <div class="overflow-x-auto !mt-6 w-full max-w-full min-w-0 -mx-1 px-1 sm:mx-0 sm:px-0">
                <table
                    class="w-full min-w-[640px] border border-gray-200 border-collapse text-sm whitespace-nowrap searchable-table">
                    <thead>
                        <tr class="bg-black text-white active-border-color ">
                            @php
                                $tHead2 = ['sno', 'name', 'user', 'Date', 'Action'];
                            @endphp
                            @foreach ($tHead2 as $t)
                                <th class="border border-gray-200 font-sans !font-normal !px-2 !py-3 capitilize text-left">
                                    {{ $t }}
                                </th>
                            @endforeach
                        </tr>

                    </thead>
                    <tbody>
                        @forelse ($languages as $index => $language)
                            <tr class="hover:bg-gray-50">
                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    {{ $offset + $index + 1 }}
                                </td>

                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    {{ $language->name }}
                                </td>

                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    {{ $language->admin->name ?? 'N/A' }}
                                </td>

                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    {{ $language->created_at->format('d-m-Y') }}
                                </td>

                                <td class="border border-gray-200 font-sans !px-2 !py-2">
                                    <div class="flex flex-wrap gap-2 justify-center">
                                        <a href="{{ route('admin.articles.language.edit', $language->id) }}"
                                            class="bg-yellow-500 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-yellow-700">
                                            <span
                                                class="material-symbols-outlined !text-[16px] text-white">edit_square</span>
                                        </a>

                                        <form action="{{ route('admin.articles.language.destroy', $language->id) }}"
                                            method="POST"
                                            onsubmit="return confirm('Are you sure you want to delete this language?');"
                                            class="inline">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                class="bg-red-600 flex items-center justify-center rounded-full w-8 h-8 duration-500 hover:bg-red-700 cursor-pointer">
                                                <span
                                                    class="material-symbols-outlined !text-[16px] text-white">delete</span>
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center !py-3 bg-gray-100">No languages added</td>
                            </tr>
                        @endforelse

                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>
@endsection
