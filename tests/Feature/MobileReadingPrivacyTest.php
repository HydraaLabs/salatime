<?php

namespace Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileReadingPrivacyTest extends TestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array',
                'mail.default' => 'array',
                'logging.default' => 'null',
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Http::preventStrayRequests();
        config([
            'settings.application.company_name' => 'SalaTime',
            'settings.application.privacy_policy' => '<p id="existing-policy">Existing general policy remains unchanged.</p>',
        ]);
    }

    public function test_reading_disclosure_is_complete_in_every_public_site_language(): void
    {
        $expected = ['title', 'updated', 'records', 'purpose', 'guest', 'retention', 'deletion'];
        $english = trans('reading_privacy', [], 'en');
        $this->assertIsArray($english);
        foreach (array_keys(config('prayer_pages.locales')) as $locale) {
            $text = trans('reading_privacy', [], $locale);
            $this->assertIsArray($text);
            $this->assertSame($expected, array_keys($text));
            foreach ($text as $key => $paragraph) {
                $this->assertIsString($paragraph);
                $this->assertNotEmpty(trim($paragraph));
                if ($locale !== 'en') {
                    $this->assertNotSame($english[$key], $paragraph, "$locale.$key must be translated");
                }
            }
        }
        $this->assertStringContainsString('repetition count', $english['records']);
        $this->assertStringContainsString('religious practice', $english['records']);
        $this->assertStringContainsString('statistics', $english['purpose']);
        $this->assertStringContainsString('not automatically merged', $english['guest']);
        $this->assertStringContainsString('zero-value records', $english['retention']);
        $this->assertStringContainsString('active database', $english['deletion']);
    }

    public function test_public_policy_keeps_existing_clauses_and_renders_accessible_language_sections(): void
    {
        $html = view('support.privacy-policy')->render();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[@id="existing-policy"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="account-and-cloud-data"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="reading-progress-data"]')->length);
        $this->assertStringContainsString('synchronized reading history and its synchronization records', $html);
        $this->assertStringContainsString('https://policies.google.com/privacy', $html);
        foreach (['en', 'fr', 'ar', 'es'] as $locale) {
            $nodes = $xpath->query('//*[@id="reading-progress-'.$locale.'"]');
            $this->assertSame(1, $nodes->length);
            $section = $nodes->item(0);
            $this->assertSame('details', $section->nodeName);
            $this->assertSame($locale, $section->getAttribute('lang'));
            $this->assertSame($locale === 'ar' ? 'rtl' : 'ltr', $section->getAttribute('dir'));
            $this->assertSame($locale === 'en', $section->hasAttribute('open'));
            foreach (trans('reading_privacy', [], $locale) as $text) {
                $this->assertStringContainsString($text, $section->textContent);
            }
        }
        $this->assertSame(0, $xpath->query('//*[@id="reading-progress-data"]//script')->length);
        if ($path = getenv('SALATIME_READING_PRIVACY_PREVIEW')) {
            $this->assertNotFalse(file_put_contents($path, $html));
        }
    }

    public function test_localized_disclosure_text_is_escaped(): void
    {
        trans('reading_privacy', [], 'fr');
        app('translator')->addLines(['reading_privacy.records' => '<img src=x onerror="alert(1)">'], 'fr');
        $html = view('support.partials.reading-privacy')->render();
        $this->assertStringContainsString('&lt;img src=x', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
    }

    public function test_account_and_data_deletion_request_is_public_in_all_site_languages(): void
    {
        $response = $this->get('/privacy-policy');
        $response->assertOk();
        $html = $response->getContent();
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[@id="delete-account"]')->length);
        $expected = ['title', 'request', 'link', 'subject', 'data_only', 'verification', 'scope'];
        $english = trans('account_deletion', [], 'en');
        foreach (array_keys(config('prayer_pages.locales')) as $locale) {
            $text = trans('account_deletion', [], $locale);
            $this->assertSame($expected, array_keys($text));
            $nodes = $xpath->query('//*[@id="delete-account-'.$locale.'"]');
            $this->assertSame(1, $nodes->length);
            $section = $nodes->item(0);
            $this->assertSame($locale, $section->getAttribute('lang'));
            $this->assertSame($locale === 'ar' ? 'rtl' : 'ltr', $section->getAttribute('dir'));
            $this->assertSame($locale === 'en', $section->hasAttribute('open'));
            foreach ($text as $key => $value) {
                $this->assertNotEmpty(trim($value));
                if ($locale !== 'en') {
                    $this->assertNotSame($english[$key], $value);
                }
                if ($key !== 'subject') {
                    $this->assertStringContainsString($value, $section->textContent);
                }
            }
            $links = $xpath->query('.//a', $section);
            $this->assertSame(1, $links->length);
            $this->assertSame('mailto:contact@salatime.net?subject='.rawurlencode($text['subject']), $links->item(0)->getAttribute('href'));
            $this->assertStringContainsString('contact@salatime.net', $links->item(0)->textContent);
        }
        $this->assertSame(0, $xpath->query('//*[@id="delete-account"]//form | //*[@id="delete-account"]//script')->length);
        $this->assertSame(4, substr_count($html, '<!--email_off-->'));
        $this->assertSame(4, substr_count($html, '<!--/email_off-->'));
        $this->assertStringContainsString('without reinstalling the app or signing in', $english['request']);
        $this->assertStringContainsString('keep your account and delete only data', $english['data_only']);
        $this->assertStringContainsString('Never send your password', $english['verification']);
        $this->assertStringContainsString('active account database', $english['scope']);
    }

    public function test_deletion_request_text_and_email_subject_are_escaped(): void
    {
        trans('account_deletion', [], 'fr');
        app('translator')->addLines([
            'account_deletion.request' => '<script>alert(1)</script>',
            'account_deletion.subject' => 'Test" onclick="alert(1)',
        ], 'fr');
        $html = view('support.partials.account-deletion')->render();
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('subject=Test%22%20onclick%3D%22alert%281%29', $html);
        $this->assertStringNotContainsString('onclick="', $html);
    }
}
