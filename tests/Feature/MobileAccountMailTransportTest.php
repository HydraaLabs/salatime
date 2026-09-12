<?php

namespace Tests\Feature;

use App\Models\Mobile\MobileAccount;
use App\Notifications\Mobile\AccountCode;
use App\Services\Mobile\EmailCodes;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MobileAccountMailTransportTest extends TestCase
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
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_unconfigured_account_mail_preserves_the_default_transport_and_sender(): void
    {
        config([
            'mail.mailers.mobile_accounts' => [],
            'mail.from' => ['address' => 'site@example.test', 'name' => 'Site'],
        ]);

        $message = (new AccountCode('123456', 'verify'))->toMail(null);
        $this->assertNull($message->mailer);
        $this->assertSame([], $message->from);

        Notification::route('mail', 'reader@example.test')->notify(new AccountCode('123456', 'verify'));
        $sent = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $email = $sent->first()->getOriginalMessage();
        $this->assertSame('site@example.test', $email->getFrom()[0]->getAddress());
        $this->assertSame('SalaTime — verify your email', $email->getSubject());
        $this->assertStringContainsString('123456', $email->getTextBody());
    }

    public function test_account_codes_use_the_dedicated_transport_and_sender_despite_site_overrides(): void
    {
        $this->configureDedicatedTransport();
        // SetEmailConfig updates these site values from the database.
        config([
            'mail.default' => 'array',
            'mail.from' => ['address' => 'legacy@example.test', 'name' => 'Legacy site'],
            'mail.mailers.smtp.host' => 'legacy.example.test',
            'mail.mailers.smtp.username' => 'legacy-user',
            'mail.mailers.smtp.password' => 'legacy-test-password',
        ]);

        foreach (['verify', 'reset'] as $purpose) {
            Notification::route('mail', 'reader@example.test')->notify(new AccountCode('654321', $purpose));
        }

        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
        $sent = Mail::mailer('mobile_accounts')->getSymfonyTransport()->messages();
        $this->assertCount(2, $sent);
        foreach ($sent as $item) {
            $email = $item->getOriginalMessage();
            $this->assertSame('accounts@example.test', $email->getFrom()[0]->getAddress());
            $this->assertSame('SalaTime accounts', $email->getFrom()[0]->getName());
            $this->assertStringContainsString('654321', $email->getTextBody());
        }
        $this->assertSame('SalaTime — password reset', $sent->last()->getOriginalMessage()->getSubject());
    }

    public function test_incomplete_dedicated_configuration_does_not_change_existing_delivery(): void
    {
        foreach (['host', 'username', 'password', 'from.address'] as $missing) {
            $this->configureDedicatedTransport();
            config(['mail.mailers.mobile_accounts.'.$missing => null]);
            $message = (new AccountCode('123456', 'verify'))->toMail(null);
            $this->assertNull($message->mailer);
            $this->assertSame([], $message->from);
        }
        $this->configureDedicatedTransport();
        config(['mail.mailers.mobile_accounts.from.address' => 'invalid-sender']);
        $this->assertNull((new AccountCode('123456', 'verify'))->toMail(null)->mailer);
    }

    public function test_configuring_smtp_does_not_bypass_the_account_email_gate(): void
    {
        $this->configureDedicatedTransport();
        config(['mobile_auth.mail_enabled' => false]);
        Notification::fake();

        // With the gate off, sending needs neither persisted account actions nor SMTP.
        app(EmailCodes::class)->send(new MobileAccount(['email' => 'reader@example.test']), 'verify');

        Notification::assertNothingSent();
        $this->assertCount(0, Mail::mailer('mobile_accounts')->getSymfonyTransport()->messages());
    }

    private function configureDedicatedTransport(): void
    {
        config(['mail.mailers.mobile_accounts' => [
            // Exercise the real notification routing without opening an SMTP connection.
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
