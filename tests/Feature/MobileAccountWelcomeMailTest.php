<?php

namespace Tests\Feature;

use App\Models\Mobile\MobileAccount;
use App\Notifications\Mobile\AccountWelcome;
use App\Services\Mobile\AccountLocale;
use DOMDocument;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class MobileAccountWelcomeMailTest extends TestCase
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
                'mail.mailers.mobile_accounts' => [],
                'mail.from' => ['address' => 'site@example.test', 'name' => 'SalaTime'],
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_welcome_renders_both_html_and_plain_text_without_network_delivery(): void
    {
        $email = $this->renderWelcome(' Amine ');
        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        $this->assertSame('Bienvenue sur SalaTime', $email->getSubject());
        $this->assertSame('reader@example.test', $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('<html lang="fr"', $html);
        $this->assertSame('Bonjour Amine,', $this->htmlDocument($html)->getElementsByTagName('h1')->item(0)->textContent);
        $this->assertStringContainsString('Bonjour Amine,', $text);
        $this->assertStringContainsString('Vos préférences vous suivent', $html);
        $this->assertStringContainsString('Vos préférences vous suivent', $text);
        $this->assertStringContainsString('à la suite de la création de votre compte SalaTime', $text);
        $this->assertStringNotContainsString('<table', $text);
        $this->assertStringNotContainsString('<style', $text);

        // Optional review artifact contains only the fictional name above.
        $directory = getenv('SALATIME_WELCOME_PREVIEW_DIR');
        if (is_string($directory) && $directory !== '') {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            $this->assertNotFalse(file_put_contents($directory.'/welcome-email.html', $html));
        }
    }

    public function test_account_name_is_escaped_in_html_and_readable_in_plain_text(): void
    {
        $name = 'Amine & Lina <img src=x onerror="alert(1)">';
        $email = $this->renderWelcome($name);
        $html = $email->getHtmlBody();

        $this->assertStringContainsString(e($name), $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('Bonjour '.$name.',', $email->getTextBody());
        $document = $this->htmlDocument($html);
        $this->assertSame(0, $document->getElementsByTagName('img')->length);
        $this->assertSame(0, $document->getElementsByTagName('script')->length);
        $this->assertSame('Bonjour '.$name.',', $document->getElementsByTagName('h1')->item(0)->textContent);
    }

    public function test_links_use_the_public_https_site_and_brand_has_a_standalone_fallback(): void
    {
        // The targeted account deployment does not require the separate website theme config.
        config(['brand' => [], 'app.url' => 'http://localhost:8877']);
        $email = $this->renderWelcome('Amine');
        $html = $email->getHtmlBody();
        $text = $email->getTextBody();

        $this->assertStringContainsString('#2f5233', strtolower($html));
        $document = $this->htmlDocument($html);
        $links = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = $anchor->getAttribute('href');
            $this->assertSame('https', parse_url($href, PHP_URL_SCHEME));
            $this->assertSame('salatime.net', parse_url($href, PHP_URL_HOST));
            $links[] = $href;
            $this->assertStringContainsString($href, $text);
        }
        $this->assertContains('https://salatime.net', $links);
        $this->assertContains('https://salatime.net/privacy-policy', $links);
        $this->assertStringNotContainsString('localhost', $html.$text);
        $this->assertStringNotContainsString('http://', $text);
    }

    public function test_welcome_uses_account_transport_and_sender_despite_general_smtp_overrides(): void
    {
        $this->configureDedicatedTransport();
        config([
            'mail.from' => ['address' => 'legacy@example.test', 'name' => 'Legacy site'],
            'mail.mailers.smtp.host' => 'legacy.example.test',
            'mail.mailers.smtp.username' => 'legacy-user',
            'mail.mailers.smtp.password' => 'legacy-test-password',
        ]);

        $email = $this->renderWelcome('Amine', 'mobile_accounts');

        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertSame('accounts@example.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('SalaTime accounts', $email->getFrom()[0]->getName());
        $this->assertSame('Bonjour Amine,', $this->htmlDocument($email->getHtmlBody())->getElementsByTagName('h1')->item(0)->textContent);
        $this->assertStringContainsString('Bonjour Amine,', $email->getTextBody());
    }

    public function test_incomplete_account_transport_uses_the_default_mailer_and_sender(): void
    {
        $this->configureDedicatedTransport();
        config(['mail.mailers.mobile_accounts.password' => null]);

        $email = $this->renderWelcome('Amine');

        $this->assertCount(0, Mail::mailer('mobile_accounts')->getSymfonyTransport()->messages());
        $this->assertSame('site@example.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('SalaTime', $email->getFrom()[0]->getName());
    }

    /** @dataProvider welcomeLanguages */
    public function test_welcome_uses_the_account_language_for_every_part_of_the_email(string $locale, string $subject, string $direction): void
    {
        // Per-account rendering must work independently of the website's current locale.
        app()->setLocale($locale === 'fr' ? 'en' : 'fr');
        $email = $this->renderWelcome('Amine & Lina', 'array', $locale);
        $html = $email->getHtmlBody();
        $text = $email->getTextBody();
        $copy = require lang_path($locale.'/mobile_welcome.php');
        $this->assertSame($subject, $email->getSubject());
        $this->assertCount(14, $copy);
        foreach ($copy as $key => $phrase) {
            $this->assertIsString($phrase);
            $this->assertNotSame('', $phrase);
            if ($key === 'subject') {
                continue;
            }
            $phrase = str_replace(':name', 'Amine & Lina', $phrase);
            if ($key === 'greeting') {
                $this->assertSame($phrase, $this->htmlDocument($html)->getElementsByTagName('h1')->item(0)->textContent);
            } else {
                $this->assertStringContainsString(e($phrase), $html, $locale.' HTML '.$key);
            }
            // The preheader and header tagline have no duplicate in the text alternative.
            if (! in_array($key, ['eyebrow', 'preheader', 'tagline'], true)) {
                $this->assertStringContainsString($phrase, $text, $locale.' text '.$key);
            }
        }
        $document = $this->htmlDocument($html);
        $root = $document->getElementsByTagName('html')->item(0);
        $this->assertSame($locale, $root->getAttribute('lang'));
        $this->assertSame($direction, $root->getAttribute('dir'));
        $this->assertSame('auto', $document->getElementsByTagName('bdi')->item(0)->getAttribute('dir'));
        $this->assertSame('Amine & Lina', $document->getElementsByTagName('bdi')->item(0)->textContent);
        $this->assertStringNotContainsString(':name', $html.$text);
        if ($locale !== 'fr') {
            $this->assertStringNotContainsString('Politique de confidentialité', $html.$text);
            $this->assertStringNotContainsString('Vos préférences vous suivent', $html.$text);
        }
        $directory = getenv('SALATIME_WELCOME_PREVIEW_DIR');
        if (is_string($directory) && $directory !== '') {
            if (! is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            $this->assertNotFalse(file_put_contents($directory.'/welcome-'.$locale.'.html', $html));
        }
    }

    public static function welcomeLanguages(): array
    {
        return [
            'English' => ['en', 'Welcome to SalaTime', 'ltr'],
            'French' => ['fr', 'Bienvenue sur SalaTime', 'ltr'],
            'Arabic' => ['ar', 'مرحبًا بك في SalaTime', 'rtl'],
            'Turkish' => ['tr', 'SalaTime’a hoş geldiniz', 'ltr'],
            'Urdu' => ['ur', 'SalaTime میں خوش آمدید', 'rtl'],
            'Indonesian' => ['id', 'Selamat datang di SalaTime', 'ltr'],
            'Malay' => ['ms', 'Selamat datang ke SalaTime', 'ltr'],
            'Spanish' => ['es', 'Te damos la bienvenida a SalaTime', 'ltr'],
            'Bengali' => ['bn', 'SalaTime-এ স্বাগতম', 'ltr'],
            'Persian' => ['fa', 'به SalaTime خوش آمدید', 'rtl'],
        ];
    }

    public function test_translations_cover_exactly_the_selectable_account_languages(): void
    {
        $this->assertSame(AccountLocale::SUPPORTED, array_column(self::welcomeLanguages(), 0));
        $keys = array_keys(require lang_path('en/mobile_welcome.php'));
        foreach (AccountLocale::SUPPORTED as $locale) {
            $this->assertSame($keys, array_keys(require lang_path($locale.'/mobile_welcome.php')));
        }
    }

    /** @dataProvider fallbackLanguages */
    public function test_missing_or_unsupported_account_language_falls_back_to_english(?string $locale): void
    {
        app()->setLocale('fr');
        $email = $this->renderWelcome('Amine', 'array', $locale);
        $this->assertSame('Welcome to SalaTime', $email->getSubject());
        $this->assertStringContainsString('<html lang="en" dir="ltr"', $email->getHtmlBody());
        $this->assertStringContainsString('Hello Amine,', $email->getTextBody());
    }

    public static function fallbackLanguages(): array
    {
        return [[null], [''], ['de'], ['hi'], ['<script>']];
    }

    public function test_regional_locale_uses_the_matching_translation(): void
    {
        $email = $this->renderWelcome('Amine', 'array', 'ar-MA');
        $this->assertSame('مرحبًا بك في SalaTime', $email->getSubject());
        $this->assertStringContainsString('<html lang="ar" dir="rtl"', $email->getHtmlBody());
    }

    public function test_registration_delivers_the_welcome_in_the_persisted_language_through_the_real_mail_channel(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        (require database_path('migrations/2026_09_12_190000_create_mobile_accounts_tables.php'))->up();
        (require database_path('migrations/2026_10_06_010000_add_locale_to_mobile_accounts.php'))->up();
        config(['mobile_auth.enabled' => true, 'mobile_auth.mail_enabled' => true]);
        app()->setLocale('fr');
        Http::preventStrayRequests();

        $this->postJson('/api/mobile/auth/register', [
            'name' => 'Amine', 'email' => 'reader@example.test',
            'password' => 'a-secure-password-123', 'password_confirmation' => 'a-secure-password-123',
            'locale' => 'ar',
        ], ['Accept-Language' => 'en-US'])->assertCreated()->assertJsonPath('data.user.locale', 'ar');

        $this->assertDatabaseHas('mobile_accounts', ['email' => 'reader@example.test', 'locale' => 'ar']);
        $this->assertDatabaseCount('mobile_preferences', 0);
        $messages = Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages); // Verification code and a single welcome, both in memory.
        $email = $messages->last()->getOriginalMessage();
        $this->assertSame('مرحبًا بك في SalaTime', $email->getSubject());
        $this->assertStringContainsString('<html lang="ar" dir="rtl"', $email->getHtmlBody());
        $this->assertStringContainsString('مرحبًا Amine،', $email->getTextBody());
        $this->assertStringNotContainsString('Bonjour', $email->getHtmlBody().$email->getTextBody());
        $this->app->terminate();
        $this->assertCount(2, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    private function renderWelcome(string $name, string $mailer = 'array', ?string $locale = 'fr'): Email
    {
        // Unsaved account and in-memory mailer exercise the real MailChannel and MIME rendering.
        (new MobileAccount(['name' => $name, 'email' => 'reader@example.test', 'locale' => $locale]))
            ->notify(new AccountWelcome);
        $messages = Mail::mailer($mailer)->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);

        return $messages->first()->getOriginalMessage();
    }

    private function htmlDocument(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $this->assertTrue($document->loadHTML($html, LIBXML_NONET));

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function configureDedicatedTransport(): void
    {
        config(['mail.mailers.mobile_accounts' => [
            'transport' => 'array',
            'host' => 'smtp.example.test',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'accounts-test-user',
            'password' => 'accounts-test-password',
            'from' => ['address' => 'accounts@example.test', 'name' => 'SalaTime accounts'],
        ]]);
    }
}
