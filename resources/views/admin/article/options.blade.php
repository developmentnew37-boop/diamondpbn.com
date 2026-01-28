@extends('admin.layout.layout')

@section('title', 'Pick Your Article Creation Option')

@push('style')
@endpush
@section('main-content')


    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-full flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Dashboards</h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="{{ route('admin.articles.opt') }}" class="breadcrumb-link">Articles Options</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <div class="w-full content-card">
        <h2 class="text-xl font-semibold">
            Create Content: Choose Your Starting Option below
        </h2>
        <div class="w-full flex flex-wrap gap-10 !mt-10">
            <label for="article_opt_1"
                class="pointer-events-none opacity-60 select-label flex flex-col gap-3 group items-center relative overflow-hidden justify-center bg-gray-50 border w-[225px] border-gray-200 mt-4 rounded-lg !p-6 cursor-pointer duration-300 transition-all hover:border-[var(--primary-color)]">
                <input type="radio" name="article_opt" class="article_radio_opt" id="article_opt_1" value='ai generation'
                    hidden>
                <img src="{{ asset('images/ai.png') }}" alt="" srcset="">
                <p
                    class="text-sm bg-[var(--primary-color)] text-white !p-2 rounded absolute top-full left-1/2 -translate-x-1/2 duration-300 opacity-0 group-hover:top-1/2  group-hover:-translate-y-1/2 group-hover:opacity-100 whitespace-nowrap ">
                    using Airtificial intelligence</p>
                <span
                    class="w-7 h-7 flex items-center justify-center bg-[var(--primary-color)] text-white rounded-full absolute left-1 duration-300 transition-all top-1 opacity-0 group-hover:opacity-100 check-span">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width='18' stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>

                </span>
            </label>
            <label for="article_opt_2"
                class="select-label flex flex-col gap-3 items-center group relative overflow-hidden justify-center bg-gray-50 border w-[225px] border-gray-200 mt-4 rounded-lg !p-6 cursor-pointer duration-300 transition-all hover:border-[var(--primary-color)]">
                <input type="radio" name="article_opt" class="article_radio_opt" id="article_opt_2" value="manual_create"
                    hidden>
                <img src="{{ asset('images/manual.png') }}" alt="" srcset="">
                <p
                    class="text-sm bg-[var(--primary-color)] text-white !p-2 rounded absolute top-full left-1/2 -translate-x-1/2 duration-300 opacity-0 group-hover:top-1/2  group-hover:-translate-y-1/2 group-hover:opacity-100 whitespace-nowrap ">
                    manual creation</p>
                <span
                    class="w-7 h-7 flex items-center justify-center bg-[var(--primary-color)] text-white rounded-full absolute left-1 duration-300 transition-all top-1 opacity-0 group-hover:opacity-100 check-span">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width='18' stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>

                </span>
            </label>
            <label for="article_opt_3"
                class=" select-label flex flex-col gap-3 items-center justify-center group relative overflow-hidden bg-gray-50 border w-[225px] border-gray-200 mt-4 rounded-lg !p-6 cursor-pointer duration-300 transition-all hover:border-[var(--primary-color)]">
                <input type="radio" name="article_opt" class="article_radio_opt" id="article_opt_3" value="bulk_upload"
                    hidden>
                <img src="{{ asset('images/bulk-1.png') }}" alt="" srcset="">
                <p
                    class="text-sm bg-[var(--primary-color)] text-white !p-2 rounded absolute top-full left-1/2 -translate-x-1/2 duration-300 opacity-0 group-hover:top-1/2  group-hover:-translate-y-1/2 group-hover:opacity-100 whitespace-nowrap ">
                    Bulk upload Article Docxs/Pdfs</p>
                <span
                    class="w-7 h-7 flex items-center justify-center bg-[var(--primary-color)] text-white rounded-full absolute left-1 duration-300 transition-all top-1 opacity-0 group-hover:opacity-100 check-span">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width='18' stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>

                </span>
            </label>
        </div>
        <div class="w-full flex !mt-6">
            <a href="javascript:void(0)" id="redirect_btn"
                class="flex !p-2 !py-3 text-sm font-normal  justify-center duration:300 transition-all bg-black hover:bg-[var(--primary-color)] text-white rounded ">
                Create Content</a>

        </div>
    </div>


@endsection

@push('scripts')

<script type="module" src="{{ asset('js/general.js') }}"></script>

@endpush
