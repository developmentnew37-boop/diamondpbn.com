@extends('admin.layout.layout')

@section('title', 'Test')

@section('main-content')

    {{-- <h2 class="bg-green-500">Hello this is test section</h2> --}}

    <div class="w-full flex flex-wrap gap-8 justify-center  overflow-hidden ">
        <div class="w-full flex flex-wrap gap-4 justify-center overflow-hidden ">
                <div class="max-w-7xl w-[74%] mx-auto content-card ">
                    {{-- <div class="content-card"> --}}
                    <div class="px-6 pt-6 flex justify-between">
                        <h2 class="text-lg font-semibold">PBN Orders Campaigns Data</h2>
                    </div>
                  
                    {{-- </div> --}}
                </div>
                <div class="w-1/4 max-w-[600px] flex flex-col content-card">
                    <div class="flex flex-col gap-5 mb-4 w-full">
                        <h2 class="text-lg font-semibold">Domains Categories</h2>
                    </div>

                </div>
            </div>
       
    </div>


@endsection
