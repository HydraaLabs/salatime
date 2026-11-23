<?php

namespace Tests\Feature;

use App\Services\Prayer\PrayerCalendarService;
use App\Services\Prayer\PrayerPageCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebsiteOptimizationsAndFixesTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_landing_page_exposes_brand_dual_platform_schema_popular_cities_and_no_cookies(): void
    {
        $response = $this->get('/')->assertOk();

        $this->assertSame([], $response->headers->getCookies());
        $response->assertHeader('Cache-Control');

        $html = $response->getContent();
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $this->assertNotEmpty($matches);
        $schema = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $types = array_column($schema['@graph'], '@type');
        $this->assertSame(['WebSite', 'Organization', 'MobileApplication', 'MobileApplication'], $types);

        $org = $schema['@graph'][1];
        $this->assertSame('ImageObject', $org['logo']['@type']);
        $this->assertContains('https://github.com/HydraaLabs/salatime', $org['sameAs']);
        $this->assertContains('https://github.com/HydraaLabs/salatime_app', $org['sameAs']);
        $this->assertContains(config('seo.app_store_url'), $org['sameAs']);

        $operatingSystems = [
            $schema['@graph'][2]['operatingSystem'],
            $schema['@graph'][3]['operatingSystem'],
        ];
        $this->assertSame(['Android', 'iOS'], $operatingSystems);

        // Popular city links and explicit geolocation trigger (no prompt on page load).
        $response->assertSee('id="prayer-detect-btn"', false);
        $response->assertSee('/en/prayer-times/france/paris-2988507', false);
        $response->assertSee('/en/prayer-times/morocco/casablanca', false);
        $response->assertSee('n.english || n.name', false);
    }

    public function test_public_legal_and_sitemap_routes_do_not_issue_session_cookies(): void
    {
        foreach (['/sitemap.xml', '/privacy-policy', '/terms-and-conditions', '/support'] as $uri) {
            $response = $this->get($uri)->assertOk();
            $this->assertSame([], $response->headers->getCookies(), $uri);
            $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'), $uri);
        }
    }

    public function test_english_prayer_pages_link_to_canonical_home_and_embed_daily_times_in_faq(): void
    {
        $response = $this->get('/en/prayer-times/france/paris-2988507')->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('<a class="brand" href="'.config('seo.site_url').'/"', $html);
        $this->assertStringNotContainsString('href="'.config('seo.site_url').'/?lang=en"', $html);

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $graph = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR)['@graph'];
        $faq = collect($graph)->firstWhere('@type', 'FAQPage');
        $firstAnswer = $faq['mainEntity'][0]['acceptedAnswer']['text'];
        $this->assertMatchesRegularExpression('/\d{2}:\d{2}/', $firstAnswer);
    }

    public function test_storage_route_blocks_path_traversal(): void
    {
        $this->get('/storage/../../.env')->assertNotFound();
        $this->get('/storage/../logs/laravel.log')->assertNotFound();
        $this->get('/storage/..%2F..%2F.env')->assertNotFound();
    }

    public function test_prayer_calendar_and_automatic_api_respect_dst_transition_day(): void
    {
        $service = app(PrayerCalendarService::class);
        $calendar = $service->automatic([
            'type' => 'automatic',
            'date' => '2026-03-29',
            'lat' => 48.85341,
            'lng' => 2.3488,
            'prayer_method' => 12,
            'school' => 'STANDARD',
            'timezone' => 'Europe/Paris',
        ]);

        $byDate = collect($calendar['days'])->keyBy('date');
        $minute = fn (string $time) => (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
        $jump = $minute($byDate['2026-03-29']['zuhr_start']) - $minute($byDate['2026-03-28']['zuhr_start']);
        $this->assertGreaterThanOrEqual(58, $jump);
        $this->assertLessThanOrEqual(62, $jump);

        $apiDayBefore = $this->postJson('/api/today-prayer-time', [
            'type' => 'automatic',
            'date' => '2026-03-28',
            'lat' => 48.85341,
            'lng' => 2.3488,
            'prayer_method' => 12,
            'school' => 'STANDARD',
            'timezone' => 'Europe/Paris',
        ])->assertOk()->json('data.zuhr_start');

        $apiDstDay = $this->postJson('/api/today-prayer-time', [
            'type' => 'automatic',
            'date' => '2026-03-29',
            'lat' => 48.85341,
            'lng' => 2.3488,
            'prayer_method' => 12,
            'school' => 'STANDARD',
            'timezone' => 'Europe/Paris',
        ])->assertOk()->json('data.zuhr_start');

        $apiJump = $minute($apiDstDay) - $minute($apiDayBefore);
        $this->assertGreaterThanOrEqual(58, $apiJump);
        $this->assertLessThanOrEqual(62, $apiJump);
    }

    public function test_prayer_by_location_validates_inputs_uses_regional_method_caches_geocoding_and_skips_sunrise(): void
    {
        $this->getJson('/api/prayer-by-location?lat=999&lng=0&timezone=Not/A_Zone')
            ->assertStatus(422);

        Cache::flush();
        // Set local time between Fajr and Sunrise in Casablanca so Sunrise is the next clock time,
        // verifying that Dhuhr (not Sunrise) is highlighted as the next prayer.
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-10 06:00:00', 'Africa/Casablanca'));

        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                'address' => ['city' => 'Casablanca', 'country' => 'Morocco'],
            ], 200),
        ]);

        $first = $this->getJson('/api/prayer-by-location?lat=33.5731&lng=-7.5898&timezone=Africa/Casablanca&lang=fr')
            ->assertOk()
            ->json();

        $this->assertTrue($first['status']);
        $this->assertSame('Casablanca, Morocco', $first['city']);
        $this->assertSame('MOROCCO', $first['method']);
        $this->assertSame(config('seo.site_url').'/fr/horaires-priere/maroc/casablanca', $first['nearest_city_url']);

        $next = collect($first['prayers'])->firstWhere('is_next', true);
        $this->assertNotNull($next);
        $this->assertSame('Dhuhr', $next['name']);

        // Second hit on the same tile must reuse the cached reverse geocode without calling Nominatim again.
        $this->getJson('/api/prayer-by-location?lat=33.5732&lng=-7.5897&timezone=Africa/Casablanca&lang=fr')
            ->assertOk();

        Http::assertSentCount(1);
    }

    public function test_catalog_nearest_city_and_robots_disallow_legacy_directories(): void
    {
        $catalog = app(PrayerPageCatalog::class);
        $nearest = $catalog->nearestCity(48.8566, 2.3522);
        $this->assertNotNull($nearest);
        $this->assertSame('FR', $nearest['country_code']);
        $this->assertSame('paris-2988507', $nearest['slug']);

        $robots = file_get_contents(public_path('robots.txt'));
        $this->assertStringContainsString('Disallow: /documentation/', $robots);
        $this->assertStringContainsString('Disallow: /demo/', $robots);
    }
}
