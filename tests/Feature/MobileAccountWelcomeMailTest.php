<?php

namespace Tests\Feature;

use App\Models\Mobile\MobileAccount;
use App\Notifications\Mobile\AccountWelcome;
use DOMDocument;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
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
        $this->assertStringContainsString('Bonjour Amine,', $html);
        $this->assertStringContainsString('Bonjour Amine,', $text);
        $this->assertStringContainsString('Vos préférences vous suivent', $html);
        $this->assertStringContainsString('VOS PRÉFÉRENCES VOUS SUIVENT', $text);
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
        $this->assertStringContainsString('Bonjour Amine,', $email->getHtmlBody());
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

    private function renderWelcome(string $name, string $mailer = 'array'): Email
    {
        // Unsaved account and in-memory mailer exercise the real MailChannel and MIME rendering.
        (new MobileAccount(['name' => $name, 'email' => 'reader@example.test']))
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
