<?php

namespace App\Services\Mosque;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Nearby mosques from OpenStreetMap (Overpass), shielded by a tile cache.
 *
 * The world is split into 0.02° tiles (about 2 km). Each tile is fetched once
 * with a radius large enough for any point of the tile and any search radius
 * up to MAX_RADIUS_KM, then filtered per request. A tile stays fresh for
 * FRESH_DAYS and is still served for KEEP_DAYS when every Overpass server is
 * unavailable, which happens regularly on the free public instances.
 */
class NearbyMosqueService
{
    public const MAX_RADIUS_KM = 5;
    public const TILE_DEGREES = 0.02;
    public const FETCH_RADIUS_METERS = 7000;
    public const FRESH_DAYS = 7;
    public const KEEP_DAYS = 60;
    public const ENDPOINTS = [
        // OpenStreetMap France stayed responsive (worldwide data) while the
        // main server and the kumi mirror were saturated on 2026-10-16.
        'https://overpass.openstreetmap.fr/api/interpreter',
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
    ];
    private const USER_AGENT = 'SalaTime/1.0 (+https://salatime.net; contact@salatime.net)';
    private const BUDGET_SECONDS = 24;

    public function __construct(private Repository $cache)
    {
    }

    /**
     * @return array{source: string, fetched_at: ?string, mosques: list<array<string, mixed>>}|null
     */
    public function nearby(float $lat, float $lng, int $radiusKm, string $language): ?array
    {
        [$tileLat, $tileLng] = $this->tile($lat, $lng);
        $key = sprintf('nearby_mosques:v1:%.2f:%.2f', $tileLat, $tileLng);
        $entry = $this->cache->get($key);
        $fresh = is_array($entry) && ($entry['fetched_at'] ?? 0) > time() - self::FRESH_DAYS * 86400;
        $source = 'cache';

        if (! $fresh) {
            $elements = $this->withTileLock($key, fn () => $this->fetch($tileLat, $tileLng));
            if ($elements !== null) {
                $entry = ['fetched_at' => time(), 'elements' => $elements];
                $this->cache->put($key, $entry, now()->addDays(self::KEEP_DAYS));
                $source = 'live';
            } elseif (is_array($entry)) {
                $source = 'stale';
            } else {
                return null;
            }
        }

        return [
            'source' => $source,
            'fetched_at' => gmdate('c', (int) $entry['fetched_at']),
            'mosques' => $this->filter($entry['elements'], $lat, $lng, $radiusKm * 1000, $language),
        ];
    }

    /** @return array{0: float, 1: float} */
    public function tile(float $lat, float $lng): array
    {
        $round = fn (float $value) => round(round($value / self::TILE_DEGREES) * self::TILE_DEGREES, 2);

        return [$round($lat), $round($lng)];
    }

    private function withTileLock(string $key, callable $fetch): ?array
    {
        try {
            $lock = $this->cache->lock($key.':lock', self::BUDGET_SECONDS + 6);
            if (! $lock->get()) {
                // Another request is fetching this tile; reuse what it stores.
                $lock->block(self::BUDGET_SECONDS);
                $lock->release();
                $entry = $this->cache->get($key);

                return is_array($entry) ? $entry['elements'] : null;
            }
        } catch (Throwable) {
            return $fetch();
        }
        try {
            return $fetch();
        } finally {
            $lock->release();
        }
    }

    /** @return list<array<string, mixed>>|null */
    private function fetch(float $lat, float $lng): ?array
    {
        $radius = self::FETCH_RADIUS_METERS;
        $query = '[out:json][timeout:20];('
            ."node[\"amenity\"=\"place_of_worship\"][\"religion\"=\"muslim\"](around:$radius,$lat,$lng);"
            ."way[\"amenity\"=\"place_of_worship\"][\"religion\"=\"muslim\"](around:$radius,$lat,$lng);"
            .');out center;';
        $deadline = microtime(true) + self::BUDGET_SECONDS;
        // First server twice (it is often only momentarily full), then the others.
        $attempts = [self::ENDPOINTS[0], self::ENDPOINTS[1], self::ENDPOINTS[0], self::ENDPOINTS[2]];
        foreach ($attempts as $index => $endpoint) {
            $remaining = $deadline - microtime(true);
            if ($remaining < 3) {
                break;
            }
            if ($index === 2) {
                usleep(1_500_000);
            }
            try {
                $response = Http::asForm()
                    ->withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => 'application/json'])
                    ->connectTimeout(4)
                    ->timeout((int) max(3, min(10, $remaining - 1)))
                    ->post($endpoint, ['data' => $query]);
                $elements = $response->successful() ? $response->json('elements') : null;
                if (is_array($elements)) {
                    return $this->normalize($elements);
                }
            } catch (Throwable) {
                // Try the next server.
            }
        }

        return null;
    }

    /** Keep only what the app shows, so cached tiles stay small. */
    private function normalize(array $elements): array
    {
        $mosques = [];
        foreach ($elements as $element) {
            $lat = $element['lat'] ?? $element['center']['lat'] ?? null;
            $lng = $element['lon'] ?? $element['center']['lon'] ?? null;
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                continue;
            }
            $tags = is_array($element['tags'] ?? null) ? $element['tags'] : [];
            $names = array_filter(
                $tags,
                fn ($value, $tag) => is_string($value) && ($tag === 'name' || str_starts_with($tag, 'name:')),
                ARRAY_FILTER_USE_BOTH,
            );
            $address = trim(implode(' ', array_filter([
                $tags['addr:housenumber'] ?? null,
                $tags['addr:street'] ?? null,
                $tags['addr:city'] ?? null,
            ], 'is_string')));
            $mosques[] = ['lat' => (float) $lat, 'lng' => (float) $lng, 'names' => $names, 'address' => $address];
        }

        return $mosques;
    }

    private function filter(array $elements, float $lat, float $lng, int $radius, string $language): array
    {
        $results = [];
        foreach ($elements as $mosque) {
            $distance = $this->distance($lat, $lng, $mosque['lat'], $mosque['lng']);
            if ($distance > $radius) {
                continue;
            }
            $names = $mosque['names'];
            $results[] = [
                'name' => $names['name:'.$language] ?? $names['name'] ?? null,
                'address' => $mosque['address'] !== '' ? $mosque['address'] : null,
                'lat' => $mosque['lat'],
                'lng' => $mosque['lng'],
                'distance' => (int) round($distance),
            ];
        }
        usort($results, fn ($a, $b) => $a['distance'] <=> $b['distance']);

        return $results;
    }

    private function distance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rad = M_PI / 180;
        $dLat = ($lat2 - $lat1) * $rad;
        $dLng = ($lng2 - $lng1) * $rad;
        $a = sin($dLat / 2) ** 2 + cos($lat1 * $rad) * cos($lat2 * $rad) * sin($dLng / 2) ** 2;

        return 6371008.8 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
