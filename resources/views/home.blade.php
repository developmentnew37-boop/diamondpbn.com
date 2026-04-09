<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Diamond Pbn — Your PBN automation software. Domains, articles, and publishing campaigns.">
    <title>Diamond Pbn</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0c0d10;
            --surface: #12141a;
            --surface-elevated: #181b24;
            --muted: #8b919c;
            --line: rgba(255, 255, 255, 0.08);
            --accent: #ff4a17;
            --accent-soft: rgba(255, 74, 23, 0.15);
            --accent-glow: rgba(255, 74, 23, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: "DM Sans", system-ui, sans-serif;
            background: var(--ink);
            color: #e8eaef;
            min-height: 100vh;
            overflow-x: hidden;
        }

        .noise {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: 0.04;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            z-index: 0;
        }

        .glow {
            position: fixed;
            width: 70vmax;
            height: 70vmax;
            top: -20%;
            right: -25%;
            background: radial-gradient(circle, var(--accent-soft) 0%, transparent 55%);
            pointer-events: none;
            z-index: 0;
        }

        .wrap {
            position: relative;
            z-index: 1;
            max-width: 1120px;
            margin: 0 auto;
            padding: 2rem 1.5rem 4rem;
        }

        /* —— Header —— */
        header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.25rem;
            padding-bottom: 2.5rem;
            border-bottom: 1px solid var(--line);
        }

        .brand {
            display: flex;
            align-items: stretch;
            gap: 0;
            text-decoration: none;
            color: inherit;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(255, 74, 23, 0.22) 0%, rgba(255, 255, 255, 0.06) 45%, rgba(24, 27, 36, 0.9) 100%);
            padding: 1px;
            box-shadow:
                0 0 0 1px rgba(255, 255, 255, 0.06) inset,
                0 20px 50px -24px rgba(0, 0, 0, 0.6),
                0 0 80px -40px var(--accent-glow);
            transition: box-shadow 0.25s ease, transform 0.2s ease;
        }

        .brand:hover {
            box-shadow:
                0 0 0 1px rgba(255, 74, 23, 0.2) inset,
                0 24px 56px -24px rgba(0, 0, 0, 0.65),
                0 0 100px -36px rgba(255, 74, 23, 0.18);
            transform: translateY(-1px);
        }

        .brand-inner {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.65rem 1.15rem 0.65rem 0.65rem;
            border-radius: 15px;
            background: linear-gradient(165deg, var(--surface-elevated) 0%, var(--surface) 100%);
            width: 100%;
            min-height: 72px;
        }

        .brand-logo-wrap {
            flex-shrink: 0;
            width: 56px;
            height: 56px;
            border-radius: 14px;
            background: radial-gradient(circle at 30% 25%, rgba(255, 74, 23, 0.35), rgba(18, 20, 26, 0.95));
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow:
                0 8px 24px rgba(0, 0, 0, 0.45),
                0 0 0 1px rgba(255, 74, 23, 0.15) inset;
            display: grid;
            place-items: center;
            padding: 6px;
        }

        .brand-logo {
            width: 100%;
            height: 100%;
            object-fit: contain;
            display: block;
            filter: drop-shadow(0 2px 8px rgba(0, 0, 0, 0.35));
        }

        .brand-copy {
            display: flex;
            flex-direction: column;
            justify-content: center;
            gap: 0.35rem;
            min-width: 0;
            padding-right: 0.25rem;
        }

        .brand-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.5rem 0.65rem;
        }

        .brand-text {
            font-weight: 700;
            font-size: 1.2rem;
            letter-spacing: -0.03em;
            line-height: 1.15;
            background: linear-gradient(180deg, #fff 0%, #c8ccd6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-badge {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--accent);
            background: rgba(255, 74, 23, 0.12);
            border: 1px solid rgba(255, 74, 23, 0.28);
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
        }

        .brand-sub {
            font-size: 0.8125rem;
            color: var(--muted);
            font-weight: 500;
            line-height: 1.4;
            max-width: 280px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.7rem 1.35rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            text-decoration: none;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
            flex-shrink: 0;
        }

        .btn-primary {
            background: var(--accent);
            color: #fff;
            box-shadow: 0 4px 24px rgba(255, 74, 23, 0.35);
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 8px 32px rgba(255, 74, 23, 0.45);
        }

        .hero {
            padding: 3.5rem 0 2.5rem;
        }

        .hero-grid {
            display: grid;
            gap: 2.5rem;
            align-items: center;
        }

        @media (min-width: 900px) {
            .hero-grid {
                grid-template-columns: minmax(0, 1.05fr) minmax(260px, 0.95fr);
                gap: 2rem 3rem;
            }
        }

        .hero-copy {
            min-width: 0;
        }

        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 220px;
            cursor: default;
        }

        @media (min-width: 900px) {
            .hero-visual {
                justify-content: flex-end;
                min-height: 320px;
            }
        }

        .hero-visual::before {
            content: "";
            position: absolute;
            inset: -8% -5% -5% -5%;
            background: radial-gradient(ellipse 70% 60% at 60% 45%, rgba(255, 74, 23, 0.14) 0%, transparent 65%);
            pointer-events: none;
            z-index: 0;
            opacity: 0.9;
            transition: opacity 0.45s ease, transform 0.45s ease;
        }

        .hero-visual:hover::before {
            opacity: 1;
            transform: scale(1.03);
        }

        .hero-img {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: min(100%, 480px);
            height: auto;
            display: block;
            filter: drop-shadow(0 24px 48px rgba(0, 0, 0, 0.45));
            transition: transform 0.5s cubic-bezier(0.22, 1, 0.36, 1), filter 0.5s ease;
        }

        .hero-visual:hover .hero-img {
            transform: scale(1.045) translateY(-4px);
            filter:
                drop-shadow(0 32px 64px rgba(255, 74, 23, 0.18))
                drop-shadow(0 20px 40px rgba(0, 0, 0, 0.5));
        }

        @media (prefers-reduced-motion: reduce) {
            .hero-visual::before {
                transition: none;
            }

            .hero-visual:hover::before {
                transform: none;
            }

            .hero-img {
                transition: none;
            }

            .hero-visual:hover .hero-img {
                transform: none;
                filter: drop-shadow(0 24px 48px rgba(0, 0, 0, 0.45));
            }
        }

        .hero h1 {
            font-family: "Instrument Serif", Georgia, serif;
            font-size: clamp(2.5rem, 6vw, 3.75rem);
            font-weight: 400;
            line-height: 1.1;
            letter-spacing: -0.02em;
            max-width: 16ch;
        }

        @media (min-width: 900px) {
            .hero h1 {
                max-width: 14ch;
            }
        }

        .hero h1 em {
            font-style: italic;
            color: var(--accent);
        }

        .hero-lead {
            margin-top: 1.5rem;
            font-size: 1.125rem;
            line-height: 1.65;
            color: var(--muted);
            max-width: 46ch;
        }

        .hero-cta {
            margin-top: 2rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
        }

        .btn-ghost {
            background: transparent;
            color: #e8eaef;
            border: 1px solid var(--line);
        }

        .btn-ghost:hover {
            border-color: rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.03);
        }

        /* —— Sections —— */
        .section {
            margin-top: 3.25rem;
        }

        .section-head {
            margin-bottom: 1.25rem;
        }

        .section-kicker {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }

        .section-title {
            font-family: "Instrument Serif", Georgia, serif;
            font-size: clamp(1.65rem, 3.5vw, 2rem);
            font-weight: 400;
            letter-spacing: -0.02em;
        }

        .section-desc {
            margin-top: 0.5rem;
            font-size: 0.95rem;
            color: var(--muted);
            max-width: 52ch;
            line-height: 1.55;
        }

        .grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 1.5rem 1.35rem;
            transition: border-color 0.2s ease, transform 0.2s ease;
        }

        .card:hover {
            border-color: rgba(255, 74, 23, 0.28);
            transform: translateY(-2px);
        }

        .card h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .card p {
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--muted);
        }

        .card-num {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: var(--accent);
            margin-bottom: 0.75rem;
        }

        .card--roadmap {
            background: rgba(18, 20, 26, 0.65);
            border-style: dashed;
            border-color: rgba(255, 255, 255, 0.12);
            position: relative;
        }

        .card--roadmap:hover {
            border-color: rgba(255, 74, 23, 0.35);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.65rem;
        }

        .card-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #a8adb8;
            background: rgba(255, 255, 255, 0.06);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            border: 1px solid var(--line);
            white-space: nowrap;
        }

        .card-label--soon {
            color: var(--accent);
            background: rgba(255, 74, 23, 0.1);
            border-color: rgba(255, 74, 23, 0.25);
        }

        footer {
            margin-top: 4rem;
            padding-top: 2rem;
            border-top: 1px solid var(--line);
            font-size: 0.8rem;
            color: var(--muted);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1rem;
        }

        footer a {
            color: var(--muted);
            text-decoration: none;
        }

        footer a:hover {
            color: var(--accent);
        }

        @media (max-width: 720px) {
            header {
                flex-direction: column;
                align-items: stretch;
            }

            .brand .brand-inner {
                min-height: auto;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .hero {
                padding-top: 2rem;
            }

            .hero h1 {
                max-width: none;
            }

            .brand-sub {
                max-width: none;
            }
        }
    </style>
</head>

<body>
    <div class="noise" aria-hidden="true"></div>
    <div class="glow" aria-hidden="true"></div>

    <div class="wrap">
        <header>
            <a href="{{ url('/') }}" class="brand" aria-label="Diamond Pbn — home">
                <span class="brand-inner">
                    <span class="brand-logo-wrap">
                        <img src="{{ asset('logo.png') }}" alt="Diamond Pbn" class="brand-logo" width="44" height="44">
                    </span>
                    <span class="brand-copy">
                        <span class="brand-row">
                            <span class="brand-text">Diamond Pbn</span>
                            <span class="brand-badge">Automation</span>
                        </span>
                        <span class="brand-sub">Your PBN automation software — domains, content, and campaigns in one place.</span>
                    </span>
                </span>
            </a>
            <a href="{{ route('admin.login') }}" class="btn btn-primary">Admin sign in</a>
        </header>

        <section class="hero" aria-labelledby="hero-heading">
            <div class="hero-grid">
                <div class="hero-copy">
                    <h1 id="hero-heading">Operate your network with <em>clarity</em> and control.</h1>
                    <p class="hero-lead">
                        Manage domains, articles, and multi-type publishing campaigns from one dashboard.
                        Built for teams that scale outreach across many WordPress properties.
                    </p>
                    <div class="hero-cta">
                        <a href="{{ route('admin.login') }}" class="btn btn-primary">Open admin panel</a>
                        <a href="#capabilities" class="btn btn-ghost">What it does</a>
                        <a href="#roadmap" class="btn btn-ghost">Future additions</a>
                    </div>
                </div>
                <div class="hero-visual" aria-hidden="true">
                    <img src="{{ asset('images/hero-network.svg') }}" alt=""
                        class="hero-img" width="520" height="420" loading="eager" decoding="async">
                </div>
            </div>
        </section>

        <section id="capabilities" class="section" aria-labelledby="cap-title">
            <div class="section-head">
                <p class="section-kicker">Today</p>
                <h2 id="cap-title" class="section-title">What the platform does</h2>
                <p class="section-desc">
                    Core capabilities available in the admin workspace: inventory, content, and campaign execution backed by queued jobs.
                </p>
            </div>
            <div class="grid">
                <article class="card">
                    <div class="card-num">01</div>
                    <h3>Domains &amp; inventory</h3>
                    <p>Categories, sets, and status workflows so your site list stays accurate, filterable, and ready for campaign targeting.</p>
                </article>
                <article class="card">
                    <div class="card-num">02</div>
                    <h3>Articles &amp; sets</h3>
                    <p>Languages, categories, locking, and usage tracking keep content organized before it ships to the network.</p>
                </article>
                <article class="card">
                    <div class="card-num">03</div>
                    <h3>Campaign engine</h3>
                    <p>Post, sidebar, hidden link, scheduled, and WP-native flows—background workers handle publishing and updates at scale.</p>
                </article>
                <article class="card">
                    <div class="card-num">04</div>
                    <h3>Reporting &amp; exports</h3>
                    <p>Dashboard analytics plus secure, shareable campaign reports so stakeholders see progress without full admin access.</p>
                </article>
                <article class="card">
                    <div class="card-num">05</div>
                    <h3>Roles &amp; operations</h3>
                    <p>Super admins, admins, and members with OTP-backed sign-in—built for teams that run campaigns day to day.</p>
                </article>
                <article class="card">
                    <div class="card-num">06</div>
                    <h3>Reliability at scale</h3>
                    <p>Queue-driven jobs for publish, bulk update, retry, and delete paths—designed for large domain counts and long-running tasks.</p>
                </article>
            </div>
        </section>

        <section id="roadmap" class="section" aria-labelledby="road-title">
            <div class="section-head">
                <p class="section-kicker">Roadmap</p>
                <h2 id="road-title" class="section-title">Future additions</h2>
                <p class="section-desc">
                    We’re expanding toward a full self-service experience: credits, quotas, and a dedicated space for end users to connect their own sites and run campaigns.
                </p>
            </div>
            <div class="grid">
                <article class="card card--roadmap">
                    <div class="card-top">
                        <div class="card-num" style="margin-bottom:0;">User credit system</div>
                        <span class="card-label card-label--soon">Planned</span>
                    </div>
                    <h3>Transparent spend</h3>
                    <p>Purchase and consume credits for publishes, domains checks, and premium actions—clear balances and history per account.</p>
                </article>
                <article class="card card--roadmap">
                    <div class="card-top">
                        <div class="card-num" style="margin-bottom:0;">Dedicated user panel</div>
                        <span class="card-label card-label--soon">Planned</span>
                    </div>
                    <h3>Your own workspace</h3>
                    <p>A separate, user-facing panel (not only admin) where customers sign in, manage profile, and see their assets without operator hand-holding.</p>
                </article>
                <article class="card card--roadmap">
                    <div class="card-top">
                        <div class="card-num" style="margin-bottom:0;">Bring your domains</div>
                        <span class="card-label card-label--soon">Planned</span>
                    </div>
                    <h3>Add &amp; verify personal domains</h3>
                    <p>Users connect domains they own or manage, run verification, and organize them for campaigns—personalized inventory per tenant.</p>
                </article>
                <article class="card card--roadmap">
                    <div class="card-top">
                        <div class="card-num" style="margin-bottom:0;">Self-serve publishing</div>
                        <span class="card-label card-label--soon">Planned</span>
                    </div>
                    <h3>Articles &amp; campaigns on your sites</h3>
                    <p>Launch posts, sidebar placements, and scheduled runs against <em>your</em> connected properties—with guardrails and templates from the platform.</p>
                </article>
                <article class="card card--roadmap">
                    <div class="card-top">
                        <div class="card-num" style="margin-bottom:0;">User quota system</div>
                        <span class="card-label card-label--soon">Planned</span>
                    </div>
                    <h3>Fair limits &amp; tiers</h3>
                    <p>Enforce monthly caps on domains, articles, campaign posts, or API calls—aligned to plan tier and credit balance.</p>
                </article>
                <article class="card card--roadmap">
                    <div class="card-top">
                        <div class="card-num" style="margin-bottom:0;">More to come</div>
                        <span class="card-label card-label--soon">Exploring</span>
                    </div>
                    <h3>APIs, webhooks &amp; integrations</h3>
                    <p>Deeper automation hooks, billing provider tie-ins, and white-label options so agencies can productize the stack under their brand.</p>
                </article>
            </div>
        </section>

        <footer>
            <span>&copy; {{ date('Y') }} Diamond Pbn. All rights reserved.</span>
            <span><a href="{{ route('admin.login') }}">Administrator access</a></span>
        </footer>
    </div>
</body>

</html>
