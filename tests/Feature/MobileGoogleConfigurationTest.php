<?php

namespace Tests\Feature;

use App\Services\Mobile\SocialIdentityVerifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileGoogleConfigurationTest extends TestCase
{
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array',
                'mobile_auth.enabled' => true,
            ]);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_google_is_hidden_when_the_server_client_cannot_be_validated(): void
    {
        foreach ([
            [null, []],
            ['', ['web-client']],
            ['web-client', []],
            ['web-client', ['another-client']],
        ] as [$serverClientId, $clientIds]) {
            config([
                'mobile_auth.google.server_client_id' => $serverClientId,
                'mobile_auth.google.client_ids' => $clientIds,
            ]);
            $this->assertFalse(app(SocialIdentityVerifier::class)->enabled('google'));
            $this->getJson('/api/mobile/auth/config')->assertOk()
                ->assertJsonPath('data.google.enabled', false)
                ->assertJsonPath('data.email.enabled', true);
        }
    }

    public function test_google_is_available_when_the_server_client_is_an_accepted_audience(): void
    {
        config([
            'mobile_auth.google.server_client_id' => 'web-client',
            'mobile_auth.google.client_ids' => ['ios-client', 'web-client'],
        ]);
        $this->assertTrue(app(SocialIdentityVerifier::class)->enabled('google'));
        $this->getJson('/api/mobile/auth/config')->assertOk()
            ->assertJsonPath('data.google.enabled', true)
            ->assertJsonPath('data.google.server_client_id', 'web-client');
    }

    public function test_mismatched_google_configuration_fails_before_any_provider_request(): void
    {
        config([
            'mobile_auth.google.server_client_id' => 'web-client',
            'mobile_auth.google.client_ids' => ['another-client'],
        ]);
        Http::preventStrayRequests();
        $this->postJson('/api/mobile/auth/google', ['id_token' => 'unused-provider-proof'])
            ->assertServiceUnavailable();
        Http::assertNothingSent();
    }
}
