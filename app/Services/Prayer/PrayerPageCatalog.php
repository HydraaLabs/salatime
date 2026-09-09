<?php

namespace App\Services\Prayer;

class PrayerPageCatalog
{
    public function countries(): array
    {
        $morocco = [
            'names' => array_map(fn ($locale) => $locale['country_name'], config('prayer_pages.locales')),
            'slugs' => ['fr' => 'maroc', 'ar' => 'morocco', 'en' => 'morocco', 'es' => 'marruecos'],
            'method' => config('prayer_pages.method'),
            'school' => 'STANDARD',
            'cities' => array_map(fn ($city) => $city + ['timezone' => config('prayer_pages.timezone')], config('prayer_pages.cities')),
        ];

        return ['MA' => $morocco] + config('prayer_world.countries');
    }

    public function countryCode(string $slug, string $locale): ?string
    {
        foreach ($this->countries() as $code => $country) {
            if ($country['slugs'][$locale] === $slug) {
                return $code;
            }
        }

        return null;
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
}
