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
    <link rel="stylesheet" href="{{ asset('build/assets/app-BMLFxF1u.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <script src="{{ asset('build/assets/app-Cwyqw0uf.js') }}"></script>
    {{-- <script src="{{ asset('js/script.js') }}"></script> --}}
    @stack('style')


</head>

<body>

    <!-- Main Wrapper -->
    <div class="w-full">
        <!-- Navbar -->


        <!-- Main Content -->
        <main class="main-content !ml-0">



            @yield('main-content')



        </main>

        <!-- Footer -->

    </div>

    @yield('popup')

    @stack('scripts')


</body>

</html>
