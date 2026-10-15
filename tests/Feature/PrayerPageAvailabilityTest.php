<?php

namespace Tests\Feature;

use App\Services\Prayer\PrayerPageCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PrayerPageAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_reported_cuenca_page_is_available_on_the_old_crawl_date_today_and_a_future_date(): void
    {
        $url = 'https://salatime.net/hi/prayer-times/ecuador/cuenca-3658666';
        foreach (['2026-10-06', '2026-10-12', '2027-01-15'] as $date) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse($date.' 12:00:00', 'America/Guayaquil'));
            $response = $this->get($url)->assertOk();
            $response->assertSee('<link rel="canonical" href="'.$url.'">', false)
                ->assertSee('<html lang="hi" dir="ltr">', false)
                ->assertSee('America/Guayaquil')
                ->assertDontSee('prayer_pages.');
            $today = $response->viewData('today');
            $this->assertSame($date, $today['date']->format('Y-m-d'));
            $this->assertFalse($today['polar']);
            $this->assertSame(['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'], array_keys($today['times']));
            foreach ($today['times'] as $prayer => $time) {
                $this->assertMatchesRegularExpression('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time, "$date/$prayer");
                $response->assertSee($time);
            }
        }
    }

    public function test_cuenca_is_available_with_clean_canonicals_and_timings_in_all_six_languages(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-12 12:00:00', 'America/Guayaquil'));
        $catalog = app(PrayerPageCatalog::class);
        $locales = config('prayer_pages.locales');
        $this->assertCount(6, $locales);
        foreach ($locales as $locale => $settings) {
            $url = $catalog->url($locale, 'EC', 'cuenca-3658666');
            $this->assertStringNotContainsString('/public/', $url);
            $this->assertStringNotContainsString(':443', $url);
            $response = $this->get($url)->assertOk();
            $response->assertSee('<link rel="canonical" href="'.$url.'">', false)
                ->assertSee('<html lang="'.$settings['language_tag'].'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'">', false)
                ->assertDontSee('prayer_pages.');
            foreach ($locales as $alternate => $alternateSettings) {
                $response->assertSee('hreflang="'.$alternateSettings['language_tag'].'" href="'.$catalog->url($alternate, 'EC', 'cuenca-3658666').'"', false);
            }
            $times = $response->viewData('today')['times'];
            $this->assertCount(6, $times);
            foreach ($times as $prayer => $time) {
                $this->assertMatchesRegularExpression('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time, "$locale/$prayer");
                $response->assertSee($time);
            }
        }
    }
}
