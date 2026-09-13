<?php

namespace Tests\Feature;

use App\Services\Prayer\PrayerPageCatalog;
use Illuminate\Support\Arr;
use Tests\TestCase;

class PublicLanguageNavigationTest extends TestCase
{
    public function test_homepage_removes_donation_and_offers_the_calendar_languages(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertDontSee('id="donate"', false)
            ->assertDontSee('href="#donate"', false)
            ->assertDontSee('/api/donation', false);

        foreach (['en', 'ar', 'bn', 'hi', 'es', 'fr'] as $locale) {
            $response->assertSee('data-lang="'.$locale.'"', false);
            $this->assertArrayHasKey($locale, config('prayer_pages.locales'));
        }
    }

    public function test_added_languages_have_complete_translations_and_matching_placeholders(): void
    {
        $english = Arr::dot(require lang_path('en/prayer_pages.php'));
        foreach (['bn', 'hi'] as $locale) {
            $translations = Arr::dot(require lang_path($locale.'/prayer_pages.php'));
            $this->assertSame(array_keys($english), array_keys($translations));
            foreach ($english as $key => $value) {
                preg_match_all('/:[a-z_]+/', $value, $expected);
                preg_match_all('/:[a-z_]+/', $translations[$key], $actual);
                $this->assertEqualsCanonicalizing($expected[0], $actual[0], "$locale.$key");
                $this->assertNotSame('', $translations[$key]);
            }
        }
    }

    public function test_language_picker_preserves_world_country_and_city_context_in_all_six_languages(): void
    {
        $catalog = app(PrayerPageCatalog::class);
        foreach (config('prayer_pages.locales') as $locale => $settings) {
            foreach ([[null, null], ['IN', null], ['FR', 'paris-2988507'], ['MA', 'fes']] as [$country, $city]) {
                $response = $this->get($catalog->url($locale, $country, $city))->assertOk()
                    ->assertSee('lang="'.$locale.'" dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false)
                    ->assertDontSee('prayer_pages.');
                $dom = new \DOMDocument;
                @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
                $xpath = new \DOMXPath($dom);
                $links = $xpath->query('//details[contains(@class,"language-picker")]//a');
                $this->assertSame(6, $links->length);
                $this->assertSame(1, $xpath->query('//details[contains(@class,"language-picker")]//a[@aria-current="page"]')->length);
                foreach ($links as $link) {
                    $this->assertSame($catalog->url($link->getAttribute('lang'), $country, $city), $link->getAttribute('href'));
                }
                $this->assertSame(7, $xpath->query('//head/link[@rel="alternate"]')->length);
            }
        }
    }
}
