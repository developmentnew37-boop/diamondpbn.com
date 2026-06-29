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
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    @include('partials.build-assets')
    <script src="{{ asset('js/script.js') }}" defer></script>
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

    <script>
        (function() {
            const AUTO_HIDE_MS = 5000;
            const alerts = document.querySelectorAll(
                [
                    '.main-content .js-cus-alert',
                    '.main-content [role="alert"]',
                    '.main-content div.text-sm.rounded.bg-green-100.text-green-700',
                    '.main-content div.text-sm.rounded.bg-red-100.text-red-700',
                ].join(', ')
            );
            alerts.forEach(function(el) {
                setTimeout(function() {
                    el.style.transition = 'opacity 0.3s ease';
                    el.style.opacity = '0';
                    setTimeout(function() {
                        el.remove();
                    }, 300);
                }, AUTO_HIDE_MS);
            });
        })();
    </script>


</body>

</html>
