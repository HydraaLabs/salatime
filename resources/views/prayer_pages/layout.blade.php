<!DOCTYPE html>
<html lang="{{ $localeConfig['language_tag'] }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <meta name="description" content="@yield('description')">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <meta name="theme-color" content="#0b4a38">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach($alternates as $language => $url)
    <link rel="alternate" hreflang="{{ $language }}" href="{{ $url }}">
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ $alternates['en'] }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="SalaTime">
    <meta property="og:locale" content="{{ $localeConfig['og_locale'] }}">
    <meta property="og:title" content="@yield('title')">
    <meta property="og:description" content="@yield('description')">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ asset(config('seo.social_image')) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('title')">
    <meta name="twitter:description" content="@yield('description')">
    <meta name="twitter:image" content="{{ asset(config('seo.social_image')) }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v=20260827">
    @stack('structured-data')

    <style>
        :root {
            --ink: #102a22;
            --muted: #5d716a;
            --green-950: #052b21;
            --green-900: #073b2d;
            --green-800: #0b4a38;
            --green-700: #116249;
            --green-600: #16825f;
            --mint: #e8f5ef;
            --line: #dcebe4;
            --gold: #d6a83b;
            --surface: #ffffff;
            --page: #f4f8f6;
            --radius: 22px;
            --shadow: 0 20px 55px rgba(5, 43, 33, .09);
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
        .search-label { display: block; font-weight: 700; margin-bottom: 8px; }
        .city-search { width: 100%; padding: 16px; font: inherit; border: 1px solid var(--line); border-radius: 12px; margin-bottom: 24px; background: white; color: var(--ink); }
        .country-group { margin-bottom: 44px; }
        a:focus-visible, input:focus-visible, summary:focus-visible { outline: 3px solid var(--gold); outline-offset: 4px; }
        .monthly-calendar tbody th { text-transform: none; letter-spacing: normal; background: white; }
        .monthly-calendar .is-today th { background: #fbf6e7; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            background: var(--page);
            color: var(--ink);
            font-family: Inter, Manrope, "Segoe UI", Tahoma, Arial, sans-serif;
            line-height: 1.6;
        }
        a { color: inherit; text-decoration: none; }
        img { display: block; max-width: 100%; }
        .container { width: min(1160px, calc(100% - 32px)); margin-inline: auto; }
        .site-header {
            position: sticky;
            top: 0;
            z-index: 20;
            border-bottom: 1px solid rgba(255,255,255,.1);
            background: rgba(5,43,33,.96);
            backdrop-filter: blur(16px);
            color: white;
        }
        .nav { min-height: 74px; display: flex; align-items: center; justify-content: space-between; gap: 22px; }
        .brand { display: flex; align-items: center; gap: 13px; }
        .brand img { width: 170px; height: 46px; object-fit: contain; }
        .brand-tagline { max-width: 145px; color: #b9d6cb; font-weight: 500; font-size: .72rem; line-height: 1.25; }
        .nav-actions { display: flex; align-items: center; gap: 10px; }
        .language-picker { position: relative; padding: 0; border: 0; border-radius: 12px; background: transparent; color: white; }
        .language-picker > summary { display: flex; align-items: center; gap: 9px; min-height: 44px; padding: 9px 13px; list-style: none; border: 1px solid rgba(255,255,255,.2); border-radius: 12px; background: rgba(255,255,255,.08); font-size: .9rem; white-space: nowrap; }
        .language-picker > summary::-webkit-details-marker { display: none; }
        .language-picker > summary:hover, .language-picker[open] > summary { background: rgba(255,255,255,.16); }
        .language-flag { font-size: 1.3rem; line-height: 1; }
        .language-chevron { width: 14px; height: 14px; transition: transform .15s; }
        .language-picker[open] .language-chevron { transform: rotate(180deg); }
        .language-links { position: absolute; inset-inline-end: 0; top: calc(100% + 9px); display: grid; gap: 4px; min-width: 190px; padding: 7px; border: 1px solid var(--line); border-radius: 14px; background: white; color: var(--ink); box-shadow: var(--shadow); }
        .language-links a { display: flex; align-items: center; gap: 11px; min-height: 44px; padding: 10px 12px; font-size: .9rem; font-weight: 700; border-radius: 9px; }
        .language-links a[aria-current="page"], .language-links a:hover { color: var(--green-800); background: var(--mint); }
        .language-check { margin-inline-start: auto; }
        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 44px;
            padding: 10px 17px;
            border-radius: 999px;
            background: var(--gold);
            color: #18251e;
            font-weight: 800;
        }
        .breadcrumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: var(--muted); font-size: .86rem; padding-block: 22px; }
        .breadcrumbs a:hover { color: var(--green-700); }
        .hero {
            overflow: hidden;
            position: relative;
            padding: 60px 0 72px;
            background:
                radial-gradient(circle at 80% 8%, rgba(214,168,59,.22), transparent 25%),
                radial-gradient(circle at 15% 90%, rgba(39,151,112,.26), transparent 30%),
                linear-gradient(145deg, var(--green-950), var(--green-800));
            color: white;
        }
        .hero::after { content: "☾"; position: absolute; inset-inline-end: 7%; top: -72px; color: rgba(255,255,255,.055); font-size: 300px; line-height: 1; }
        .hero-grid { position: relative; z-index: 1; display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(280px, .65fr); gap: 44px; align-items: center; }
        .eyebrow { display: inline-flex; padding: 7px 12px; border: 1px solid rgba(255,255,255,.18); border-radius: 999px; color: #d6e9e1; font-weight: 700; font-size: .82rem; }
        h1 { margin: 17px 0 12px; font-size: clamp(2.15rem, 5vw, 4.15rem); line-height: 1.06; letter-spacing: -.045em; }
        .hero-copy { margin: 0; color: #c8ddd5; font-size: clamp(1rem, 2vw, 1.15rem); max-width: 720px; }
        .next-card { padding: 25px; border-radius: var(--radius); border: 1px solid rgba(255,255,255,.16); background: rgba(255,255,255,.1); box-shadow: 0 30px 70px rgba(0,0,0,.18); backdrop-filter: blur(14px); }
        .next-card span { color: #c6ddd4; font-size: .9rem; }
        .next-card strong { display: block; margin-top: 5px; font-size: 1.65rem; }
        .next-time { display: flex; align-items: baseline; justify-content: space-between; gap: 18px; margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,.15); }
        .next-time b { font-size: 2.4rem; color: #f7d989; line-height: 1; }
        main { min-height: 65vh; }
        .section { padding: 68px 0; }
        .section-title { margin: 0 0 9px; font-size: clamp(1.65rem, 3vw, 2.35rem); line-height: 1.2; letter-spacing: -.025em; }
        .section-intro { margin: 0 0 28px; color: var(--muted); max-width: 760px; }
        .prayer-grid { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: 12px; margin-top: -34px; position: relative; z-index: 3; }
        .prayer-card { padding: 19px 12px; border: 1px solid var(--line); border-radius: 17px; background: var(--surface); text-align: center; box-shadow: 0 15px 40px rgba(5,43,33,.06); }
        .prayer-card.is-next { border-color: var(--gold); box-shadow: 0 15px 40px rgba(214,168,59,.18); transform: translateY(-4px); }
        .prayer-icon { color: var(--gold); font-size: 1.45rem; }
        .prayer-name { display: block; margin: 4px 0; color: var(--muted); font-size: .83rem; font-weight: 700; }
        .prayer-time { font-size: 1.32rem; font-weight: 900; }
        .surface { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--shadow); }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; min-width: 760px; border-collapse: collapse; }
        th, td { padding: 15px 17px; border-bottom: 1px solid var(--line); text-align: center; white-space: nowrap; }
        th { background: #edf6f2; color: var(--green-800); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
        th:first-child, td:first-child { text-align: start; font-weight: 750; }
        tr:last-child td { border-bottom: 0; }
        tr.is-today td { background: #fbf6e7; }
        .content-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(280px, .65fr); gap: 26px; }
        .info-card { padding: 28px; }
        .info-card h2 { margin: 0 0 10px; font-size: 1.35rem; }
        .info-card p { color: var(--muted); margin: 0; }
        .notice { margin-top: 17px; padding: 14px 16px; border-inline-start: 4px solid var(--gold); border-radius: 10px; background: #fbf6e7; color: #67531f; font-size: .9rem; }
        .city-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 13px; }
        .city-card { display: flex; min-height: 96px; padding: 18px; align-items: center; justify-content: space-between; gap: 12px; border: 1px solid var(--line); border-radius: 16px; background: white; transition: .2s ease; }
        .city-card:hover { transform: translateY(-3px); border-color: #94c9b4; box-shadow: 0 14px 35px rgba(5,43,33,.08); }
        .city-card strong { display: block; font-size: 1.05rem; }
        .city-card small { display: block; color: var(--muted); font-size: .78rem; }
        .city-card span { color: var(--green-600); font-weight: 900; }
        .faq-list { display: grid; gap: 12px; }
        details { padding: 18px 21px; background: white; border: 1px solid var(--line); border-radius: 14px; }
        summary { cursor: pointer; font-weight: 800; }
        details p { margin: 12px 0 0; color: var(--muted); }
        .app-cta { display: flex; align-items: center; justify-content: space-between; gap: 30px; padding: 34px; color: white; background: linear-gradient(135deg, var(--green-950), var(--green-700)); }
        .app-cta h2 { margin: 0 0 7px; }
        .app-cta p { margin: 0; color: #c8ddd5; max-width: 700px; }
        footer { margin-top: 70px; padding: 42px 0; color: #a9c5ba; background: var(--green-950); }
        .footer-row { display: flex; justify-content: space-between; gap: 22px; align-items: center; }
        .footer-row a:hover { color: white; }
        @media (max-width: 900px) {
            .brand-tagline { display: none; }
            .hero-grid, .content-grid { grid-template-columns: 1fr; }
            .prayer-grid { grid-template-columns: repeat(3, 1fr); }
            .city-grid { grid-template-columns: repeat(2, 1fr); }
            .hero { padding-top: 44px; }
        }
        @media (max-width: 640px) {
            .container { width: min(100% - 22px, 1160px); }
            .nav { min-height: 66px; }
            .brand-tagline, .nav-actions > .button { display: none; }
            .brand img { width: 138px; height: 40px; }
            .language-picker > summary { padding: 9px 10px; gap: 7px; }
            .hero { padding: 38px 0 60px; }
            .prayer-grid { grid-template-columns: repeat(2, 1fr); margin-top: -25px; }
            .city-grid { grid-template-columns: 1fr; }
            .section { padding: 48px 0; }
            .app-cta, .footer-row { align-items: flex-start; flex-direction: column; }
        }
    </style>
</head>
<body>
    <header class="site-header">
        <div class="container nav">
            <a class="brand" href="{{ config('seo.site_url') }}/" aria-label="SalaTime">
                <img src="{{ asset('assets/img/logo.png') }}?v=20260827" alt="SalaTime">
                <span class="brand-tagline">{{ __('prayer_pages.brand_tagline') }}</span>
            </a>
            <div class="nav-actions">
                <details class="language-picker">
                    <summary aria-label="{{ __('prayer_pages.languages') }}: {{ $localeConfig['name'] }}">
                        <span class="language-flag" aria-hidden="true">{{ $localeConfig['flag'] }}</span>
                        <span lang="{{ $locale }}">{{ $localeConfig['name'] }}</span>
                        <svg class="language-chevron" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </summary>
                    <nav class="language-links" aria-label="{{ __('prayer_pages.languages') }}">
                        @foreach(config('prayer_pages.locales') as $languageCode => $languageSettings)
                        <a href="{{ $alternates[$languageSettings['language_tag']] }}" hreflang="{{ $languageSettings['language_tag'] }}" lang="{{ $languageCode }}" @if($languageCode === $locale) aria-current="page" @endif>
                            <span class="language-flag" aria-hidden="true">{{ $languageSettings['flag'] }}</span>
                            <span>{{ $languageSettings['name'] }}</span>
                            @if($languageCode === $locale)<span class="language-check" aria-hidden="true">✓</span>@endif
                        </a>
                        @endforeach
                    </nav>
                </details>
                <a class="button" href="{{ config('seo.play_store_url') }}" rel="noopener">{{ __('prayer_pages.download') }}</a>
            </div>
        </div>
    </header>

    <main>
        @yield('content')
    </main>

    <footer>
        <div class="container footer-row">
            <span>© {{ date('Y') }} SalaTime · {{ __('prayer_pages.source') }} : <a href="https://www.geonames.org/">GeoNames</a> (<a href="https://creativecommons.org/licenses/by/4.0/">CC BY 4.0</a>)</span>
            <span><a href="{{ $worldUrl }}">{{ __('prayer_pages.world_heading') }}</a> · <a href="{{ url('privacy-policy') }}">{{ __('prayer_pages.privacy') }}</a></span>
        </div>
    </footer>
<script>
(() => {
    const picker = document.querySelector('.language-picker');
    document.addEventListener('click', event => {
        if (!picker.contains(event.target)) picker.open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && picker.open) {
            picker.open = false;
            picker.querySelector('summary').focus();
        }
    });
})();
</script>
</body>
</html>
