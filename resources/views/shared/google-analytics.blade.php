@php
    try {
        $googleAnalyticsId = resolve(\App\Services\Setting\SettingService::class)
            ->getCachedFormattedSettings('landing')['seo_google_analytics'] ?? null;
    } catch (\Throwable) {
        $googleAnalyticsId = null;
    }
@endphp
@if(is_string($googleAnalyticsId) && preg_match('/\AG-[A-Z0-9]+\z/', $googleAnalyticsId))
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $googleAnalyticsId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($googleAnalyticsId));
    </script>
@endif
