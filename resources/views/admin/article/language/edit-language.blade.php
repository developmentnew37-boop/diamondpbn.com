@extends('admin.layout.layout')

@section('title', 'Edit')


@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header w-full max-w-full min-w-0">
        <div class="w-full flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
            <div class="min-w-0">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb flex-wrap gap-y-1">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.article.index') }}" class="breadcrumb-link">Articles</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.articles.language.index') }}" class="breadcrumb-link">Language</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <span class="breadcrumb-link">Edit</span>
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

    <div class="w-full !mt-6 min-w-0">
        <div class="w-full max-w-3xl xl:max-w-4xl mx-auto content-card min-w-0">
            <div class="flex flex-col gap-5 mb-4 w-full text-left px-1 sm:px-0">
                <h2 class="text-xl font-semibold capitalize">Edit language</h2>
                <form action="{{ route('admin.articles.language.update', $language->id) }}" method="post" class="w-full flex-col">
                        @csrf
                        @method('PUT')
                        <div class="w-full flex flex-col gap-5">
                            <div class="w-full flex flex-col gap-3 p-2">
                                <label for="language_title"
                                    class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">name
                                </label>
                                <input type="text" name="name" placeholder="Language name" id="language_title" value="{{ $language->name }}"
                                    class="bg-gray-100 border border-gray-200 !p-3 text-sm w-full rounded outline-none focus:border-orange-600">

                                @error('name')
                                    <p class="text-red-400 text-sm">{{ $message }}</p>
                                @enderror

                            </div>


                            <div class="w-full flex items-stretch sm:items-center p-2">
                                <button type="submit"
                                    class="w-full sm:w-auto flex !p-2 !py-3 text-sm font-normal justify-center duration:600 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded cursor-pointer">
                                    Update language</button>
                            </div>


                        </div>

                </form>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    <script src="{{ asset('js/search-items.js') }}"></script>

@endsection
