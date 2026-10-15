<?php

namespace Tests\Feature;

use App\Services\Prayer\PrayerPageCatalog;
use App\Services\Prayer\PrayerPageService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class CountryPrayerOverviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_single_city_country_renders_real_today_times_and_calculation_settings(): void
    {
        $catalog = app(PrayerPageCatalog::class);
        $response = $this->get($catalog->url('en', 'RO'))->assertOk();
        $overview = $response->viewData('countryOverview');
        $this->assertCount(1, $overview);
        $city = $catalog->cities('RO')['bucharest-683506'];
        $today = app(PrayerPageService::class)->schedule('RO/bucharest-683506', $city, CarbonImmutable::now($city['timezone']), 1)[0];
        $this->assertSame($today['times'], $overview[0]['today']['times']);
        $this->assertSame('2026-10-12', $overview[0]['today']['date']->format('Y-m-d'));
        foreach ($today['times'] as $time) {
            $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $time);
            $response->assertSee($time);
        }
        $response->assertSee('Bucharest')->assertSee('Europe/Bucharest')
            ->assertSee(__('prayer_pages.methods.'.$city['method'], [], 'en'))
            ->assertSee(__('prayer_pages.schools.'.$city['school'], [], 'en'))
            ->assertSee('href="'.$catalog->url('en', 'RO', 'bucharest-683506').'"', false)
            ->assertSee('There is no single prayer time for all of Romania.')
            ->assertSee('<th scope="col">Sunrise</th>', false);
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_australian_rows_use_each_city_local_date_and_match_city_pages(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 14:30:00', 'UTC'));
        $catalog = app(PrayerPageCatalog::class);
        $response = $this->get($catalog->url('en', 'AU'))->assertOk();
        $overview = collect($response->viewData('countryOverview'))->keyBy('slug');
        $this->assertCount(8, $overview);
        $this->assertSame('2026-10-13', $overview['sydney-2147714']['today']['date']->format('Y-m-d'));
        $this->assertSame('2026-10-12', $overview['perth-2063523']['today']['date']->format('Y-m-d'));
        foreach (['sydney-2147714', 'perth-2063523'] as $slug) {
            $cityPage = $this->get($catalog->url('en', 'AU', $slug))->assertOk();
            $this->assertSame($cityPage->viewData('today')['times'], $overview[$slug]['today']['times']);
            $response->assertSee('datetime="'.$overview[$slug]['today']['date']->format('Y-m-d').'"', false);
        }
    }

    public function test_all_twelve_search_console_country_examples_have_useful_daily_timings(): void
    {
        $catalog = app(PrayerPageCatalog::class);
        foreach (['GN', 'RO', 'AU', 'VE', 'VG', 'HK', 'ES', 'MZ', 'FO', 'BA', 'VA', 'BG'] as $code) {
            $response = $this->get($catalog->url('en', $code))->assertOk();
            $overview = $response->viewData('countryOverview');
            $this->assertCount(min(8, count($catalog->cities($code))), $overview, $code);
            $this->assertNotEmpty($overview, $code);
            foreach ($overview as $city) {
                $this->assertFalse($city['today']['polar'], $code.'/'.$city['slug']);
                foreach ($city['today']['times'] as $time) {
                    $this->assertMatchesRegularExpression('/^\d{2}:\d{2}$/', $time, $code.'/'.$city['slug']);
                    $response->assertSee($time);
                }
                $response->assertSee($city['timezone']);
            }
            $response->assertSee(__('prayer_pages.country_time_note', ['country' => $catalog->countries()[$code]['names']['en']], 'en'));
            $this->assertLessThanOrEqual(3600, $response->getMaxAge());
        }
    }

    public function test_country_public_cache_expires_before_any_displayed_city_midnight(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 12:59:55', 'UTC'));
        $catalog = app(PrayerPageCatalog::class);
        $response = $this->get($catalog->url('en', 'AU'))->assertOk();
        $this->assertGreaterThan(0, $response->getMaxAge());
        $this->assertLessThanOrEqual(5, $response->getMaxAge());
        $this->assertLessThanOrEqual(5, (int) $response->headers->getCacheControlDirective('s-maxage'));
        $before = collect($response->viewData('countryOverview'))->keyBy('slug');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 13:00:05', 'UTC'));
        $after = collect($this->get($catalog->url('en', 'AU'))->assertOk()->viewData('countryOverview'))->keyBy('slug');
        $this->assertSame('2026-10-12', $before['sydney-2147714']['today']['date']->format('Y-m-d'));
        $this->assertSame('2026-10-13', $after['sydney-2147714']['today']['date']->format('Y-m-d'));
        $this->assertSame('2026-10-12', $after['perth-2063523']['today']['date']->format('Y-m-d'));
    }

    public function test_country_calculation_is_bounded_and_prioritizes_time_zone_coverage(): void
    {
        $calculator = app(PrayerPageService::class);
        $boundedService = Mockery::mock(PrayerPageService::class)->makePartial();
        $boundedService->shouldReceive('schedule')->times(8)->andReturnUsing(function ($slug, $city, $date, $days) use ($calculator) {
            $this->assertSame(1, $days);
            $this->assertSame($city['timezone'], $date->timezoneName);

            return $calculator->schedule($slug, $city, $date, $days);
        });
        $this->app->instance(PrayerPageService::class, $boundedService);
        $catalog = app(PrayerPageCatalog::class);
        $this->assertGreaterThan(8, count($catalog->cities('US')));
        $overview = collect($this->get($catalog->url('en', 'US'))->assertOk()->viewData('countryOverview'));
        $this->assertCount(8, $overview);
        $expectedZones = min(8, collect($catalog->cities('US'))->pluck('timezone')->unique()->count());
        $this->assertCount($expectedZones, $overview->pluck('timezone')->unique());
    }

    public function test_country_is_not_publicly_cached_during_the_last_fraction_of_a_local_day(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 12:59:59.500000', 'UTC'));
        $response = $this->get(app(PrayerPageCatalog::class)->url('en', 'AU'))->assertOk();
        $this->assertSame(0, $response->getMaxAge());
        $this->assertSame(0, (int) $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function test_world_directory_never_calculates_city_schedules(): void
    {
        $service = Mockery::mock(PrayerPageService::class);
        $service->shouldNotReceive('schedule');
        $this->app->instance(PrayerPageService::class, $service);
        $response = $this->get(app(PrayerPageCatalog::class)->url('en'))->assertOk();
        $this->assertSame([], $response->viewData('countryOverview'));
        $this->assertSame(3600, $response->getMaxAge());
        $response->assertSee('Prayer times around the world');
    }

    public function test_overview_is_translated_in_all_six_languages_with_stable_city_selection(): void
    {
        $catalog = app(PrayerPageCatalog::class);
        $selected = null;
        foreach (config('prayer_pages.locales') as $locale => $settings) {
            $response = $this->get($catalog->url($locale, 'AU'))->assertOk();
            $overview = $response->viewData('countryOverview');
            $slugs = array_column($overview, 'slug');
            $this->assertSame($selected ?? $slugs, $slugs);
            $selected = $slugs;
            $response->assertSee(__('prayer_pages.country_today_heading', ['country' => $catalog->countries()['AU']['names'][$locale]], $locale))
                ->assertSee(__('prayer_pages.city', [], $locale))
                ->assertSee(__('prayer_pages.timezone', [], $locale))
                ->assertSee('<html lang="'.$settings['language_tag'].'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'">', false)
                ->assertDontSee('prayer_pages.');
            foreach (['country_today_heading', 'country_today_intro', 'country_time_note', 'country_calculation_text', 'city', 'timezone'] as $key) {
                $this->assertTrue(app('translator')->hasForLocale('prayer_pages.'.$key, $locale), "$locale/$key");
            }
        }
    }

    public function test_polar_country_row_shows_unavailable_times_and_local_guidance(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-21 12:00:00', 'UTC'));
        $city = app(PrayerPageCatalog::class)->cities('NO')['oslo-3143244'];
        $city['latitude'] = 69.6492;
        $city['longitude'] = 18.9553;
        $city['names'] = array_fill_keys(array_keys(config('prayer_pages.locales')), 'Tromsø');
        config(['prayer_world.countries.NO.cities' => ['tromso-test' => $city]]);
        $response = $this->get(app(PrayerPageCatalog::class)->url('en', 'NO'))->assertOk();
        $today = $response->viewData('countryOverview')[0]['today'];
        $this->assertTrue($today['polar']);
        $this->assertSame(['—'], array_values(array_unique($today['times'])));
        $response->assertSee(__('prayer_pages.polar_note', [], 'en'))->assertSee('Tromsø');
    }
}
