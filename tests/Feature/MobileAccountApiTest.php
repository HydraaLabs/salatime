<?php

namespace Tests\Feature;

use App\Models\Mobile\MobileAccount;
use App\Models\User;
use App\Notifications\Mobile\AccountCode;
use App\Notifications\Mobile\AccountWelcome;
use Firebase\JWT\JWT;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MobileAccountApiTest extends TestCase
{
    private $rsa;

    private array $jwk;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../../bootstrap/app.php';
        // Applied before providers boot: no query can reach the existing DB.
        $app->afterBootstrapping(LoadConfiguration::class, function ($app) {
            $app['config']->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
                'cache.default' => 'array', 'mail.default' => 'array', 'queue.default' => 'sync',
                'mobile_auth.enabled' => true, 'mobile_auth.mail_enabled' => true,
                'mobile_auth.apple.client_ids' => [], 'mobile_auth.apple.private_key_path' => '']);
        });
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        (require database_path('migrations/2019_12_14_000001_create_personal_access_tokens_table.php'))->up();
        (require database_path('migrations/2026_09_12_190000_create_mobile_accounts_tables.php'))->up();
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
        Notification::fake();
        Cache::flush();
        Http::preventStrayRequests();
        $this->rsa = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $details = openssl_pkey_get_details($this->rsa);
        $this->jwk = ['kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'test-key',
            'n' => JWT::urlsafeB64Encode($details['rsa']['n']), 'e' => JWT::urlsafeB64Encode($details['rsa']['e'])];
        config(['mobile_auth.google.client_ids' => ['google-test-client'], 'mobile_auth.google.server_client_id' => 'google-test-client']);
        Http::fake(['www.googleapis.com/oauth2/v3/certs' => Http::response(['keys' => [$this->jwk]])]);
    }

    private function credentials(string $email = 'mobile@example.test'): array
    {
        return ['name' => 'SalaTime User', 'email' => $email, 'password' => 'a-secure-password-123', 'password_confirmation' => 'a-secure-password-123'];
    }

    private function register(string $email = 'mobile@example.test'): array
    {
        return $this->postJson('/api/mobile/auth/register', $this->credentials($email))->assertCreated()->json('data');
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    private function googleToken(array $changes = [], $key = null): string
    {
        return JWT::encode(array_replace(['iss' => 'https://accounts.google.com', 'aud' => 'google-test-client', 'sub' => 'google-subject-1', 'email' => 'social@example.test', 'email_verified' => true, 'iat' => time() - 1, 'exp' => time() + 300], $changes), $key ?? $this->rsa, 'RS256', 'test-key');
    }

    public function test_email_registration_login_logout_and_admin_isolation(): void
    {
        $session = $this->postJson('/api/mobile/auth/register', $this->credentials('MOBILE@example.test') + ['is_admin' => true])->assertCreated()->json('data');
        $this->assertFalse($session['user']['email_verified']);
        $this->assertTrue($session['user']['has_password']);
        $this->assertArrayNotHasKey('password', $session['user']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseHas('mobile_accounts', ['email' => 'mobile@example.test']);
        $token = $session['token'];
        $this->getJson('/api/mobile/auth/me', $this->bearer($token))->assertOk()->assertJsonPath('data.user.email', 'mobile@example.test');
        $this->getJson('/api/user', $this->bearer($token))->assertForbidden();
        $this->postJson('/api/mobile/auth/logout', [], $this->bearer($token))->assertOk();
        $this->getJson('/api/mobile/auth/me', $this->bearer($token))->assertUnauthorized();
        $this->postJson('/api/mobile/auth/login', ['email' => 'mobile@example.test', 'password' => 'a-secure-password-123'])->assertOk()->assertJsonPath('data.user.id', $session['user']['id']);
        Notification::assertSentToTimes(MobileAccount::first(), AccountWelcome::class, 1);
    }

    public function test_admin_tokens_and_sessions_cannot_access_mobile_accounts(): void
    {
        $admin = User::create(['first_name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'a-secure-password-123', 'is_admin' => true]);
        $token = $admin->createToken('admin', ['*'])->plainTextToken;
        $this->getJson('/api/mobile/auth/me', $this->bearer($token))->assertUnauthorized();
        $this->getJson('/api/mobile/preferences', $this->bearer($token))->assertUnauthorized();
        $this->actingAs($admin, 'web')->getJson('/api/mobile/preferences')->assertUnauthorized();
    }

    public function test_password_validation_and_login_throttle(): void
    {
        $this->postJson('/api/mobile/auth/register', array_replace($this->credentials(), ['password' => 'short123', 'password_confirmation' => 'short123']))->assertUnprocessable();
        Notification::assertNothingSent();
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->postJson('/api/mobile/auth/login', ['email' => 'mobile@example.test', 'password' => 'wrong-password'])->assertUnprocessable();
        }
        $this->postJson('/api/mobile/auth/login', ['email' => 'mobile@example.test', 'password' => 'wrong-password'])->assertTooManyRequests();
    }

    public function test_email_codes_are_hashed_single_use_and_password_reset_revokes_sessions(): void
    {
        $session = $this->register();
        $account = MobileAccount::first();
        $code = Notification::sent($account, AccountCode::class)->first()->code;
        $this->assertNotSame($code, DB::table('mobile_account_actions')->value('token_hash'));
        $this->postJson('/api/mobile/auth/verify-email', ['token' => $code], $this->bearer($session['token']))->assertOk()->assertJsonPath('data.user.email_verified', true);
        $this->assertDatabaseCount('mobile_account_actions', 0);
        $this->postJson('/api/mobile/auth/forgot-password', ['email' => $account->email])->assertOk();
        $reset = Notification::sent($account, AccountCode::class)->last()->code;
        $body = ['email' => $account->email, 'token' => $reset, 'password' => 'new-secure-password-456', 'password_confirmation' => 'new-secure-password-456'];
        $this->postJson('/api/mobile/auth/reset-password', $body)->assertOk();
        $this->getJson('/api/mobile/auth/me', $this->bearer($session['token']))->assertUnauthorized();
        $this->postJson('/api/mobile/auth/reset-password', $body)->assertUnprocessable();
        $this->postJson('/api/mobile/auth/login', ['email' => $account->email, 'password' => $body['password']])->assertOk();
        Notification::assertSentToTimes($account, AccountWelcome::class, 1);
    }

    public function test_codes_expire_and_five_wrong_guesses_lock_the_code(): void
    {
        $session = $this->register();
        $account = MobileAccount::first();
        $code = Notification::sent($account, AccountCode::class)->first()->code;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/mobile/auth/verify-email', ['token' => '000000'], $this->bearer($session['token']))->assertUnprocessable();
        }
        $this->travel(61)->seconds();
        $this->postJson('/api/mobile/auth/verify-email', ['token' => $code], $this->bearer($session['token']))->assertUnprocessable();
        $this->assertSame(5, DB::table('mobile_account_actions')->value('attempts'));
        DB::table('mobile_account_actions')->update(['attempts' => 0, 'expires_at' => now()->subMinute()]);
        $this->postJson('/api/mobile/auth/verify-email', ['token' => $code], $this->bearer($session['token']))->assertUnprocessable();
    }

    public function test_preferences_belong_to_account_and_optimistic_conflicts_do_not_overwrite(): void
    {
        $first = $this->register('first@example.test');
        $second = $this->register('second@example.test');
        $one = $this->bearer($first['token']);
        $two = $this->bearer($second['token']);
        $this->getJson('/api/mobile/preferences', $one)->assertOk()->assertJsonPath('data.version', 0)->assertJsonPath('data.preferences', []);
        $doc = ['schemaVersion' => 1, 'themeMode' => 'dark', 'prayerAdjustments' => ['sunrise' => 5], 'widgets' => ['seconds' => false]];
        $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => $doc, 'account_id' => $second['user']['id']], $one)->assertOk()->assertJsonPath('data.version', 1);
        $this->getJson('/api/mobile/preferences', $two)->assertOk()->assertJsonPath('data.version', 0);
        $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => ['schemaVersion' => 1]], $one)->assertConflict()->assertJsonPath('code', 'preferences_conflict')->assertJsonPath('data.preferences.themeMode', 'dark');
        $this->putJson('/api/mobile/preferences', ['version' => 1, 'preferences' => ['schemaVersion' => 1, 'themeMode' => 'light']], $one)->assertOk()->assertJsonPath('data.version', 2);
    }

    public function test_preferences_reject_unknown_sensitive_keys_invalid_types_and_limits(): void
    {
        $session = $this->register();
        $headers = $this->bearer($session['token']);
        foreach ([['schemaVersion' => 1, 'gps' => ['latitude' => 34]], ['schemaVersion' => 1, 'widgets' => ['seconds' => '1']], ['schemaVersion' => 1, 'prayerAdjustments' => ['asr' => 121]], ['schemaVersion' => 1, 'sounds' => ['adhan' => 'content://private/file.mp3']], ['themeMode' => 'light'], ['schemaVersion' => 1, 'reader' => ['goal' => 5001]]] as $document) {
            $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => $document], $headers)->assertUnprocessable();
        }
        $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => ['schemaVersion' => 1, 'reader' => ['translation' => str_repeat('x', 70000)]]], $headers)->assertStatus(413);
        $this->assertDatabaseCount('mobile_preferences', 0);
    }

    public function test_account_deletion_requires_password_and_removes_only_its_data(): void
    {
        $one = $this->register('one@example.test');
        $two = $this->register('two@example.test');
        $headers = $this->bearer($one['token']);
        $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => ['schemaVersion' => 1]], $headers)->assertOk();
        $this->deleteJson('/api/mobile/auth/account', ['password' => 'wrong'], $headers)->assertUnprocessable();
        $this->deleteJson('/api/mobile/auth/account', ['password' => 'a-secure-password-123'], $headers)->assertOk();
        $this->assertDatabaseCount('mobile_preferences', 0);
        $this->assertDatabaseCount('mobile_accounts', 1);
        $this->getJson('/api/mobile/auth/me', $this->bearer($two['token']))->assertOk();
        $this->getJson('/api/mobile/auth/me', $headers)->assertUnauthorized();
    }

    public function test_google_validates_signature_issuer_audience_expiry_and_subject(): void
    {
        $badKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        foreach ([$this->googleToken([], $badKey), $this->googleToken(['iss' => 'https://attacker.invalid']), $this->googleToken(['aud' => 'another-app']), $this->googleToken(['exp' => time() - 10])] as $token) {
            $this->postJson('/api/mobile/auth/google', ['id_token' => $token])->assertUnprocessable();
        }
        Notification::assertNothingSent();
        $this->postJson('/api/mobile/auth/google', ['id_token' => $this->googleToken()])->assertOk()->assertJsonPath('data.user.has_password', false);
        $this->assertDatabaseCount('mobile_accounts', 1);
        $this->assertDatabaseCount('mobile_identities', 1);
        Notification::assertSentToTimes(MobileAccount::first(), AccountWelcome::class, 1);
    }

    public function test_google_cached_id_token_can_reauthenticate_without_duplicate_account(): void
    {
        $token = $this->googleToken();
        $first = $this->postJson('/api/mobile/auth/google', ['id_token' => $token])->assertOk()->json('data');
        $this->postJson('/api/mobile/auth/logout', [], $this->bearer($first['token']))->assertOk();
        $this->postJson('/api/mobile/auth/google', ['id_token' => $token])->assertOk()->assertJsonPath('data.user.id', $first['user']['id']);
        $this->assertDatabaseCount('mobile_accounts', 1);
        Notification::assertSentToTimes(MobileAccount::first(), AccountWelcome::class, 1);
    }

    public function test_social_email_does_not_automatically_link_to_an_existing_account(): void
    {
        $local = $this->register('social@example.test');
        $this->postJson('/api/mobile/auth/google', ['id_token' => $this->googleToken()])->assertConflict()->assertJsonPath('code', 'account_link_required');
        $this->postJson('/api/mobile/auth/link/google', ['id_token' => $this->googleToken()], $this->bearer($local['token']))->assertForbidden()->assertJsonPath('code', 'email_verification_required');
        MobileAccount::first()->forceFill(['email_verified_at' => now()])->save();
        $this->postJson('/api/mobile/auth/link/google', ['id_token' => $this->googleToken()], $this->bearer($local['token']))->assertOk()->assertJsonPath('data.user.id', $local['user']['id']);
        $this->assertDatabaseCount('mobile_accounts', 1);
        Notification::assertSentToTimes(MobileAccount::first(), AccountWelcome::class, 1);
    }

    public function test_existing_accounts_do_not_receive_a_welcome_when_logging_in_or_linking_google(): void
    {
        $account = MobileAccount::create(['name' => 'Existing user', 'email' => 'social@example.test',
            'password' => 'a-secure-password-123', 'email_verified_at' => now()]);
        $session = $this->postJson('/api/mobile/auth/login', ['email' => $account->email,
            'password' => 'a-secure-password-123'])->assertOk()->json('data');
        $this->postJson('/api/mobile/auth/link/google', ['id_token' => $this->googleToken()],
            $this->bearer($session['token']))->assertOk();
        Notification::assertNothingSent();
    }

    public function test_google_account_creation_succeeds_if_the_welcome_transport_fails(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated SMTP failure'));

        $session = $this->postJson('/api/mobile/auth/google', ['id_token' => $this->googleToken()])
            ->assertOk()->json('data');
        $this->getJson('/api/mobile/auth/me', $this->bearer($session['token']))->assertOk();
        $this->assertDatabaseCount('mobile_accounts', 1);
        $this->assertDatabaseCount('mobile_identities', 1);
    }

    public function test_unconfigured_social_provider_is_disabled_and_fake_apple_token_is_rejected(): void
    {
        $this->getJson('/api/mobile/auth/config')->assertOk()->assertJsonPath('data.apple.enabled', false);
        $this->getJson('/api/mobile/auth/challenge?provider=apple')->assertStatus(503);
        $this->postJson('/api/mobile/auth/apple', ['identity_token' => 'not-a-jwt'])->assertUnprocessable();
        $this->assertDatabaseCount('mobile_accounts', 0);
    }

    private function appleConfiguration(): string
    {
        $ec = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ec, $pem);
        $path = tempnam(sys_get_temp_dir(), 'salatime-apple-test-');
        file_put_contents($path, $pem);
        $this->beforeApplicationDestroyed(fn () => unlink($path));
        config(['mobile_auth.apple.client_ids' => ['net.salatime.test'], 'mobile_auth.apple.client_id' => 'net.salatime.test',
            'mobile_auth.apple.team_id' => 'TESTTEAM', 'mobile_auth.apple.key_id' => 'TESTKEY',
            'mobile_auth.apple.private_key_path' => $path, 'mobile_auth.apple.redirect_uri' => 'https://example.test/api/mobile/auth/apple/callback']);

        return openssl_pkey_get_details($ec)['key'];
    }

    private function appleToken(string $nonce): string
    {
        return $this->googleToken(['iss' => 'https://appleid.apple.com', 'aud' => 'net.salatime.test',
            'sub' => 'apple-user-1', 'email' => 'relay@privaterelay.appleid.com', 'email_verified' => 'true', 'nonce' => hash('sha256', $nonce)]);
    }

    public function test_apple_nonce_state_code_exchange_and_account_revocation(): void
    {
        $publicKey = $this->appleConfiguration();
        $challenge = $this->getJson('/api/mobile/auth/challenge?provider=apple&platform=android')->assertOk()->json('data');
        $jwt = $this->appleToken($challenge['nonce']);
        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response(['keys' => [$this->jwk]]),
            'appleid.apple.com/auth/token' => Http::response(['id_token' => $jwt, 'refresh_token' => 'fake-refresh-token-for-unit-test']),
            'appleid.apple.com/auth/revoke' => Http::response([], 200),
        ]);
        $this->post('/api/mobile/auth/apple/callback', ['state' => $challenge['state'], 'code' => 'one-use-code', 'id_token' => $jwt])->assertRedirect()->assertHeader('location');
        $body = $challenge + ['identity_token' => $jwt, 'authorization_code' => 'one-use-code'];
        $session = $this->postJson('/api/mobile/auth/apple', $body)->assertOk()->json('data');
        $this->assertTrue($session['user']['email_verified']);
        $this->assertFalse($session['user']['has_password']);
        $this->assertNotSame('fake-refresh-token-for-unit-test', DB::table('mobile_identities')->value('refresh_token'));
        Http::assertSent(function ($request) use ($publicKey) {
            if ($request->url() !== 'https://appleid.apple.com/auth/token') {
                return false;
            }
            $claims = JWT::decode($request['client_secret'], new \Firebase\JWT\Key($publicKey, 'ES256'));

            return $claims->iss === 'TESTTEAM' && $claims->sub === 'net.salatime.test' && $request['code'] === 'one-use-code';
        });
        $this->postJson('/api/mobile/auth/apple', $body)->assertUnprocessable()->assertJsonValidationErrors('nonce');
        $this->deleteJson('/api/mobile/auth/account', [], $this->bearer($session['token']))->assertOk();
        Http::assertSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/revoke' && $request['token'] === 'fake-refresh-token-for-unit-test');
        $this->assertDatabaseCount('mobile_accounts', 0);
        $this->assertDatabaseCount('mobile_identities', 0);
    }

    public function test_apple_rejects_wrong_nonce_or_state_before_issuing_a_session(): void
    {
        $this->appleConfiguration();
        $challenge = $this->getJson('/api/mobile/auth/challenge?provider=apple&platform=android')->assertOk()->json('data');
        $jwt = $this->appleToken($challenge['nonce']);
        Http::fake(['appleid.apple.com/auth/keys' => Http::response(['keys' => [$this->jwk]])]);
        $body = $challenge + ['identity_token' => $jwt, 'authorization_code' => 'code'];
        $this->postJson('/api/mobile/auth/apple', array_replace($body, ['nonce' => str_repeat('f', 64)]))->assertUnprocessable();
        $this->postJson('/api/mobile/auth/apple', array_replace($body, ['state' => 'invalid-state']))->assertUnprocessable();
        $this->post('/api/mobile/auth/apple/callback', ['state' => 'invalid-state', 'code' => 'code'])->assertStatus(400);
        $this->assertDatabaseCount('mobile_accounts', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Http::assertNotSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/token');
    }

    public function test_native_apple_sign_in_preserves_name_on_returning_authorizations(): void
    {
        $this->appleConfiguration();
        config(['mobile_auth.apple.ios_client_id' => 'net.salatime.test',
            'mobile_auth.apple.client_id' => '', 'mobile_auth.apple.redirect_uri' => '']);
        $this->getJson('/api/mobile/auth/config')->assertOk()
            ->assertJsonPath('data.apple.ios_enabled', true)
            ->assertJsonPath('data.apple.android_enabled', false);
        $this->getJson('/api/mobile/auth/challenge?provider=apple&platform=android')->assertServiceUnavailable();
        $id = null;
        $exchangeResponses = Http::sequence();
        Http::fake([
            'appleid.apple.com/auth/keys' => Http::response(['keys' => [$this->jwk]]),
            'appleid.apple.com/auth/token' => $exchangeResponses,
        ]);
        foreach ([['name' => 'First Apple name'], [], ['name' => ''], ['name' => null]] as $name) {
            $challenge = $this->getJson('/api/mobile/auth/challenge?provider=apple&platform=ios')->assertOk()->json('data');
            $jwt = $this->appleToken($challenge['nonce']);
            $exchangeResponses->push(['id_token' => $jwt, 'refresh_token' => 'fake-native-refresh-token']);
            $session = $this->postJson('/api/mobile/auth/apple', $challenge + $name + [
                'identity_token' => $jwt, 'authorization_code' => 'native-code',
            ])->assertOk()->assertJsonPath('data.user.name', 'First Apple name')->json('data');
            $id ??= $session['user']['id'];
            $this->assertSame($id, $session['user']['id']);
            Http::assertSent(fn ($request) => $request->url() === 'https://appleid.apple.com/auth/token'
                && $request['client_id'] === 'net.salatime.test' && ! isset($request['redirect_uri']));
        }
        $this->assertDatabaseCount('mobile_accounts', 1);
        $this->assertDatabaseCount('mobile_identities', 1);
        Notification::assertSentToTimes(MobileAccount::find($id), AccountWelcome::class, 1);
    }

    public function test_apple_configuration_hides_platforms_without_a_valid_audience_or_redirect(): void
    {
        $this->appleConfiguration();
        config(['mobile_auth.apple.ios_client_id' => 'unconfigured.native.app']);
        $this->getJson('/api/mobile/auth/config')->assertOk()
            ->assertJsonPath('data.apple.ios_enabled', false)
            ->assertJsonPath('data.apple.android_enabled', true);
        $this->getJson('/api/mobile/auth/challenge?provider=apple&platform=ios')->assertServiceUnavailable();
        foreach (['http://example.test/callback', '', 'https:///bad'] as $redirect) {
            config(['mobile_auth.apple.redirect_uri' => $redirect]);
            $this->getJson('/api/mobile/auth/config')->assertOk()->assertJsonPath('data.apple.android_enabled', false);
            $this->getJson('/api/mobile/auth/challenge?provider=apple&platform=android')->assertServiceUnavailable();
        }
        config(['mobile_auth.apple.redirect_uri' => 'https://example.test/callback',
            'mobile_auth.apple.client_id' => 'not-accepted-service']);
        $this->getJson('/api/mobile/auth/config')->assertOk()->assertJsonPath('data.apple.android_enabled', false);
        Http::assertNothingSent();
    }

    public function test_every_catalog_sound_is_portable_and_unknown_names_are_rejected(): void
    {
        $session = $this->register();
        $headers = $this->bearer($session['token']);
        $keys = config('mobile_sound_keys');
        $this->assertGreaterThan(60, count($keys));
        $name = collect($keys)->first(fn ($key) => str_starts_with($key, 'moatheni_'));
        $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => ['schemaVersion' => 1, 'sounds' => ['adhan' => $name, 'before' => 'silent', 'after' => 'custom_'.str_repeat('a', 64)]]], $headers)->assertOk()->assertJsonPath('data.preferences.sounds.adhan', $name);
        $this->putJson('/api/mobile/preferences', ['version' => 1, 'preferences' => ['schemaVersion' => 1, 'sounds' => ['adhan' => 'moatheni_unknown_new_file']]], $headers)->assertUnprocessable();
    }

    public function test_expired_tokens_and_foreign_verification_codes_are_not_accepted(): void
    {
        $one = $this->register('one@example.test');
        $two = $this->register('two@example.test');
        $code = Notification::sent(MobileAccount::where('email', 'one@example.test')->first(), AccountCode::class)->first()->code;
        DB::table('mobile_account_actions')->where('mobile_account_id', $two['user']['id'])->update(['token_hash' => hash_hmac('sha256', '000000', (string) config('app.key'))]);
        $this->postJson('/api/mobile/auth/verify-email', ['token' => $code], $this->bearer($two['token']))->assertUnprocessable();
        DB::table('personal_access_tokens')->where('tokenable_id', $one['user']['id'])->update(['expires_at' => now()->subSecond()]);
        $this->getJson('/api/mobile/auth/me', $this->bearer($one['token']))->assertUnauthorized();
    }

    public function test_prayer_notification_settings_roundtrip_and_personal_sounds_use_the_exact_portable_defaults(): void
    {
        $headers = $this->bearer($this->register()['token']);
        $overrides = [];
        foreach (['before', 'adhan', 'after'] as $phase) {
            foreach (['fajr', 'sunrise', 'dhuhr', 'jumaa', 'asr', 'maghrib', 'isha'] as $prayer) {
                $overrides[$phase][$prayer] = ['enabled' => $prayer !== 'sunrise', 'sound' => 'custom_'.str_repeat('c', 64), 'minutes' => $phase === 'adhan' ? 0 : 120];
            }
        }
        $response = $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => ['schemaVersion' => 1, 'prayerNotificationSettings' => $overrides]], $headers)->assertOk()->assertJsonPath('data.version', 1);
        foreach ($overrides as $phase => $prayers) {
            foreach ($prayers as $prayer => $setting) {
                $sound = config('mobile_notification_defaults.prayerSounds.'.$phase.'.'.$prayer);
                $this->assertContains($sound, config('mobile_sound_keys'));
                $response->assertJsonPath('data.preferences.prayerNotificationSettings.'.$phase.'.'.$prayer.'.sound', $sound);
                $response->assertJsonPath('data.preferences.prayerNotificationSettings.'.$phase.'.'.$prayer.'.minutes', $setting['minutes']);
            }
        }
        $response->assertJsonPath('data.preferences.prayerNotificationSettings.before.sunrise.sound', 'moatheni_water')
            ->assertJsonPath('data.preferences.prayerNotificationSettings.adhan.sunrise.sound', 'moatheni_bird')
            ->assertJsonPath('data.preferences.prayerNotificationSettings.after.jumaa.sound', 'moatheni_short_sound');
        $this->getJson('/api/mobile/preferences', $headers)->assertOk()->assertExactJson($response->json());
        $empty = $this->putJson('/api/mobile/preferences', ['version' => 1, 'preferences' => ['schemaVersion' => 1, 'prayerNotificationSettings' => ['before' => ['fajr' => []], 'after' => []]]], $headers)->assertOk();
        $wire = json_decode($empty->getContent());
        $this->assertInstanceOf(\stdClass::class, $wire->data->preferences->prayerNotificationSettings->after);
        $this->assertInstanceOf(\stdClass::class, $wire->data->preferences->prayerNotificationSettings->before->fajr);
        $reset = $this->putJson('/api/mobile/preferences', ['version' => 2, 'preferences' => ['schemaVersion' => 1, 'prayerNotificationSettings' => []]], $headers)->assertOk();
        $this->assertInstanceOf(\stdClass::class, json_decode($reset->getContent())->data->preferences->prayerNotificationSettings);
    }

    public function test_additional_notification_anchors_keep_old_backups_and_separate_weekdays(): void
    {
        $headers = $this->bearer($this->register()['token']);
        $extras = [
            'mondayThursday' => ['enabled' => true, 'sound' => 'silent', 'minutes' => 1200],
            'whiteDays' => ['enabled' => true, 'sound' => 'silent', 'minutes' => 1439],
        ];
        $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => ['schemaVersion' => 1, 'additionalReminders' => $extras]], $headers)->assertOk()->assertJsonPath('data.preferences.additionalReminders.whiteDays.minutes', 1439);
        $anchors = ['fajrAlarm' => 'afterFajr', 'duha' => 'beforeDhuhr', 'morning' => 'afterFajr', 'evening' => 'beforeMaghrib', 'bedtime' => 'afterIsha', 'monday' => 'afterIsha', 'thursday' => 'clock', 'middleNight' => 'beforeMiddleNight', 'lastThird' => 'beforeLastThird', 'friday' => 'beforeMaghrib'];
        foreach ($anchors as $type => $anchor) {
            $extras[$type] = ['enabled' => true, 'anchor' => $anchor, 'minutes' => $anchor === 'clock' ? 1345 : 120, 'sound' => 'custom_'.str_repeat('d', 64), 'useDefaultSound' => false];
        }
        $response = $this->putJson('/api/mobile/preferences', ['version' => 1, 'preferences' => ['schemaVersion' => 1, 'additionalReminders' => $extras]], $headers)->assertOk();
        foreach ($anchors as $type => $anchor) {
            $sound = config('mobile_notification_defaults.additionalSounds.'.$type);
            $this->assertContains($sound, config('mobile_sound_keys'));
            $response->assertJsonPath('data.preferences.additionalReminders.'.$type.'.anchor', $anchor)
                ->assertJsonPath('data.preferences.additionalReminders.'.$type.'.sound', $sound)
                ->assertJsonPath('data.preferences.additionalReminders.'.$type.'.useDefaultSound', false);
        }
        $response->assertJsonPath('data.preferences.additionalReminders.mondayThursday.minutes', 1200)
            ->assertJsonPath('data.preferences.additionalReminders.whiteDays.minutes', 1439);
    }

    public function test_notification_schema_rejects_wrong_phase_types_anchors_and_bounds_without_overwriting(): void
    {
        $headers = $this->bearer($this->register()['token']);
        $documents = [
            ['prayerNotificationSettings' => ['unknown' => []]],
            ['prayerNotificationSettings' => ['before' => ['unknown' => []]]],
            ['prayerNotificationSettings' => ['before' => ['fajr' => ['minutes' => 121]]]],
            ['prayerNotificationSettings' => ['after' => ['fajr' => ['minutes' => -1]]]],
            ['prayerNotificationSettings' => ['adhan' => ['jumaa' => ['minutes' => 1]]]],
            ['prayerNotificationSettings' => ['adhan' => ['fajr' => ['enabled' => 'true']]]],
            ['prayerNotificationSettings' => ['before' => ['sunrise' => ['sound' => 'content://private/audio']]]],
            ['additionalReminders' => ['beforeLastThird' => ['enabled' => true]]],
            ['additionalReminders' => ['bedtime' => ['anchor' => 'clock', 'minutes' => 1000]]],
            ['additionalReminders' => ['middleNight' => ['minutes' => 121]]],
            ['additionalReminders' => ['fajrAlarm' => ['anchor' => 'beforeFajr', 'minutes' => '20']]],
            ['additionalReminders' => ['monday' => ['anchor' => 'afterIsha', 'minutes' => 121]]],
            ['additionalReminders' => ['thursday' => ['anchor' => 'clock', 'minutes' => 1440]]],
            ['additionalReminders' => ['whiteDays' => ['useDefaultSound' => 'false']]],
            ['additionalReminders' => ['duha' => ['token' => 'secret']]],
        ];
        foreach ($documents as $document) {
            $this->putJson('/api/mobile/preferences', ['version' => 0, 'preferences' => ['schemaVersion' => 1] + $document], $headers)->assertUnprocessable();
        }
        $this->assertDatabaseCount('mobile_preferences', 0);
    }
}
