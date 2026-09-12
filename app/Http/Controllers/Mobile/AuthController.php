<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Mobile\MobileAccount;
use App\Models\Mobile\MobileIdentity;
use App\Services\Mobile\AppleChallenge;
use App\Services\Mobile\EmailCodes;
use App\Services\Mobile\SocialIdentityVerifier;
use App\Services\Mobile\WelcomeEmails;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private EmailCodes $codes, private SocialIdentityVerifier $identities, private AppleChallenge $challenges, private WelcomeEmails $welcomes) {}

    public function configuration()
    {
        return response()->json(['data' => ['enabled' => (bool) config('mobile_auth.enabled'),
            'email' => ['enabled' => (bool) config('mobile_auth.enabled'), 'verification_enabled' => (bool) config('mobile_auth.mail_enabled'), 'password_reset_enabled' => (bool) config('mobile_auth.mail_enabled')],
            'google' => ['enabled' => (bool) config('mobile_auth.enabled') && $this->identities->enabled('google'), 'server_client_id' => config('mobile_auth.google.server_client_id'), 'ios_client_id' => config('mobile_auth.google.ios_client_id')],
            'apple' => ['enabled' => (bool) config('mobile_auth.enabled') && $this->identities->enabled('apple'), 'client_id' => config('mobile_auth.apple.client_id'), 'redirect_uri' => config('mobile_auth.apple.redirect_uri')],
        ]]);
    }

    private function passwordRule(): array
    {
        return ['required', 'string', 'max:128', 'confirmed', Password::min(12)->letters()->numbers(), function ($attribute, $value, $fail) {
            if (strlen($value) > 72) {
                $fail('The password must not exceed 72 bytes.');
            }
        }];
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
    }

    public function register(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email:rfc|max:254|unique:mobile_accounts,email', 'password' => $this->passwordRule(), 'device_name' => 'sometimes|string|max:100']);
        try {
            $account = MobileAccount::create(collect($data)->only(['name', 'email', 'password'])->all());
        } catch (QueryException $error) {
            if ($error->getCode() === '23000' || $error->getCode() === '23505') {
                throw ValidationException::withMessages(['email' => 'This email is already registered.']);
            }
            throw $error;
        }
        $this->deliverCode($account, 'verify');

        $response = $this->session($account, $request, 201);
        $this->welcomes->afterRegistration($account);

        return $response;
    }

    public function login(Request $request)
    {
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|email:rfc|max:254', 'password' => 'required|string|max:128', 'device_name' => 'sometimes|string|max:100']);
        $account = MobileAccount::where('email', $data['email'])->first();
        // A dummy hash keeps unknown-address timing close to password failures.
        $hash = $account?->password ?? '$2y$12$6pDQMGPNDPbcCUVjqmHdbO0bNxX8kbYHgAEvSmwgpZJRp.Hufpe5m';
        if (! Hash::check($data['password'], $hash) || ! $account?->password) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }
        if (Hash::needsRehash($account->password)) {
            $account->forceFill(['password' => $data['password']])->save();
        }

        return $this->session($account, $request);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => ['user' => $request->user()->publicData()]]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['logged_out' => true]]);
    }

    public function forgotPassword(Request $request)
    {
        $this->requireMail();
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|email:rfc|max:254']);
        $account = MobileAccount::where('email', $data['email'])->whereNotNull('password')->first();
        if ($account) {
            $this->deliverCode($account, 'reset');
        }

        return response()->json(['data' => ['message' => 'If this account exists, a reset code has been sent.']]);
    }

    public function resetPassword(Request $request)
    {
        $this->requireMail();
        $this->normalizeEmail($request);
        $data = $request->validate(['email' => 'required|email:rfc|max:254', 'token' => 'required|digits:6', 'password' => $this->passwordRule()]);
        $account = MobileAccount::where('email', $data['email'])->whereNotNull('password')->first();
        if (! $account || ! $this->codes->consume($account, 'reset', (string) $data['token'])) {
            throw ValidationException::withMessages(['token' => 'This code is invalid or expired.']);
        }
        DB::transaction(function () use ($account, $data) {
            $account->forceFill(['password' => $data['password']])->save();
            $account->tokens()->delete();
        });

        return response()->json(['data' => ['password_reset' => true]]);
    }

    public function resendVerification(Request $request)
    {
        $this->requireMail();
        if (! $request->user()->email_verified_at) {
            $this->deliverCode($request->user(), 'verify');
        }

        return response()->json(['data' => ['message' => 'A verification code has been sent if verification is needed.']]);
    }

    public function verifyEmail(Request $request)
    {
        $this->requireMail();
        $data = $request->validate(['token' => 'required|digits:6']);
        $account = $request->user();
        if (! $account->email_verified_at) {
            if (! $this->codes->consume($account, 'verify', (string) $data['token'])) {
                throw ValidationException::withMessages(['token' => 'This code is invalid or expired.']);
            }
            $account->forceFill(['email_verified_at' => now()])->save();
        }

        return response()->json(['data' => ['user' => $account->publicData()]]);
    }

    public function destroy(Request $request)
    {
        $account = $request->user();
        $request->validate(['password' => 'nullable|string|max:128']);
        if ($account->password && (! is_string($request->input('password')) || ! Hash::check($request->input('password'), $account->password))) {
            throw ValidationException::withMessages(['password' => 'Confirm your password to delete your account.']);
        }
        if (! $account->password && $account->currentAccessToken()->created_at->lt(now()->subMinutes(10))) {
            return response()->json(['message' => 'Sign in again before deleting your account.', 'code' => 'reauthentication_required'], 403);
        }
        foreach ($account->identities()->where('provider', 'apple')->get() as $identity) {
            if ($identity->refresh_token) {
                $this->identities->revokeApple($identity->refresh_token, $identity->client_id);
            }
        }
        DB::transaction(function () use ($account) {
            $account->tokens()->delete();
            $account->delete();
        });

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function challenge(Request $request)
    {
        $data = $request->validate(['provider' => 'required|in:apple', 'platform' => 'sometimes|in:android,ios']);
        abort_unless($this->identities->enabled('apple'), 503, 'Apple sign-in is not configured.');

        return response()->json(['data' => $this->challenges->issue($data['platform'] ?? 'ios')]);
    }

    public function appleCallback(Request $request)
    {
        $data = $request->validate(['state' => 'required|string|max:200', 'code' => 'nullable|string|max:4096', 'id_token' => 'nullable|string|max:16384', 'error' => 'nullable|string|max:100', 'error_description' => 'nullable|string|max:500', 'user' => 'nullable|string|max:2048']);
        abort_unless($this->challenges->callbackState($data['state']), 400, 'Invalid sign-in state.');
        $package = config('mobile_auth.apple.android_package');
        abort_unless(preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\.[A-Za-z][A-Za-z0-9_]*)+$/', $package), 503);

        return redirect()->away('intent://callback?'.http_build_query(array_filter($data, fn ($value) => $value !== null), '', '&', PHP_QUERY_RFC3986).'#Intent;package='.$package.';scheme=signinwithapple;end');
    }

    public function google(Request $request)
    {
        return $this->social($request, 'google');
    }

    public function apple(Request $request)
    {
        return $this->social($request, 'apple');
    }

    public function linkGoogle(Request $request)
    {
        return $this->social($request, 'google', true);
    }

    public function linkApple(Request $request)
    {
        return $this->social($request, 'apple', true);
    }

    private function social(Request $request, string $provider, bool $link = false)
    {
        if ($link && ! $request->user()->email_verified_at) {
            return response()->json(['message' => 'Verify your email before linking a sign-in provider.', 'code' => 'email_verification_required'], 403);
        }
        $rules = ['device_name' => 'sometimes|string|max:100', 'name' => 'sometimes|string|max:100'];
        $rules += $provider === 'google' ? ['id_token' => 'required|string|max:16384'] : [
            'identity_token' => 'required|string|max:16384', 'authorization_code' => 'required|string|max:4096',
            'challenge_id' => 'required|uuid', 'nonce' => 'required|string|size:64', 'state' => 'required|string|max:200',
        ];
        $data = $request->validate($rules);
        $rawToken = $data[$provider === 'google' ? 'id_token' : 'identity_token'];
        $claims = $this->identities->verify($provider, $rawToken, $data['nonce'] ?? null);
        $refreshToken = null;
        $clientId = is_array($claims['aud']) ? $claims['aud'][0] : $claims['aud'];
        if ($provider === 'apple') {
            $this->challenges->consume($data['challenge_id'], $data['nonce'], $data['state']);
            $exchange = $this->identities->exchangeApple($data['authorization_code'], $clientId);
            $verified = $this->identities->verify('apple', $exchange['id_token'], $data['nonce']);
            if (! hash_equals($claims['sub'], $verified['sub']) || $verified['aud'] !== $claims['aud']) {
                throw ValidationException::withMessages(['identity_token' => 'Apple identity mismatch.']);
            }
            $refreshToken = $exchange['refresh_token'] ?? null;
        }
        $subject = $claims['sub'];

        return Cache::lock('mobile_identity_'.hash('sha256', $provider.':'.$subject), 15)->block(3, function () use ($request, $provider, $claims, $subject, $clientId, $refreshToken, $data, $link) {
            return DB::transaction(function () use ($request, $provider, $claims, $subject, $clientId, $refreshToken, $data, $link) {
                $identity = MobileIdentity::where('provider', $provider)->where('subject', $subject)->first();
                $account = $identity?->account;
                if ($link) {
                    if (($account && $account->id !== $request->user()->id) || $request->user()->identities()->where('provider', $provider)->where('subject', '!=', $subject)->exists()) {
                        return response()->json(['message' => 'This provider is already linked to an account.', 'code' => 'identity_already_linked'], 409);
                    }
                    $account = $request->user();
                }
                if (! $account) {
                    $email = mb_strtolower($claims['email'] ?? '');
                    $verified = in_array($claims['email_verified'] ?? false, [true, 'true', 1, '1'], true);
                    if (! $verified || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
                        throw ValidationException::withMessages(['email' => 'A verified email is required for your first sign-in.']);
                    }
                    // Linking by email alone would let another identity seize an existing account.
                    if (MobileAccount::where('email', $email)->exists()) {
                        return response()->json(['message' => 'Sign in to your existing account first, then link this provider.', 'code' => 'account_link_required'], 409);
                    }
                    try {
                        $account = MobileAccount::create(['name' => mb_substr($data['name'] ?? ($claims['name'] ?? 'SalaTime'), 0, 100), 'email' => $email, 'email_verified_at' => now()]);
                    } catch (QueryException $error) {
                        if (in_array($error->getCode(), ['23000', '23505'], true)) {
                            return response()->json(['message' => 'Sign in to your existing account first.', 'code' => 'account_link_required'], 409);
                        }
                        throw $error;
                    }
                }
                $identity = MobileIdentity::firstOrNew(['provider' => $provider, 'subject' => $subject]);
                $identity->mobile_account_id = $account->id;
                $identity->client_id = $clientId;
                if ($refreshToken !== null) {
                    $identity->refresh_token = $refreshToken;
                }
                $identity->save();

                $response = $this->session($account, $request);
                if (! $link) {
                    $this->welcomes->afterRegistration($account);
                }

                return $response;
            });
        });
    }

    private function session(MobileAccount $account, Request $request, int $status = 200)
    {
        $token = $account->createToken($request->input('device_name', 'SalaTime'), ['mobile:account'], now()->addDays((int) config('mobile_auth.token_days', 30)));
        $oldIds = $account->tokens()->orderByDesc('id')->skip(20)->take(1000)->pluck('id');
        if ($oldIds->isNotEmpty()) {
            $account->tokens()->whereIn('id', $oldIds)->delete();
        }

        return response()->json(['data' => ['user' => $account->publicData(), 'token' => $token->plainTextToken]], $status);
    }

    private function requireMail(): void
    {
        abort_unless(config('mobile_auth.mail_enabled'), 503, 'Email recovery is not configured.');
    }

    private function deliverCode(MobileAccount $account, string $purpose): void
    {
        try {
            $this->codes->send($account, $purpose);
        } catch (\Throwable $error) {
            // Never log codes, addresses, passwords or provider tokens.
            report(new \RuntimeException('SalaTime account email could not be delivered.'));
        }
    }
}
