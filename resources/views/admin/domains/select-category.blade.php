@extends('admin.layout.layout')



@section('title', 'Select Domains')


@section('main-content')

    {{-- bread-crumbs --}}
    <div class="page-header">
        <div class="w-full flex flex-wrap items-center">
            <div class="w-1/2 flex flex-col gap-2 flex-wrap">
                <h2 class="page-title">Edit Domains </h2>
                <div class="breadcrumb">
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Dashboard</a>
                        <span>›</span>
                    </div>
                    <div class="breadcrumb-item">
                        <a href="#" class="breadcrumb-link">Select Domain Category</a>
                        <span>›</span>
                    </div>

                </div>
            </div>
            <div class="w-1/2 flex flex-wrap justify-end items-center">
                {{-- <a href="javascript:void(0)" id="make_article_Set"
                    class="flex !p-2 !py-3 text-[16px] font-normal w-1/5 justify-center duration:300 bg-black hover:bg-[var(--primary-color)] text-white rounded ">+
                    Add Article Set
                </a> --}}
            </div>
        </div>
    </div>

    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}


    <div class="w-full  flex items-center justify-center">
        <div class="w-[30%] max-w-[400px] bg-white rounded-xl !py-6 !px-2 text-lg flex flex-col justify-center gap-3">

            {{-- <div class="w-full flex justify-center !p-2">
                <img src="{{ asset('assets/Socialz-Vision-logo-fi.webp') }}" alt="logo" class="w-1/5">
            </div> --}}
            <div class="w-full flex flex-col justify-center text-center !px-2 gap-2">
                <h2 class="text-2xl font-medium border-b border-dashed border-gray-300 text-[var(--primary-color)]  w-full !px-3 !py-2 !mx-auto rounded">Select Category</h2>
             
            </div>
            <form action="{{ route('admin.redirect.to.list')}}" method="post">
                @csrf
                <div class="w-full flex flex-col gap-1 !p-2 !mt-1">
                    <div class="w-full relative !mb-2">

                        <select name="category" id=""
                            class="w-full rounded !p-3 text-sm outline-0 themeFont border border-gray-300 focus:border-[var(--primary-color)]">
                            <option value="">select category</option>
                            @if ($domainCategories && count($domainCategories) > 0)
                                @foreach ($domainCategories as $domain)
                                    <option value="{{ $domain->id }}">{{ $domain->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    @error('category')
                        <div class="w-full flex flex-wrap text-sm text-red-600 !mb-2">
                            <span>{{ $message }}</span>
                        </div>
                    @enderror


                    <input type='submit' value='Redirect'
                        class='w-full !py-3 ! cursor-pointer px-2 rounded-lg bg-[var(--primary-color)] text-white capitalize'>
                </div>


            </form>

        </div>
    </div>



@endsection



@push('scripts')
            <script src="{{ asset('js/updated_dynamic_dropdown.js') }}"></script>
    
@endpush
