<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Verify Opt</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet" />
    @include('partials.build-assets')
    @include('partials.auth-secret-assets')
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
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
            class="w-full max-w-md mx-auto bg-white rounded-xl !py-6 sm:!py-8 !px-4 sm:!px-6 text-lg flex flex-col gap-3 shadow-sm border border-gray-100"
            style="width:100%; max-width:420px;">
            <div class="w-full flex justify-center">
                <a href="javascript:void(0)"><img src="{{ asset('logo.png') }}" alt="logo"
                        class="w-40 !mb-2  block" />
                </a>
            </div>

            @if (session()->has('cus__error'))
                <div class="!p-4  text-sm rounded bg-red-100 text-red-700 w-full" role="alert">
                    <span class="font-medium"> {{ session('cus__error') }}</span>

                </div>
            @endif

            <form action="{{ route('admin.verify.otp') }}" method="post" class="w-full">
                @csrf
                <div class="w-full flex flex-col gap-1 !p-2 !mt-1">
                    <div class="w-full !mb-2">
                        <label for="otp" class="text-sm !mb-2 block text-slate-700">Verification code</label>
                        @include('partials.auth-secret-input', [
                            'name' => 'otp',
                            'id' => 'otp',
                            'type' => 'password',
                            'placeholder' => 'Enter OTP Code',
                            'inputmode' => 'numeric',
                            'autocomplete' => 'one-time-code',
                        ])
                    </div>
                    @error('otp')
                        <div class="w-full flex flex-wrap text-sm text-red-600 !mb-2">
                            <span>{{ $message }}</span>
                        </div>
                    @enderror

                    <button type="submit"
                        class="w-full !py-3 cursor-pointer !px-3 rounded-lg bg-[var(--primary-color)] text-white capitalize">
                        Verify Otp
                    </button>
                </div>
                @error('Otp')
                    <div class="!p-4 !mb-4 text-sm text-yellow-800 rounded-lg bg-yellow-50 dark:bg-gray-800 dark:text-yellow-300"
                        role="alert">
                        <span class="font-medium">Warning!</span>
                        {{ $message }}
                    </div>
                @enderror
            </form>

        </div>
    </div>



    {{-- <script src="{{ asset('build/assets/js/script.js') }}"></script> --}}

</body>

</html>
