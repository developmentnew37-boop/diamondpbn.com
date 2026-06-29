<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @include('partials.build-assets')
    <script src="{{ asset('js/script.js') }}" defer></script>

</head>

<body>
    <!-- Sidebar Overlay for Mobile -->
    {{-- <div class="sidebar-overlay" id="sidebarOverlay"></div> --}}

    <!-- Sidebar -->
    {{-- @include('include.sidebar') --}}

    <!-- Main Wrapper -->

    <div class="w-full min-h-screen bg-[#F4F7FE] flex items-center justify-center !px-3 sm:!px-6 !py-10">
        <div
            class="w-full max-w-[420px] bg-white rounded-xl !py-6 sm:!py-8 !px-4 sm:!px-6 text-lg flex flex-col justify-center gap-3 shadow-sm border border-gray-100">
            <div class="w-full flex justify-center">
                <a href="javascript:void(0)"><img src="{{ asset('logo.png') }}" alt="logo"
                        class="w-40 !mb-2  block" />
                </a>
            </div>

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
            {{--  --}}
            <form action="{{ route('admin.forgot.post') }}" method="post" class="w-full">
                @csrf
                <div class="w-full flex flex-col gap-1 !p-2 !mt-1">
                    <div class="w-full relative !mb-2">

                        <input type="email" name="email" autocomplete="email" inputmode="email"
                            class="w-full rounded !py-3 !px-3 text-sm outline-0 themeFont border border-gray-300 focus:border-[var(--primary-color)]"
                            placeholder="Enter Registered Email">
                    </div>
                    @error('email')
                        <div class="w-full flex flex-wrap text-sm text-red-600 !mb-2">
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <input type='submit' value='Update password'
                        class='w-full !py-3 cursor-pointer !px-3 rounded-lg bg-[var(--primary-color)] text-white capitalize'>
                </div>
            </form>

        </div>
    </div>



</body>

</html>
