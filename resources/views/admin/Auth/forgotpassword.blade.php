<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('build/assets/app-BMLFxF1u.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="{{ asset('build/assets/app-Cwyqw0uf.js') }}"></script>
    <script src="{{ asset('js/script.js') }}"></script>
    {{-- <link rel="stylesheet" href="{{ asset('build/assets/app-Ci3NzweI.css') }}"> --}}

</head>

<body>
    <!-- Sidebar Overlay for Mobile -->
    {{-- <div class="sidebar-overlay" id="sidebarOverlay"></div> --}}

    <!-- Sidebar -->
    {{-- @include('include.sidebar') --}}

    <!-- Main Wrapper -->

    <div class="w-full h-[100vh] bg-[#F4F7FE] flex items-center justify-center">
        <div class="w-[30%] max-w-[400px] bg-white rounded-xl !py-6 !px-2 text-lg flex flex-col justify-center gap-3">
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
            <form action="{{ route('admin.forgot.post') }}" method="post">
                @csrf
                <div class="w-full flex flex-col gap-1 !p-2 !mt-1">
                    <div class="w-full relative !mb-2">

                        <input type="text" name="email"
                            class="w-full rounded !py-3 !px-2 text-sm outline-0 themeFont border border-gray-300 focus:border-[var(--primary-color)]"
                            placeholder="Enter Registered Email">
                    </div>
                    @error('email')
                        <div class="w-full flex flex-wrap text-sm text-red-600 !mb-2">
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <input type='submit' value='update password'
                        class='w-full !py-3 ! cursor-pointer px-2 rounded-lg bg-[var(--primary-color)] text-white capitalize'>
                </div>
            </form>

        </div>
    </div>



</body>

</html>
