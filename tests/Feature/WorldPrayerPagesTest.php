<?php

namespace Tests\Feature;

use App\Services\Prayer\PrayerPageCatalog;
use App\Services\Prayer\PrayerPageService;
use App\Vendor\PrayerTimes\Method;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class WorldPrayerPagesTest extends TestCase
{
    public function test_every_world_city_and_country_has_working_localized_canonical_pages(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-09 12:00:00', 'UTC'));
        try {
            $catalog = app(PrayerPageCatalog::class);
            foreach (config('prayer_pages.locales') as $locale => $settings) {
                $this->get($catalog->url($locale))->assertOk()->assertSee(__('prayer_pages.world_heading', [], $locale));
                foreach ($catalog->countries() as $code => $country) {
                    $countryUrl = $catalog->url($locale, $code);
                    $this->get($countryUrl)->assertOk()->assertSee($country['names'][$locale]);
                    foreach ($country['cities'] as $slug => $city) {
                        $url = $catalog->url($locale, $code, $slug);
                        $response = $this->get($url);
                        $this->assertSame(200, $response->status(), $url);
                        $response->assertSee('<link rel="canonical" href="'.$url.'">', false)
                            ->assertSee($city['names'][$locale])->assertSee($city['timezone'])
                            ->assertDontSee('prayer_pages.');
                        $this->assertSame(30, substr_count($response->getContent(), '<th scope="row">'), $url);
                    }
                }
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_world_catalog_has_valid_coordinates_timezones_methods_and_unique_country_slugs(): void
    {
        $catalog = app(PrayerPageCatalog::class);
        foreach (config('prayer_pages.locales') as $locale => $settings) {
            $slugs = array_column(array_column($catalog->countries(), 'slugs'), $locale);
            $this->assertCount(count($slugs), array_unique($slugs));
            foreach ($slugs as $slug) {
                $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            }
        }
        foreach ($catalog->countries() as $code => $country) {
            $this->assertContains($country['method'], Method::getMethodCodes());
            foreach ($catalog->cities($code) as $slug => $city) {
                $this->assertGreaterThanOrEqual(-90, $city['latitude']);
                $this->assertLessThanOrEqual(90, $city['latitude']);
                $this->assertGreaterThanOrEqual(-180, $city['longitude']);
                $this->assertLessThanOrEqual(180, $city['longitude']);
                $this->assertInstanceOf(\DateTimeZone::class, new \DateTimeZone($city['timezone']));
                $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            }
        }
    }

    public function test_alternates_country_breadcrumbs_and_structured_data_describe_the_same_city(): void
    {
        $catalog = app(PrayerPageCatalog::class);
        $url = $catalog->url('fr', 'FR', 'paris-2988507');
        $response = $this->get($url)->assertOk();
        foreach (config('prayer_pages.locales') as $locale => $settings) {
            $response->assertSee('hreflang="'.$settings['language_tag'].'" href="'.$catalog->url($locale, 'FR', 'paris-2988507').'"', false);
        }
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
        $graph = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $this->assertSame('Paris', $graph[0]['about']['name']);
        $this->assertSame('France', $graph[0]['about']['containedInPlace']['name']);
        $this->assertSame($catalog->url('fr', 'FR'), $graph[1]['itemListElement'][2]['item']);
        $response->assertDontSee('Africa/Casablanca')->assertDontSee('méthode Maroc');
        $this->get('/fr/horaires-priere/inconnu')->assertNotFound();
        $this->get('/fr/horaires-priere/france/fes')->assertNotFound();
        $this->get('/fr/horaires-priere/maroc/paris-2988507')->assertNotFound();
    }

    public function test_sitemap_has_all_canonical_pages_once_and_local_dates(): void
    {
        $catalog = app(PrayerPageCatalog::class);
        $response = $this->get('/sitemap.xml')->assertOk();
        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $urls = array_map(fn ($url) => (string) $url->loc, iterator_to_array($xml->url, false));
        $this->assertCount(count($urls), array_unique($urls));
        $urlSet = array_fill_keys($urls, true);
        foreach (config('prayer_pages.locales') as $locale => $settings) {
            $this->assertArrayHasKey($catalog->url($locale), $urlSet);
            foreach ($catalog->countries() as $code => $country) {
                $this->assertArrayHasKey($catalog->url($locale, $code), $urlSet);
                foreach ($country['cities'] as $slug => $city) {
                    $this->assertArrayHasKey($catalog->url($locale, $code, $slug), $urlSet);
                }
            }
        }
    }

    public function test_pages_use_city_local_date_and_cache_expires_at_midnight(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-09 14:59:55', 'UTC'));
        try {
            $response = $this->get('/en/prayer-times/japan/tokyo-1850147')->assertOk();
            $this->assertSame('2026-09-09', $response->viewData('today')['date']->format('Y-m-d'));
            $this->assertLessThanOrEqual(5, $response->getMaxAge());
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-09 15:00:05', 'UTC'));
            $response = $this->get('/en/prayer-times/japan/tokyo-1850147')->assertOk();
            $this->assertSame('2026-09-10', $response->viewData('today')['date']->format('Y-m-d'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_schedule_respects_dst_and_calculation_settings_in_cache(): void
    {
        $service = app(PrayerPageService::class);
        $city = app(PrayerPageCatalog::class)->cities('FR')['paris-2988507'];
        $days = $service->schedule('paris', $city, CarbonImmutable::parse('2026-03-28', 'Europe/Paris'), 2);
        $this->assertSame(1, $days[0]['date']->offsetHours);
        // The midnight offset changes later on March 29; calculated noon uses the new offset.
        $minute = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $jump = $minute($days[1]['times']['Dhuhr']) - $minute($days[0]['times']['Dhuhr']);
        $this->assertGreaterThanOrEqual(58, $jump);
        $this->assertLessThanOrEqual(62, $jump);
        $hanafi = $service->schedule('paris', array_replace($city, ['school' => 'HANAFI']), CarbonImmutable::parse('2026-03-28', 'Europe/Paris'), 2);
        $this->assertNotSame($days[0]['times']['Asr'], $hanafi[0]['times']['Asr']);
        $mwl = $service->schedule('paris', array_replace($city, ['method' => 'MWL']), CarbonImmutable::parse('2026-03-28', 'Europe/Paris'), 2);
        $this->assertNotSame($days[0]['times']['Fajr'], $mwl[0]['times']['Fajr']);
    }

    public function test_next_prayer_handles_seconds_and_isha_after_midnight(): void
    {
        $service = app(PrayerPageService::class);
        $day = fn ($date) => ['date' => CarbonImmutable::parse($date, 'Europe/Paris'), 'times' => [
            'Fajr' => '03:30', 'Dhuhr' => '13:00', 'Asr' => '17:00', 'Maghrib' => '22:00', 'Isha' => '00:30',
        ]];
        $next = $service->nextPrayer($day('2026-06-22'), $day('2026-06-23'), CarbonImmutable::parse('2026-06-22 00:10:15', 'Europe/Paris'), $day('2026-06-21'));
        $this->assertSame('Isha', $next['key']);
        $this->assertFalse($next['tomorrow']);
        $next = $service->nextPrayer($day('2026-06-22'), $day('2026-06-23'), CarbonImmutable::parse('2026-06-22 13:00:00', 'Europe/Paris'));
        $this->assertSame('Asr', $next['key']);
    }

    public function test_public_pages_do_not_issue_session_cookies(): void
    {
        foreach (['/fr/horaires-priere', '/fr/horaires-priere/france', '/fr/horaires-priere/france/paris-2988507'] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertSame([], $response->headers->getCookies());
        }
    }

    public function test_calculated_times_match_an_independent_reference_within_one_minute(): void
    {
        // Public AlAdhan timings API, 10-09-2026, 48.85341/2.3488,
        // method=12, school=0, timezonestring=Europe/Paris; retrieved 2026-09-09.
        $reference = ['Fajr' => '06:09', 'Sunrise' => '07:20', 'Dhuhr' => '13:48', 'Asr' => '17:23', 'Maghrib' => '20:14', 'Isha' => '21:25'];
        $city = app(PrayerPageCatalog::class)->cities('FR')['paris-2988507'];
        $day = app(PrayerPageService::class)->schedule('reference-paris', $city, CarbonImmutable::parse('2026-09-10', 'Europe/Paris'), 1)[0];
        $minutes = fn ($time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        foreach ($reference as $prayer => $time) {
            $this->assertLessThanOrEqual(1, abs($minutes($day['times'][$prayer]) - $minutes($time)), $prayer);
        }
    }

    public function test_polar_sun_does_not_produce_invented_prayer_times(): void
    {
        $city = ['latitude' => 69.6492, 'longitude' => 18.9553, 'timezone' => 'Europe/Oslo', 'method' => 'MWL'];
        $days = app(PrayerPageService::class)->schedule('polar-test', $city, CarbonImmutable::parse('2026-06-21', 'Europe/Oslo'), 2);
        $this->assertTrue($days[0]['polar']);
        $this->assertSame(['—'], array_values(array_unique($days[0]['times'])));
        $next = app(PrayerPageService::class)->nextPrayer($days[0], $days[1], $days[0]['date']);
        $this->assertNull($next['key']);
    }
}
