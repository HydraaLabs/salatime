@extends('prayer_pages.layout')

@section('title', __('prayer_pages.city_title', ['city' => $cityName.', '.$countryName]))
@section('description', __('prayer_pages.city_description', ['city' => $cityName, 'country' => $countryName, 'timezone' => $timezone]))

@push('structured-data')
@php
    $faqs = [
        [
            'question' => __('prayer_pages.faq_1_q', ['city' => $cityName]),
            'answer' => __('prayer_pages.faq_1_a'),
        ],
        [
            'question' => __('prayer_pages.faq_2_q'),
            'answer' => $methodText,
        ],
        [
            'question' => __('prayer_pages.faq_3_q'),
            'answer' => __('prayer_pages.faq_3_a'),
        ],
    ];
    $pageSchema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'WebPage',
                '@id' => $canonical.'#webpage',
                'url' => $canonical,
                'name' => __('prayer_pages.city_heading', ['city' => $cityName]),
                'description' => __('prayer_pages.city_description', ['city' => $cityName, 'country' => $countryName, 'timezone' => $timezone]),
                'dateModified' => $today['date']->toAtomString(),
                'inLanguage' => $localeConfig['language_tag'],
                'isPartOf' => ['@id' => config('seo.site_url').'/#website'],
                'about' => [
                    '@type' => 'City',
                    'name' => $cityName,
                    'containedInPlace' => ['@type' => 'Country', 'name' => $countryName],
                    'geo' => [
                        '@type' => 'GeoCoordinates',
                        'latitude' => $cityConfig['latitude'],
                        'longitude' => $cityConfig['longitude'],
                    ],
                ],
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => __('prayer_pages.home'), 'item' => config('seo.site_url').'/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('prayer_pages.world_heading'), 'item' => $worldUrl],
                    ['@type' => 'ListItem', 'position' => 3, 'name' => $countryName, 'item' => $countryUrl],
                    ['@type' => 'ListItem', 'position' => 4, 'name' => $cityName, 'item' => $canonical],
                ],
            ],
            [
                '@type' => 'ItemList',
                'name' => __('prayer_pages.today', ['date' => $today['date']->locale($locale)->isoFormat('D MMMM YYYY')]),
                'numberOfItems' => count($today['times']),
                'itemListElement' => collect($today['times'])->map(fn ($time, $key) => [
                    '@type' => 'ListItem',
                    'position' => array_search($key, array_keys($today['times']), true) + 1,
                    'name' => __('prayer_pages.prayers.'.$key),
                    'description' => $time,
                ])->values()->all(),
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => collect($faqs)->map(fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
                ])->all(),
            ],
        ],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($pageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <div class="container breadcrumbs" aria-label="{{ __('prayer_pages.breadcrumb') }}">
        <a href="{{ config('seo.site_url') }}/">{{ __('prayer_pages.home') }}</a><span>›</span>
        <a href="{{ $worldUrl }}">{{ __('prayer_pages.world_heading') }}</a><span>›</span>
        <a href="{{ $countryUrl }}">{{ $countryName }}</a><span>›</span>
        <span>{{ $cityName }}</span>
    </div>

    <section class="hero">
        <div class="container hero-grid">
            <div>
                <span class="eyebrow">{{ __('prayer_pages.updated_daily') }}</span>
                <h1>{{ __('prayer_pages.city_heading', ['city' => $cityName]) }}</h1>
                <p class="hero-copy">{{ __('prayer_pages.today', ['date' => $today['date']->locale($locale)->isoFormat('dddd D MMMM YYYY')]) }}</p>
            </div>
            <div class="next-card">
                <span>{{ __('prayer_pages.next_prayer') }}</span>
                <strong>{{ $nextPrayer['key'] ? __('prayer_pages.prayers.'.$nextPrayer['key']) : __('prayer_pages.unavailable') }}</strong>
                <div class="next-time"><span>@if($nextPrayer['tomorrow']){{ __('prayer_pages.tomorrow') }}@else{{ $cityName }}@endif</span><b dir="ltr">{{ $nextPrayer['time'] }}</b></div>
            </div>
        </div>
    </section>

    @if($today['polar'])<div class="container notice">{{ __('prayer_pages.polar_note') }}</div>@endif
    <div class="container prayer-grid" aria-label="{{ __('prayer_pages.today', ['date' => $today['date']->format('Y-m-d')]) }}">
        @foreach($today['times'] as $key => $time)
        <article class="prayer-card @if(!$nextPrayer['tomorrow'] && $nextPrayer['key'] === $key) is-next @endif">
            <div class="prayer-icon" aria-hidden="true">{{ in_array($key, ['Fajr', 'Maghrib', 'Isha']) ? '☾' : '☀' }}</div>
            <span class="prayer-name">{{ __('prayer_pages.prayers.'.$key) }}</span>
            <span class="prayer-time" dir="ltr">{{ $time }}</span>
        </article>
        @endforeach
    </div>

    <section class="section">
        <div class="container">
            <h2 class="section-title">{{ __('prayer_pages.weekly_heading', ['city' => $cityName]) }}</h2>
            <p class="section-intro">{{ __('prayer_pages.weekly_intro') }}</p>
            <div class="surface table-wrap">
                <table>
                    <thead><tr><th>{{ __('prayer_pages.date') }}</th>@foreach(array_keys($today['times']) as $key)<th>{{ __('prayer_pages.prayers.'.$key) }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($weeklySchedule as $dayIndex => $day)
                        <tr @class(['is-today' => $dayIndex === 0])>
                            <td>{{ $day['date']->locale($locale)->isoFormat('ddd D MMM') }}</td>
                            @foreach($day['times'] as $time)<td dir="ltr">{{ $time }}</td>@endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section" style="padding-top: 0">
        <div class="container">
            <h2 class="section-title">{{ __('prayer_pages.monthly_heading', ['month' => $today['date']->locale($locale)->isoFormat('MMMM YYYY')]) }}</h2>
            <p class="section-intro">{{ __('prayer_pages.monthly_intro', ['timezone' => $timezone]) }}</p>
            @if(collect($monthlySchedule)->contains('polar', true))<p class="notice">{{ __('prayer_pages.polar_note') }}</p>@endif
            <div class="surface table-wrap">
                <table class="monthly-calendar">
                    <caption class="sr-only">{{ __('prayer_pages.monthly_heading', ['month' => $today['date']->locale($locale)->isoFormat('MMMM YYYY')]) }} — {{ $cityName }}</caption>
                    <thead><tr><th scope="col">{{ __('prayer_pages.date') }}</th>@foreach(array_keys($today['times']) as $key)<th scope="col">{{ __('prayer_pages.prayers.'.$key) }}</th>@endforeach</tr></thead>
                    <tbody>@foreach($monthlySchedule as $day)
                        <tr @class(['is-today' => $day['date']->isSameDay($today['date'])])><th scope="row">{{ $day['date']->locale($locale)->isoFormat('ddd D MMM') }}</th>@foreach($day['times'] as $time)<td dir="ltr">{{ $time }}</td>@endforeach</tr>
                    @endforeach</tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section" style="padding-top: 0">
        <div class="container content-grid">
            <article class="surface info-card">
                <h2>{{ __('prayer_pages.method_heading') }}</h2>
                <p>{{ $methodText }}</p>
                <div class="notice">{{ __('prayer_pages.accuracy_note') }}</div>
                <p>{{ __('prayer_pages.high_latitude_note') }}</p>
            </article>
            <aside class="surface info-card">
                <h2>{{ $cityName }}, {{ $countryName }}</h2>
                <p>{{ number_format($cityConfig['latitude'], 4) }}, {{ number_format($cityConfig['longitude'], 4) }}<br><bdi>{{ $timezone }}</bdi><br>{{ __('prayer_pages.updated_daily') }}</p>
            </aside>
        </div>
    </section>

    <section class="section" style="padding-top: 0">
        <div class="container">
            <h2 class="section-title">{{ __('prayer_pages.nearby_heading') }}</h2>
            <div class="city-grid">
                @foreach($nearbyCities as $nearby)
                <a class="city-card" href="{{ $nearby['url'] }}">
                    <span><strong>{{ $nearby['names'][$locale] }}</strong><small>{{ (int) round($nearby['distance']) }} km</small></span><span aria-hidden="true">→</span>
                </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section" style="padding-top: 0">
        <div class="container">
            <h2 class="section-title">{{ __('prayer_pages.faq_heading') }}</h2>
            <div class="faq-list">
                @foreach($faqs as $faq)
                <details><summary>{{ $faq['question'] }}</summary><p>{{ $faq['answer'] }}</p></details>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section" style="padding-top: 0">
        <div class="container surface app-cta">
            <div><h2>{{ __('prayer_pages.app_heading') }}</h2><p>{{ __('prayer_pages.app_text') }}</p></div>
            <a class="button" href="{{ config('seo.play_store_url') }}" rel="noopener">{{ __('prayer_pages.open_google_play') }}</a>
        </div>
    </section>
@endsection
