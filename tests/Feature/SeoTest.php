<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_landing_page_exposes_canonical_metadata_and_structured_data(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('<title>'.e(config('seo.title')).'</title>', false)
            ->assertSee('<link rel="canonical" href="'.config('seo.site_url').'/">', false)
            ->assertSee('property="og:image:width" content="1200"', false)
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('"@type":"MobileApplication"', false)
            ->assertSee('"operatingSystem":"Android"', false);
    }

    public function test_sitemap_is_valid_xml_and_contains_canonical_public_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
            ->assertSee('<loc>'.config('seo.site_url').'/</loc>', false)
            ->assertSee('<loc>'.config('seo.site_url').'/privacy-policy</loc>', false);
    }

    public function test_missing_pages_return_a_real_404_and_are_not_indexable(): void
    {
        $this->get('/codex-seo-missing-page')
            ->assertNotFound()
            ->assertSee('name="robots" content="noindex,nofollow,noarchive"', false);
    }

    public function test_authentication_page_is_not_indexable(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('name="robots" content="noindex,nofollow,noarchive"', false);
    }

    public function test_french_city_page_is_server_rendered_with_local_prayer_times(): void
    {
        $canonical = config('seo.site_url').'/fr/horaires-priere/maroc/fes';

        $response = $this->get('/fr/horaires-priere/maroc/fes');

        $response
            ->assertOk()
            ->assertHeader('Cache-Control')
            ->assertSee('<html lang="fr" dir="ltr">', false)
            ->assertSee('<title>Horaires de prière à Fès, Maroc aujourd’hui | SalaTime</title>', false)
            ->assertSee('<link rel="canonical" href="'.$canonical.'">', false)
            ->assertSee('hreflang="ar"', false)
            ->assertSee('Calendrier des prières sur 7 jours à Fès')
            ->assertSee('méthode Maroc')
            ->assertSee('type="application/ld+json"', false)
            ->assertSee('FAQPage');

        $this->assertLessThanOrEqual(60, $response->getMaxAge());
    }

    public function test_arabic_city_page_uses_rtl_and_translated_content(): void
    {
        $this->get('/ar/prayer-times/morocco/casablanca')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false)
            ->assertSee('مواقيت الصلاة في الدار البيضاء')
            ->assertSee('الصلاة القادمة');
    }

    public function test_city_directory_links_to_curated_city_pages(): void
    {
        $this->get('/es/horarios-oracion/marruecos')
            ->assertOk()
            ->assertSee('Horarios de oración en Marruecos')
            ->assertSee('/es/horarios-oracion/marruecos/casablanca', false)
            ->assertSee('/es/horarios-oracion/marruecos/fes', false);
    }

    public function test_every_configured_city_page_is_available_in_every_locale(): void
    {
        foreach (config('prayer_pages.locales') as $locale => $settings) {
            $prefix = '/'.trim($settings['prefix'], '/');

            $this->get($prefix)
                ->assertOk()
                ->assertSee('hreflang="x-default"', false);

            foreach (config('prayer_pages.cities') as $slug => $city) {
                $this->get($prefix.'/'.$slug)
                    ->assertOk()
                    ->assertSee($city['names'][$locale])
                    ->assertSee('<link rel="canonical" href="'.config('seo.site_url').$prefix.'/'.$slug.'">', false);
            }
        }
    }

    public function test_unknown_prayer_city_returns_404(): void
    {
        $this->get('/fr/horaires-priere/maroc/ville-inconnue')->assertNotFound();
    }

    public function test_sitemap_contains_localized_prayer_city_pages(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.config('seo.site_url').'/fr/horaires-priere/maroc/fes</loc>', false)
            ->assertSee('<loc>'.config('seo.site_url').'/ar/prayer-times/morocco/casablanca</loc>', false)
            ->assertSee('<loc>'.config('seo.site_url').'/es/horarios-oracion/marruecos/rabat</loc>', false);
    }
}
