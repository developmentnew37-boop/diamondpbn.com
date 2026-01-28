<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Login Admin Panel</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('build/assets/app-BMLFxF1u.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="{{ asset('build/assets/app-Cwyqw0uf.js') }}"></script>
    <script src="{{ asset('js/script.js') }}"></script>
</head>

<body>
    <div class="bg-gray-50">
        <div class="min-h-screen flex flex-col items-center justify-center py-6 px-4">
            <div class="max-w-[400px] w-full">
                <div class="!p-6 sm:!p-8 rounded bg-white border border-gray-200 ">
                    {{-- <h2 class="text-slate-900 text-center text-3xl font-semibold">Sign in</h2> --}}
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
                    <form class="!mt-2 !space-y-6" action="{{ route('admin.loggedin') }}" method="post">
                        @csrf
                        <div>
                            <label class="text-slate-900 text-sm  !mb-2 block">Email</label>
                            <div class="relative flex items-center">
                                <input name="email" type="text" required
                                    class="w-full text-slate-900 text-sm border border-slate-300 !px-4 !py-3 !pr-8 rounded-md outline-none focus:!border-[var(--primary-color)]"
                                    placeholder="Enter user name" />
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 absolute right-4"
                                    viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                    stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="2" y="4" width="20" height="16" rx="2" />
                                    <path d="m2 7 10 7 10-7" />
                                </svg>
                            </div>
                            @error('email')
                                <div class="w-full text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                        <div>
                            <label class="text-slate-900 text-sm  !mb-2 block">Password</label>
                            <div class="relative flex items-center">
                                <input name="password" type="password" required
                                    class="w-full text-slate-900 text-sm border border-slate-300 !px-4 !py-3 !pr-8 rounded-md outline-none focus:!border-[var(--primary-color)]"
                                    placeholder="Enter password" />
                            </div>
                            @error('password')
                                <div class="w-full text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center">
                                <input id="remember-me" name="remember" type="checkbox"
                                    class="h-4 w-4 shrink-0 text-[var(--primary-color)] focus:ring-blue-500 border-slate-300 rounded" />
                                <label for="remember-me" class="!ml-2 block text-sm  text-slate-900">
                                    Remember me
                                </label>
                            </div>
                            <div class="text-sm">
                                <a href="{{ route('admin.forgot') }}"
                                    class="text-[var(--primary-color)] hover:underline font-semibold">
                                    Forgot your password?
                                </a>
                            </div>
                        </div>

                        <div class="!mt-12">
                            <button
                                class="w-full !py-2 !px-4 text-[15px] font-medium tracking-wide rounded-md text-white bg-[var(--primary-color)] hover:opacity-70 focus:outline-none cursor-pointer">
                                Sign in
                            </button>
                        </div>
                        {{-- <p class="text-slate-900 text-sm !mt-6 text-center">Don't have an account? <a
                                href="javascript:void(0);"
                                class="text-[var(--primary-color)] hover:underline ml-1 whitespace-nowrap font-semibold">Register
                                here</a></p> --}}
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
