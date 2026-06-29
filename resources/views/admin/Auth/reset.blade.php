<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @include('partials.build-assets')
    @include('partials.auth-secret-assets')
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

            <form action="{{ route('admin.reset') }}" method="post" class="w-full">
                @csrf
                <div class="w-full flex flex-col gap-1 !p-2 !mt-1">
                    <div class="w-full flex flex-col gap-1 !mb-2">
                        <label for="password"
                            class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                            Password
                        </label>
                        @include('partials.auth-secret-input', [
                            'name' => 'password',
                            'id' => 'password',
                            'placeholder' => 'Enter Reset Password',
                            'required' => true,
                            'autocomplete' => 'new-password',
                        ])
                        @error('password')
                            <div class="w-full flex flex-wrap text-sm text-red-600 !mb-2">
                                <span>{{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                    <div class="w-full flex flex-col gap-1 !mb-2">
                        <label for="password_confirmation"
                            class="text-sm flex items-center after:content-['*'] after:mt-1 after:ml-1 after:text-[var(--primary-color)]">
                            Confirmation
                        </label>
                        @include('partials.auth-secret-input', [
                            'name' => 'password_confirmation',
                            'id' => 'password_confirmation',
                            'placeholder' => 'Enter Reset Password Again',
                            'required' => true,
                            'autocomplete' => 'new-password',
                        ])
                        @error('password_confirmation')
                            <div class="w-full flex flex-wrap text-sm text-red-600 !mb-2">
                                <span>{{ $message }}</span>
                            </div>
                        @enderror
                    </div>
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="email" value="{{ $email }}">

                    <input type='submit' value='Reset password'
                        class='w-full !py-3 cursor-pointer !px-3 rounded-lg bg-[var(--primary-color)] text-white capitalize'>
                </div>
            </form>

        </div>
    </div>



    {{-- <script src="{{ asset('build/assets/js/script.js') }}"></script> --}}

</body>

</html>
