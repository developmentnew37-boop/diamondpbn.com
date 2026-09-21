@php
    $siteSettings = \App\Models\Setting::getSettings();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', $siteSettings->meta_title ?: $siteSettings->site_title)</title>
    @if ($siteSettings->meta_title ?? null)
        <meta name="title" content="{{ $siteSettings->meta_title }}">
    @endif
    @if ($siteSettings->meta_description ?? null)
        <meta name="description" content="{{ $siteSettings->meta_description }}">
    @endif
    @if ($siteSettings->meta_keywords ?? null)
        <meta name="keywords" content="{{ $siteSettings->meta_keywords }}">
    @endif

    
    <link rel="stylesheet" href="{{ asset('build/assets/app-CV8RmTp3.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
    <script src="{{ asset('build/assets/app-CagNDbK6.js') }}" defer></script>
    @stack('style')

  <style>
    html, body, body * {
        -webkit-user-select: none !important;
        -moz-user-select: none !important;
        -ms-user-select: none !important;
        user-select: none !important;
    }

    img, a {
        -webkit-user-drag: none !important;
        user-drag: none !important;
    }
</style>

</head>

<body class="min-h-screen m-0 font-sans leading-relaxed text-gray-800"
    style="background: {{ $siteSettings->theme_bg_gradient }}; background-attachment: fixed;">
    <div
        class="max-w-[1300px] mx-[30px] my-8 md:mx-auto bg-white rounded-[20px] shadow-[0_20px_40px_rgba(0,0,0,0.2)] flex flex-col overflow-hidden animate-fade-in-up">
        {{-- Header --}}
        <header class="relative overflow-hidden text-white" style="background: {{ $siteSettings->theme_gradient_css }}">
            <div class="absolute inset-0 opacity-30"
                style="background-image: url(&quot;data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E&quot;);">
            </div>
            <div class="relative z-10 py-10 px-6 md:px-10 text-center">
                @php
                    $headerTitle = $siteSettings->index_heading ?? $siteSettings->site_title;
                    $headerDesc = $siteSettings->index_description ?? $siteSettings->site_description;
                @endphp
                <div class="text-xl md:text-2xl font-bold flex items-center justify-center gap-3 mb-3 tracking-tight max-w-[800px] mx-auto leading-10"
                    title="{{ $headerTitle }}">
                    <span class="text-4xl animate-bounce">📍</span>
                    {{-- <span>{{ Str::limit($headerTitle, 50) }}</span> --}}
                    <span>{{ $headerTitle }}</span>
                </div>
                @if ($headerDesc)
                    {{-- <p class="text-lg opacity-95 m-0 tracking-wide max-w-2xl mx-auto" title="{{ $headerDesc }}">{{ Str::limit($headerDesc, 80) }}</p> --}}
                    <p class="text-sm opacity-95 m-0 tracking-wide max-w-6xl leading-6 mx-auto"
                        title="{{ $headerDesc }}">{{ $headerDesc }}</p>
                @endif
            </div>
            <nav
                class="relative z-10 bg-white/10 backdrop-blur-md py-5 px-6 md:px-10 flex justify-center gap-2 flex-wrap">
                <a href="{{ route('landing.index') }}"
                    class="inline-block py-2.5 px-6 rounded-full text-sm font-medium text-white no-underline transition-all duration-300 {{ request()->routeIs('landing.index') ? 'bg-white/25 -translate-y-0.5 shadow-lg' : 'hover:bg-white/25 hover:-translate-y-0.5 hover:shadow-lg' }}">Home</a>
                <a href="{{ route('landing.contact') }}"
                    class="inline-block py-2.5 px-6 rounded-full text-sm font-medium text-white no-underline transition-all duration-300 {{ request()->routeIs('landing.contact') ? 'bg-white/25 -translate-y-0.5 shadow-lg' : 'hover:bg-white/25 hover:-translate-y-0.5 hover:shadow-lg' }}">Contact</a>
                @auth
                    <a href="{{ route('dashboard') }}"
                        class="inline-block py-2.5 px-6 rounded-full text-sm font-medium text-white no-underline transition-all duration-300 {{ request()->routeIs('dashboard') ? 'bg-white/25 -translate-y-0.5 shadow-lg' : 'hover:bg-white/25 hover:-translate-y-0.5 hover:shadow-lg' }}">Dashboard</a>
                @endauth
            </nav>
        </header>

        <main class="flex-1 p-8 md:p-12 bg-white">
            @yield('content')
        </main>

        {{-- Fixed Telegram Button --}}
        <a href="https://t.me/moonalites"
            class="fixed right-10 bottom-10 z-[99] w-[100px] h-[100px] md:w-[120px] md:h-[120px] flex items-center justify-center no-underline rounded-full hover:scale-110 transition-transform"
            target="_blank" aria-label="Telegram">
            <img src="{{ asset('images/img/tel.png') }}" alt="Telegram" class="w-full h-full object-contain" />
        </a>

        {{-- Footer --}}
<footer class="bg-gray-50 py-8 px-6 md:px-10 border-t border-gray-200">
    <div class="flex flex-col md:flex-row justify-between items-center gap-4 flex-wrap">

        <p class="m-0 text-gray-500 text-sm">
            &copy; {{ date('Y') }} {{ $siteSettings->site_title }}.
            All rights reserved.
        </p>

      <div class="flex items-center gap-3">
    <a href="{{ route('privacy.policy') }}"
        class="text-indigo-500 no-underline text-sm font-medium hover:text-indigo-600 hover:underline transition-colors">
        Privacy Policy
    </a>

    <span class="text-gray-500">&bull;</span>

    <a href="{{ route('terms.service') }}"
        class="text-indigo-500 no-underline text-sm font-medium hover:text-indigo-600 hover:underline transition-colors">
        Terms of Service
    </a>
</div>
    </div>
</footer>
    </div>

    @stack('script')
<script>
document.addEventListener('DOMContentLoaded', function () {

    // Disable right click
    document.addEventListener('contextmenu', function (e) {
        e.preventDefault();
    });

    // Disable text selection
    document.addEventListener('selectstart', function (e) {
        e.preventDefault();
    });

    // Disable copy
    document.addEventListener('copy', function (e) {
        e.preventDefault();
    });

    // Disable cut
    document.addEventListener('cut', function (e) {
        e.preventDefault();
    });

    // Disable drag
    document.addEventListener('dragstart', function (e) {
        e.preventDefault();
    });

    // Disable keyboard copy/select shortcuts
    document.addEventListener('keydown', function (e) {
        const key = (e.key || '').toLowerCase();

        if (
            (e.ctrlKey || e.metaKey) &&
            ['a', 'c', 'x', 's', 'u'].includes(key)
        ) {
            e.preventDefault();
        }
    });

    // Disable image dragging
    document.querySelectorAll('img').forEach(function (img) {
        img.setAttribute('draggable', 'false');
    });

});
</script>



</body>

</html>
