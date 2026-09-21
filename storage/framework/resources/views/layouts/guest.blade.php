@php
    $siteSettings = $siteSettings ?? \App\Models\Setting::getSettings();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('Sign in') }} - {{ $siteSettings->site_title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
          <link rel="stylesheet" href="{{ asset('build/assets/app-CV8RmTp3.css') }}">
        <script src="{{ asset('build/assets/app-CagNDbK6.js') }}"></script>
</head>
<body class="font-sans antialiased min-h-screen flex flex-col sm:justify-center items-center py-8 px-4" style="background: {{ $siteSettings->theme_bg_gradient }}; background-attachment: fixed;">
    <div class="w-full sm:max-w-md">
        <div class="text-center mb-6">
            <a href="{{ route('landing.index') }}" class="inline-block text-2xl font-bold text-gray-800 hover:opacity-90 transition-opacity" style="color: inherit;">
                {{ $siteSettings->site_title }}
            </a>
        </div>
        <div class="rounded-2xl shadow-xl overflow-hidden border border-gray-200/80 bg-white/95 backdrop-blur">
            <div class="px-8 py-6 text-white" style="background: {{ $siteSettings->theme_gradient_css }};">
                <h1 class="text-xl font-semibold m-0">{{ $heading ?? __('Sign in') }}</h1>
                <p class="text-sm opacity-90 mt-1 m-0">{{ $subheading ?? __('Enter your credentials to continue') }}</p>
            </div>
            <div class="p-8">
                {{ $slot }}
            </div>
        </div>
        <p class="text-center mt-6">
            <a href="{{ route('landing.index') }}" class="text-sm text-gray-600 hover:text-gray-900 no-underline">{{ __('Back to home') }}</a>
        </p>
    </div>
</body>
</html>
