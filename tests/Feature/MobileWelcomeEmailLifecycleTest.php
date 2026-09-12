<?php

namespace Tests\Feature;

use App\Models\Mobile\MobileAccount;
use App\Notifications\Mobile\AccountWelcome;
use App\Services\Mobile\WelcomeEmails;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

class MobileWelcomeEmailLifecycleTest extends TestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        // Set isolation before providers can read settings or open a connection.
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array',
                'mail.default' => 'array',
                'queue.default' => 'sync',
                'logging.default' => 'null',
                'mobile_auth.enabled' => true,
                'mobile_auth.mail_enabled' => true,
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2026_09_12_190000_create_mobile_accounts_tables.php'))->up();
        Notification::fake();
        Http::preventStrayRequests();
    }

    private function account(): MobileAccount
    {
        return MobileAccount::create(['name' => 'Welcome Test', 'email' => 'welcome@example.test']);
    }

    public function test_it_sends_only_after_termination_and_only_once_when_termination_repeats(): void
    {
        $account = $this->account();
        app(WelcomeEmails::class)->afterRegistration($account);
        Notification::assertNothingSent();

        $this->app->terminate();
        Notification::assertSentToTimes($account, AccountWelcome::class, 1);
        $this->app->terminate();
        Notification::assertSentToTimes($account, AccountWelcome::class, 1);
        Notification::assertCount(1);
    }

    public function test_an_open_transaction_must_commit_before_termination_can_send(): void
    {
        DB::beginTransaction();
        $account = $this->account();
        app(WelcomeEmails::class)->afterRegistration($account);
        $this->app->terminate();
        Notification::assertNothingSent();

        DB::commit();
        Notification::assertNothingSent();
        $this->app->terminate();
        Notification::assertSentToTimes($account, AccountWelcome::class, 1);
    }

    public function test_rolling_back_registration_never_sends_a_welcome(): void
    {
        DB::beginTransaction();
        $account = $this->account();
        app(WelcomeEmails::class)->afterRegistration($account);
        DB::rollBack();

        $this->app->terminate();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('mobile_accounts', 0);
    }

    public function test_existing_accounts_are_not_scheduled(): void
    {
        $existing = MobileAccount::findOrFail($this->account()->id);
        $this->assertFalse($existing->wasRecentlyCreated);
        app(WelcomeEmails::class)->afterRegistration($existing);

        $this->app->terminate();
        Notification::assertNothingSent();
    }

    public function test_mail_disabled_at_registration_does_not_send_after_later_activation(): void
    {
        config(['mobile_auth.mail_enabled' => false]);
        app(WelcomeEmails::class)->afterRegistration($this->account());
        config(['mobile_auth.mail_enabled' => true]);

        $this->app->terminate();
        Notification::assertNothingSent();
    }

    public function test_mail_disabled_before_delivery_is_skipped_without_a_later_retry(): void
    {
        app(WelcomeEmails::class)->afterRegistration($this->account());
        config(['mobile_auth.mail_enabled' => false]);
        $this->app->terminate();
        config(['mobile_auth.mail_enabled' => true]);
        $this->app->terminate();

        Notification::assertNothingSent();
    }

    public function test_an_account_deleted_before_the_callback_is_skipped(): void
    {
        $account = $this->account();
        app(WelcomeEmails::class)->afterRegistration($account);
        $account->delete();

        $this->app->terminate();
        Notification::assertNothingSent();
        $this->assertDatabaseCount('mobile_accounts', 0);
    }

    public function test_smtp_failure_is_sanitized_and_does_not_interrupt_termination_or_retry(): void
    {
        $account = $this->account();
        $privateDetails = 'private-address@example.test private-smtp-password private-provider-token';
        Notification::shouldReceive('send')->once()->andThrow(new RuntimeException($privateDetails));
        $reported = [];
        Log::shouldReceive('error')->once()->andReturnUsing(function ($message, $context) use (&$reported) {
            $reported = [$message, $context];
        });
        app(WelcomeEmails::class)->afterRegistration($account);
        $continued = false;
        $this->app->terminating(function () use (&$continued) {
            $continued = true;
        });

        $this->app->terminate();
        $this->app->terminate();

        $this->assertTrue($continued);
        $this->assertDatabaseHas('mobile_accounts', ['id' => $account->id]);
        $this->assertSame('SalaTime welcome email could not be delivered.', $reported[0]);
        $exception = $reported[1]['exception'];
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertNull($exception->getPrevious());
        $logged = $reported[0].json_encode($reported[1], JSON_PARTIAL_OUTPUT_ON_ERROR).(string) $exception;
        foreach (explode(' ', $privateDetails) as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $logged);
        }
    }
}
