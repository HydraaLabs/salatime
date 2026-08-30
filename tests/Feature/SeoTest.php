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
}
