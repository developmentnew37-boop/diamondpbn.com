<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>@yield('title', 'Diamond Pbn')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@100..900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
    {{-- @vite(['resources/css/app.css', 'resources/js/app.js']) --}}
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('build/assets/app-Bnu7dOZh.css') }}">
    <script src="{{ asset('build/assets/app-Cwyqw0uf.js') }}" defer></script>
    <script src="{{ asset('js/script.js') }}" defer></script>
    @stack('style')


</head>

<body>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    @include('admin.include.sidebar')

    <!-- Main Wrapper -->
    <div class="main-wrapper">
        <!-- Navbar -->
        @include('admin.include.navbar')

        <!-- Main Content -->
        <main class="main-content">



            @yield('main-content')



        </main>

        <!-- Footer -->
        {{-- @include('admin.include.footer') --}}
    </div>

    @yield('popup')

    @stack('scripts')


</body>

</html>
