<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Error') — Diamond pbn</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --err-primary: #ff4a17;
            --err-primary-dim: rgba(255, 74, 23, 0.15);
            --err-bg: #0c0f14;
            --err-surface: #151b24;
            --err-border: rgba(255, 255, 255, 0.06);
            --err-text: #f1f5f9;
            --err-muted: #94a3b8;
            --err-glow: rgba(255, 74, 23, 0.35);
        }

        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: 'Outfit', system-ui, sans-serif;
            background: var(--err-bg);
            color: var(--err-text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }

        .err-noise {
            position: fixed;
            inset: 0;
            opacity: 0.04;
            pointer-events: none;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        }

        .err-shell {
            position: relative;
            width: 100%;
            max-width: 28rem;
        }

        .err-card {
            position: relative;
            background: linear-gradient(145deg, var(--err-surface) 0%, #111820 100%);
            border: 1px solid var(--err-border);
            border-radius: 1.25rem;
            padding: 2rem 1.75rem 2.25rem;
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.03) inset,
                0 24px 48px -12px rgba(0, 0, 0, 0.55),
                0 0 80px -20px var(--err-glow);
        }

        .err-art {
            display: flex;
            justify-content: center;
            margin-bottom: 1.25rem;
        }

        .err-art svg {
            width: min(100%, 220px);
            height: auto;
            overflow: visible;
        }

        .err-art-float {
            animation: err-float 5s ease-in-out infinite;
        }

        @keyframes err-float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-6px); }
        }

        @media (prefers-reduced-motion: reduce) {
            .err-art-float {
                animation: none;
            }
        }

        .err-code {
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.2em;
            text-transform: uppercase;
            color: var(--err-primary);
            margin: 0 0 0.35rem;
            text-align: center;
        }

        .err-title {
            font-size: 1.5rem;
            font-weight: 700;
            line-height: 1.25;
            margin: 0 0 0.65rem;
            text-align: center;
            letter-spacing: -0.02em;
        }

        .err-desc {
            font-size: 0.95rem;
            line-height: 1.55;
            color: var(--err-muted);
            margin: 0 0 1.5rem;
            text-align: center;
        }

        .err-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.65rem;
            justify-content: center;
        }

        .err-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.65rem 1.15rem;
            font-size: 0.9rem;
            font-weight: 600;
            font-family: inherit;
            border-radius: 0.65rem;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .err-btn:active { transform: scale(0.98); }

        .err-btn--primary {
            background: var(--err-primary);
            color: #fff;
            box-shadow: 0 4px 14px rgba(255, 74, 23, 0.35);
        }

        .err-btn--primary:hover {
            filter: brightness(1.06);
            box-shadow: 0 6px 20px rgba(255, 74, 23, 0.4);
        }

        .err-btn--ghost {
            background: transparent;
            color: var(--err-text);
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .err-btn--ghost:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.18);
        }

        .err-brand {
            margin-top: 1.75rem;
            text-align: center;
            font-size: 0.75rem;
            color: rgba(148, 163, 184, 0.5);
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="err-noise" aria-hidden="true"></div>
    <div class="err-shell">
        <div class="err-card">
            <div class="err-art err-art-float" role="img" aria-hidden="true">
                @yield('illustration')
            </div>
            <p class="err-code">@yield('code')</p>
            <h1 class="err-title">@yield('heading')</h1>
            <p class="err-desc">@yield('message')</p>
            <div class="err-actions">
                <a class="err-btn err-btn--primary" href="{{ url('/') }}">Back to home</a>
                <button type="button" class="err-btn err-btn--ghost" onclick="history.length > 1 ? history.back() : (window.location.href='{{ url('/') }}')">
                    Go back
                </button>
            </div>
        </div>
        <p class="err-brand">Diamond pbn</p>
    </div>
</body>
</html>
