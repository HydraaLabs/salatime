<?php

namespace Tests\Feature;

use App\Services\Mosque\NearbyMosqueService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NearbyMosqueApiTest extends TestCase
{
    private const MAIN = 'https://overpass.openstreetmap.fr/api/interpreter';
    private const MIRROR = 'https://overpass-api.de/api/interpreter';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function overpass(): array
    {
        return ['elements' => [
            // About 1.1 km north of the searched point.
            ['type' => 'node', 'lat' => 34.005, 'lon' => -5.03,
                'tags' => ['name' => 'Mosquée Al Fath', 'name:ar' => 'مسجد الفتح', 'addr:city' => 'Fès']],
            // About 330 m east, mapped as a building (way with a center).
            ['type' => 'way', 'center' => ['lat' => 33.996, 'lon' => -5.0265], 'tags' => ['name' => 'Masjid Nour']],
            // About 4.4 km south: only inside a 5 km search.
            ['type' => 'node', 'lat' => 33.956, 'lon' => -5.03, 'tags' => []],
            ['type' => 'node', 'tags' => ['name' => 'No coordinates']],
        ]];
    }

    public function test_live_results_are_filtered_sorted_localized_and_cached(): void
    {
        Http::fake([self::MAIN => Http::response($this->overpass())]);

        $response = $this->getJson('/api/mosques/nearby?lat=33.997&lng=-5.03&radius=2&lang=ar');

        $response->assertOk()->assertJsonPath('status', true)->assertJsonPath('source', 'live');
        $mosques = $response->json('mosques');
        $this->assertSame(['Masjid Nour', 'مسجد الفتح'], array_column($mosques, 'name'));
        $this->assertSame('Fès', $mosques[1]['address']);
        $this->assertGreaterThan($mosques[0]['distance'], $mosques[1]['distance']);
        $this->assertNotNull($response->headers->get('X-Robots-Tag'));

        // Another point of the same tile and a larger radius reuse the cache.
        $wider = $this->getJson('/api/mosques/nearby?lat=33.993&lng=-5.034&radius=5');
        $wider->assertOk()->assertJsonPath('source', 'cache')->assertJsonCount(3, 'mosques');
        $this->assertNull($wider->json('mosques.2.name'));
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request) => str_contains($request['data'], 'around:7000,34,-5.04')
            && str_contains($request->header('User-Agent')[0], 'SalaTime'));
    }

    public function test_a_busy_main_server_falls_back_to_a_mirror(): void
    {
        Http::fake([
            self::MAIN => Http::response('All query workers are busy', 502),
            self::MIRROR => Http::response($this->overpass()),
        ]);

        $this->getJson('/api/mosques/nearby?lat=33.997&lng=-5.03&radius=2')
            ->assertOk()->assertJsonPath('source', 'live')->assertJsonCount(2, 'mosques');
        Http::assertSentCount(2);
    }

    public function test_an_expired_tile_is_still_served_when_every_server_fails(): void
    {
        $service = app(NearbyMosqueService::class);
        [$lat, $lng] = $service->tile(33.997, -5.03);
        Cache::put(sprintf('nearby_mosques:v1:%.2f:%.2f', $lat, $lng), [
            'fetched_at' => time() - 10 * 86400,
            'elements' => [['lat' => 33.996, 'lng' => -5.0265, 'names' => ['name' => 'Masjid Nour'], 'address' => '']],
        ], now()->addDays(60));
        Http::fake(['*' => Http::response('', 500)]);

        $this->getJson('/api/mosques/nearby?lat=33.997&lng=-5.03&radius=2')
            ->assertOk()->assertJsonPath('source', 'stale')->assertJsonPath('mosques.0.name', 'Masjid Nour');
    }

    public function test_an_unknown_tile_reports_unavailability_when_every_server_fails(): void
    {
        Http::fake(['*' => Http::response('', 504)]);

        $this->getJson('/api/mosques/nearby?lat=10&lng=10')
            ->assertStatus(503)->assertJsonPath('status', false)->assertHeader('Retry-After', '60');
    }

    public function test_invalid_coordinates_and_radius_are_rejected_without_a_request(): void
    {
        Http::fake();

        foreach (['lat=91&lng=0', 'lat=0', 'lat=0&lng=0&radius=6', 'lat=0&lng=0&radius=0', 'lat=0&lng=0&lang=EN-us'] as $query) {
            $this->getJson('/api/mosques/nearby?'.$query)->assertStatus(422);
        }
        Http::assertNothingSent();
    }
}
