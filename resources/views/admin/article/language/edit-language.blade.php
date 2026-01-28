@extends('admin.layout.layout')

@section('title', 'Edit')


@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        {{-- {{ route('article') }} --}}
                        <a href="" class="breadcrumb-link">Articles</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        {{-- {{ route('category') }} --}}
                        <a href="#" class="breadcrumb-link">Category</a>
                    </div>
                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
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

    <div class="w-full flex flex-wrap gap-8 !mt-6 justify-center">
        <div class="w-full flex flex-wrap gap-4 justify-center ">
            {{-- sec 1 max-w-[600px] --}}
            <div class="w-1/3  flex flex-col content-card">
                <div class="flex flex-col gap-5 mb-4 w-full ">
                    <h2 class="text-xl font-semibold  capitalize">Add Language here</h2>
                    <form action="{{ route('admin.articles.language.update',$language->id) }}" method="post" class="w-full flex-col">
                        @csrf
                        @method('PUT')
                        <div class="w-full flex flex-col gap-5">
                            <div class="w-full flex flex-col gap-3 p-2">
                                <label for="language_title"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">name
                                </label>
                                <input type="text" name="name" placeholder="Add language here" id="language_title" value="{{ $language->name }}"
                                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                                @error('name')
                                    <p class="text-red-400 text-sm">{{ $message }}</p>
                                @enderror

                            </div>


                            <div class="w-full flex items-center p-2">
                                <button type="submit"
                                    class="flex !p-2 !py-3 text-sm font-normal justify-center duration:600 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                    Add Language</button>
                            </div>


                        </div>

                    </form>
                </div>

            </div>

        </div>


    </div>

    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>

@endsection
