<?php

namespace App\Services\Prayer;

class PrayerPageCatalog
{
    private ?array $cachedCountries = null;

    /** @var array<string, array<string, string>> */
    private array $countryCodesByLocale = [];

    public function countries(): array
    {
        if ($this->cachedCountries !== null) {
            return $this->cachedCountries;
        }

        $morocco = [
            'names' => array_map(fn ($locale) => $locale['country_name'], config('prayer_pages.locales')),
            'slugs' => ['fr' => 'maroc', 'ar' => 'morocco', 'en' => 'morocco', 'es' => 'marruecos', 'bn' => 'morocco', 'hi' => 'morocco'],
            'method' => config('prayer_pages.method'),
            'school' => 'STANDARD',
            'cities' => array_map(fn ($city) => $city + ['timezone' => config('prayer_pages.timezone')], config('prayer_pages.cities')),
        ];

        return $this->cachedCountries = ['MA' => $morocco] + config('prayer_world.countries');
    }

    public function countryCode(string $slug, string $locale): ?string
    {
        if (! isset($this->countryCodesByLocale[$locale])) {
            $map = [];
            foreach ($this->countries() as $code => $country) {
                $map[$country['slugs'][$locale]] = $code;
            }
            $this->countryCodesByLocale[$locale] = $map;
        }

        return $this->countryCodesByLocale[$locale][$slug] ?? null;
    }

    public function url(string $locale, ?string $code = null, ?string $city = null): string
    {
        $prefix = config("prayer_pages.locales.$locale.world_prefix");
        if ($code !== null) {
            $prefix .= '/'.$this->countries()[$code]['slugs'][$locale];
        }

        return rtrim(config('seo.site_url'), '/').'/'.$prefix.($city === null ? '' : '/'.$city);
    }

    public function cities(string $code): array
    {
        $country = $this->countries()[$code];

        return array_map(fn ($city) => $city + [
            'country_code' => $code,
            'method' => $country['method'],
            'school' => $country['school'],
        ], $country['cities']);
    }

    /**
     * Find the closest catalog city to the given coordinates.
     *
     * @return array{country_code: string, slug: string, method: string, school: string, timezone: string, names: array<string, string>, country_names: array<string, string>, distance_km: float}|null
     */
    public function nearestCity(float $latitude, float $longitude): ?array
    {
        $best = null;
        $bestDistance = INF;
        $lat1 = deg2rad($latitude);
        $lng1 = deg2rad($longitude);

        foreach ($this->countries() as $code => $country) {
            foreach ($country['cities'] as $slug => $city) {
                $dLat = deg2rad($city['latitude']) - $lat1;
                $dLng = deg2rad($city['longitude']) - $lng1;
                $a = sin($dLat / 2) ** 2
                    + cos($lat1) * cos(deg2rad($city['latitude'])) * sin($dLng / 2) ** 2;
                $distance = 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));

                if ($distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = [
                        'country_code' => $code,
                        'slug' => $slug,
                        'method' => $country['method'],
                        'school' => $country['school'],
                        'timezone' => $city['timezone'],
                        'names' => $city['names'],
                        'country_names' => $country['names'],
                        'distance_km' => $distance,
                    ];
                }
            }
        }

        return $best;
    }
}
