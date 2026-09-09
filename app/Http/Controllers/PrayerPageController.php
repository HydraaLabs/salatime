<?php

namespace App\Http\Controllers;

use App\Services\Prayer\PrayerPageCatalog;
use App\Services\Prayer\PrayerPageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrayerPageController extends Controller
{
    public function index(Request $request, PrayerPageCatalog $catalog): Response
    {
        [$locale, $localeConfig] = $this->localeContext($request);
        $code = $this->countryCode($request, $catalog, $locale);
        $country = $code === null ? null : $catalog->countries()[$code];
        $countries = collect($catalog->countries())->map(fn ($country, $code) => $country + [
            'url' => $catalog->url($locale, $code),
        ])->sortBy(fn ($country) => $country['names'][$locale], SORT_NATURAL | SORT_FLAG_CASE);
        $cities = $code === null ? [] : collect($catalog->cities($code))
            ->map(fn ($city, $slug) => $city + ['slug' => $slug, 'url' => $catalog->url($locale, $code, $slug)])
            ->sortBy(fn ($city) => $city['names'][$locale], SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
        $countryName = $country['names'][$locale] ?? '';
        $canonical = $catalog->url($locale, $code);
        $worldUrl = $catalog->url($locale);
        $alternates = $this->alternates($catalog, $code);
        $cityCount = $countries->sum(fn ($country) => count($country['cities']));

        return $this->publicResponse('prayer_pages.index', compact(
            'locale', 'localeConfig', 'country', 'countryName', 'countries', 'cities',
            'canonical', 'worldUrl', 'alternates', 'cityCount'
        ));
    }

    public function show(Request $request, PrayerPageService $service, PrayerPageCatalog $catalog): Response
    {
        [$locale, $localeConfig] = $this->localeContext($request);
        $code = $this->countryCode($request, $catalog, $locale);
        abort_if($code === null, 404);
        $city = (string) $request->route('city');
        $cities = $catalog->cities($code);
        abort_unless(isset($cities[$city]), 404);
        $cityConfig = $cities[$city] + ['slug' => $city];
        $timezone = $cityConfig['timezone'];
        $now = CarbonImmutable::now($timezone);
        $schedule = $service->schedule($code.'/'.$city, $cityConfig, $now->subDay()->startOfDay(), 9);
        $today = $schedule[1];
        $nextPrayer = $service->nextPrayer($today, $schedule[2], $now, $schedule[0]);
        $weeklySchedule = array_slice($schedule, 1, 7);
        $monthlySchedule = $service->schedule($code.'/'.$city, $cityConfig, $now->startOfMonth(), $now->daysInMonth);
        $nearbyCities = array_map(fn ($nearby) => $nearby + [
            'url' => $catalog->url($locale, $code, $nearby['slug']),
        ], $service->nearestCities($city, $cities));
        $canonical = $catalog->url($locale, $code, $city);
        $alternates = $this->alternates($catalog, $code, $city);
        $cityName = $cityConfig['names'][$locale];
        $countryName = $catalog->countries()[$code]['names'][$locale];
        $countryUrl = $catalog->url($locale, $code);
        $worldUrl = $catalog->url($locale);
        $methodText = __('prayer_pages.method_text', [
            'city' => $cityName, 'method' => __('prayer_pages.methods.'.$cityConfig['method']),
            'school' => __('prayer_pages.schools.'.$cityConfig['school']), 'timezone' => $timezone,
        ]);
        // Do not let a cached "next prayer" survive a prayer boundary or local midnight.
        $ttl = max(1, min(60, $now->diffInSeconds($now->addDay()->startOfDay())));
        if ($nextPrayer['at'] !== null) {
            $ttl = max(1, min($ttl, $now->diffInSeconds($nextPrayer['at'])));
        }

        return $this->publicResponse('prayer_pages.show', compact(
            'locale', 'localeConfig', 'cityConfig', 'cityName', 'countryName', 'countryUrl', 'worldUrl',
            'timezone', 'today', 'nextPrayer', 'weeklySchedule', 'monthlySchedule', 'nearbyCities',
            'canonical', 'alternates', 'methodText'
        ), $ttl);
    }

    private function countryCode(Request $request, PrayerPageCatalog $catalog, string $locale): ?string
    {
        if ($request->route('countryCode')) {
            return $request->route('countryCode');
        }
        $slug = $request->route('country');
        if ($slug === null) {
            return null;
        }
        $code = $catalog->countryCode($slug, $locale);
        abort_if($code === null, 404);

        return $code;
    }

    private function localeContext(Request $request): array
    {
        $locale = (string) $request->route('locale');
        $localeConfig = config("prayer_pages.locales.$locale");
        abort_unless(is_array($localeConfig), 404);
        app()->setLocale($locale);

        return [$locale, $localeConfig];
    }

    private function alternates(PrayerPageCatalog $catalog, ?string $code = null, ?string $city = null): array
    {
        return collect(config('prayer_pages.locales'))->mapWithKeys(fn ($settings, $locale) => [
            $settings['language_tag'] => $catalog->url($locale, $code, $city),
        ])->all();
    }

    private function publicResponse(string $view, array $data, int $ttl = 3600): Response
    {
        return response()->view($view, $data)
            ->header('Cache-Control', "public, max-age=$ttl, s-maxage=$ttl")
            ->header('Vary', 'Accept-Encoding');
    }
}
