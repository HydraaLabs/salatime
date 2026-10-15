@extends('prayer_pages.layout')
@php
    $heading = $country ? __('prayer_pages.country_heading', ['country' => $countryName]) : __('prayer_pages.world_heading');
    $description = $country ? __('prayer_pages.country_description', ['country' => $countryName, 'count' => count($cities)]) : __('prayer_pages.world_description');
    $intro = $country ? __('prayer_pages.country_intro', ['country' => $countryName]) : __('prayer_pages.world_intro');
    $items = $country ? $cities : $countries->values()->all();
@endphp
@section('title', $heading.' | SalaTime')
@section('description', $description)
@push('structured-data')
@php
    $schema = [
        '@context' => 'https://schema.org', '@type' => 'CollectionPage',
        'url' => $canonical, 'name' => $heading, 'description' => $description,
        'inLanguage' => $localeConfig['language_tag'],
        'breadcrumb' => ['@type' => 'BreadcrumbList', 'itemListElement' => array_merge([
            ['@type' => 'ListItem', 'position' => 1, 'name' => __('prayer_pages.home'), 'item' => config('seo.site_url').'/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => __('prayer_pages.world_heading'), 'item' => $worldUrl],
        ], $country ? [['@type' => 'ListItem', 'position' => 3, 'name' => $countryName, 'item' => $canonical]] : [])],
        'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($items),
            'itemListElement' => collect($items)->values()->map(fn ($item, $i) => [
                '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item['names'][$locale], 'url' => $item['url'],
            ])->all()],
    ];
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush
@section('content')
<div class="container breadcrumbs" aria-label="{{ __('prayer_pages.breadcrumb') }}">
    <a href="{{ config('seo.site_url') }}/?lang={{ $locale }}">{{ __('prayer_pages.home') }}</a><span>›</span>
    @if($country)<a href="{{ $worldUrl }}">{{ __('prayer_pages.world_heading') }}</a><span>›</span><span>{{ $countryName }}</span>
    @else<span>{{ $heading }}</span>@endif
</div>
<section class="hero">
    <div class="container hero-grid">
        <div><span class="eyebrow">{{ __('prayer_pages.updated_daily') }}</span><h1>{{ $heading }}</h1><p class="hero-copy">{{ $intro }}</p></div>
        <div class="next-card"><span>{{ $country ? $countryName : __('prayer_pages.country_count', ['count' => $countries->count()]) }}</span>
            <strong>{{ __('prayer_pages.cities_count', ['count' => $country ? count($cities) : $cityCount]) }}</strong>
            <div class="next-time"><span>{{ __('prayer_pages.prayers.Fajr') }} · {{ __('prayer_pages.prayers.Dhuhr') }} · {{ __('prayer_pages.prayers.Asr') }}<br>{{ __('prayer_pages.prayers.Maghrib') }} · {{ __('prayer_pages.prayers.Isha') }}</span><b>☾</b></div>
        </div>
    </div>
</section>
@if($country)
<section class="section"><div class="container">
    <h2 class="section-title">{{ __('prayer_pages.country_today_heading', ['country' => $countryName]) }}</h2>
    <p class="section-intro">{{ __('prayer_pages.country_today_intro', ['count' => count($countryOverview)]) }}</p>
    @if(collect($countryOverview)->contains(fn ($city) => $city['today']['polar']))
        <p class="notice">{{ __('prayer_pages.polar_note') }}</p>
    @endif
    <div class="surface table-wrap" tabindex="0" role="region" aria-label="{{ __('prayer_pages.country_today_heading', ['country' => $countryName]) }}">
        <table class="monthly-calendar">
            <caption class="sr-only">{{ __('prayer_pages.country_today_heading', ['country' => $countryName]) }}</caption>
            <thead><tr>
                <th scope="col">{{ __('prayer_pages.city') }}</th>
                <th scope="col">{{ __('prayer_pages.date') }} · {{ __('prayer_pages.timezone') }}</th>
                @foreach(['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'] as $key)<th scope="col">{{ __('prayer_pages.prayers.'.$key) }}</th>@endforeach
            </tr></thead>
            <tbody>
            @foreach($countryOverview as $city)
                <tr>
                    <th scope="row"><a href="{{ $city['url'] }}" style="text-decoration:underline">{{ $city['names'][$locale] }}</a></th>
                    <td><time datetime="{{ $city['today']['date']->format('Y-m-d') }}">{{ $city['today']['date']->locale($locale)->isoFormat('D MMM YYYY') }}</time><br><small><bdi>{{ $city['timezone'] }}</bdi></small></td>
                    @foreach($city['today']['times'] as $time)<td dir="ltr">{{ $time }}</td>@endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="notice">{{ __('prayer_pages.country_time_note', ['country' => $countryName]) }}</div>
</div></section>
<section class="section" style="padding-top:0"><div class="container surface info-card">
    <h2>{{ __('prayer_pages.method_heading') }}</h2>
    <p>{{ __('prayer_pages.country_calculation_text') }}</p>
    <ul>
    @foreach($countryOverview as $city)
        <li><a href="{{ $city['url'] }}" style="text-decoration:underline">{{ $city['names'][$locale] }}</a> — {{ __('prayer_pages.methods.'.$city['method']) }} · {{ __('prayer_pages.schools.'.$city['school']) }} · <bdi>{{ $city['timezone'] }}</bdi></li>
    @endforeach
    </ul>
    <p>{{ __('prayer_pages.accuracy_note') }}</p>
</div></section>
@endif
<section class="section"><div class="container">
    <label class="search-label" for="city-search">{{ __('prayer_pages.search') }}</label>
    <input class="city-search" type="search" id="city-search" placeholder="{{ __('prayer_pages.search') }}" autocomplete="off" aria-controls="directory">
    <p id="no-results" role="status" hidden>{{ __('prayer_pages.no_results') }}</p>
    <div id="directory">
    @if($country)
        <div class="city-grid">
            @foreach($cities as $city)
            <a class="city-card" data-search="{{ implode(' ', $city['names']) }}" href="{{ $city['url'] }}"><span><strong>{{ $city['names'][$locale] }}</strong><small>{{ $countryName }}</small></span><span aria-hidden="true">→</span></a>
            @endforeach
        </div>
    @else
        @foreach($countries as $code => $entry)
        <section class="country-group" data-country="{{ implode(' ', $entry['names']) }}">
            <h2><a href="{{ $entry['url'] }}">{{ $entry['names'][$locale] }} <span aria-hidden="true">→</span></a></h2>
            <p class="section-intro">{{ __('prayer_pages.cities_count', ['count' => count($entry['cities'])]) }}</p>
            <div class="city-grid">
            @foreach(collect($entry['cities'])->sortBy(fn ($city) => $city['names'][$locale]) as $slug => $city)
                <a class="city-card" data-search="{{ implode(' ', $city['names']) }} {{ implode(' ', $entry['names']) }}" href="{{ $entry['url'].'/'.$slug }}"><span><strong>{{ $city['names'][$locale] }}</strong></span><span aria-hidden="true">→</span></a>
            @endforeach
            </div>
        </section>
        @endforeach
    @endif
    </div>
</div></section>
<section class="section" style="padding-top:0"><div class="container surface app-cta">
    <div><h2>{{ __('prayer_pages.app_heading') }}</h2><p>{{ __('prayer_pages.app_text') }}</p></div>
    @include('prayer_pages.store-links')
</div></section>
<script>
(() => {
    const input = document.getElementById('city-search');
    const cards = [...document.querySelectorAll('[data-search]')];
    const groups = [...document.querySelectorAll('.country-group')];
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f\u064b-\u065f]/g, '').toLocaleLowerCase();
    const names = cards.map(card => normalize(card.dataset.search));
    input.addEventListener('input', () => {
        const query = normalize(input.value.trim());
        let visible = 0;
        cards.forEach((card, i) => { card.hidden = !names[i].includes(query); if (!card.hidden) visible++; });
        groups.forEach(group => { group.hidden = ![...group.querySelectorAll('[data-search]')].some(card => !card.hidden); });
        document.getElementById('no-results').hidden = visible > 0;
    });
})();
</script>
@endsection
