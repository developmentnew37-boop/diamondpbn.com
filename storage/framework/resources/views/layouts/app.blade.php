@php
    $siteSettings = \App\Models\Setting::getSettings();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', $siteSettings->site_title)</title>
        @if($siteSettings->meta_title)
        <meta name="title" content="{{ $siteSettings->meta_title }}">
        @endif
        @if($siteSettings->meta_description)
        <meta name="description" content="{{ $siteSettings->meta_description }}">
        @endif
        @if($siteSettings->meta_keywords)
        <meta name="keywords" content="{{ $siteSettings->meta_keywords }}">
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet" />

        <link rel="stylesheet" href="{{ asset('build/assets/app-CV8RmTp3.css') }}">
        <script src="{{ asset('build/assets/app-CagNDbK6.js') }}"></script>
    </head>
    <body class="font-sans antialiased" x-data="sidebar()" style="background: {{ $siteSettings->theme_bg_gradient }}; background-attachment: fixed;">
        <div class="min-h-screen flex relative">
            {{-- Mobile overlay --}}
            <div x-show="sidebarOpen"
                 x-transition
                 @click="sidebarOpen = false"
                 class="fixed inset-0 z-40 bg-black/50 lg:hidden"
                 x-cloak></div>
            @include('layouts.sidebar')

            <div class="flex-1 flex flex-col min-w-0 ">
                {{-- Top bar for mobile --}}
                <header class="sticky top-0 z-40 bg-white/95 backdrop-blur border-b border-gray-200 lg:hidden">
                    <div class="flex items-center justify-between h-16 px-4">
                        <button @click="sidebarOpen = !sidebarOpen" class="p-2 rounded-md text-gray-500 hover:bg-gray-100">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                            </svg>
                        </button>
                        <span class="font-semibold text-gray-800">{{ $siteSettings->site_title }}</span>
                        <div class="w-10"></div>
                    </div>
                </header>

                @isset($header)
                    <header class="flex items-center h-16 px-4 sm:px-6 lg:px-8 bg-white/90 backdrop-blur border-b border-gray-200/80 shrink-0">
                        {{ $header }}
                    </header>
                @endisset

                <main class="flex-1 p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @stack('scripts')
    </body>
</html>
