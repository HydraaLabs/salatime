<?php

namespace App\Services\Mobile;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SocialIdentityVerifier
{
    private const JWKS = ['google' => 'https://www.googleapis.com/oauth2/v3/certs', 'apple' => 'https://appleid.apple.com/auth/keys'];

    public function enabled(string $provider): bool
    {
        if (! in_array($provider, ['google', 'apple'], true) || ! config('mobile_auth.'.$provider.'.client_ids')) {
            return false;
        }
        if ($provider === 'google') {
            $serverClientId = config('mobile_auth.google.server_client_id');
            $clientIds = config('mobile_auth.google.client_ids', []);

            // The app requests tokens for this Web client. Advertising another
            // audience would show a sign-in method whose tokens always fail.
            return is_string($serverClientId) && $serverClientId !== '' &&
                is_array($clientIds) && in_array($serverClientId, $clientIds, true);
        }

        return config('mobile_auth.apple.team_id') && config('mobile_auth.apple.key_id') &&
            is_file((string) config('mobile_auth.apple.private_key_path')) &&
            is_readable((string) config('mobile_auth.apple.private_key_path'));
    }

    public function appleEnabledFor(string $platform): bool
    {
        if (! $this->enabled('apple')) {
            return false;
        }
        $clientIds = config('mobile_auth.apple.client_ids', []);
        if ($platform === 'ios') {
            $clientId = config('mobile_auth.apple.ios_client_id');

            return is_string($clientId) && $clientId !== '' && in_array($clientId, $clientIds, true);
        }
        $clientId = config('mobile_auth.apple.client_id');
        $redirect = (string) config('mobile_auth.apple.redirect_uri');
        $package = (string) config('mobile_auth.apple.android_package');

        return $platform === 'android' && is_string($clientId) && $clientId !== '' &&
            in_array($clientId, $clientIds, true) && filter_var($redirect, FILTER_VALIDATE_URL) &&
            parse_url($redirect, PHP_URL_SCHEME) === 'https' &&
            preg_match('/^[A-Za-z][A-Za-z0-9_]*(?:\\.[A-Za-z][A-Za-z0-9_]*)+$/', $package) === 1;
    }

    public function verify(string $provider, string $token, ?string $nonce = null): array
    {
        if (! $this->enabled($provider)) {
            throw new HttpException(503, 'This sign-in provider is not configured.');
        }
        try {
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                throw new \UnexpectedValueException;
            }
            $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true, 16, JSON_THROW_ON_ERROR);
            if (($header['alg'] ?? '') !== 'RS256' || ! is_string($header['kid'] ?? null) || strlen($header['kid']) > 256) {
                throw new \UnexpectedValueException;
            }
            $jwks = $this->keys($provider);
            $known = array_column($jwks['keys'] ?? [], 'kid');
            if (! in_array($header['kid'], $known, true) && Cache::add('mobile_jwks_refresh_'.$provider, true, 60)) {
                Cache::forget('mobile_jwks_'.$provider);
                $jwks = $this->keys($provider);
            }
            $keys = JWK::parseKeySet(['keys' => array_values(array_filter($jwks['keys'] ?? [], fn ($key) => ($key['kty'] ?? '') === 'RSA' && ($key['alg'] ?? 'RS256') === 'RS256' && ($key['use'] ?? 'sig') === 'sig'))], 'RS256');
            $claims = (array) JWT::decode($token, $keys);
            $issuers = $provider === 'google' ? ['accounts.google.com', 'https://accounts.google.com'] : ['https://appleid.apple.com'];
            $audiences = config('mobile_auth.'.$provider.'.client_ids', []);
            $aud = $claims['aud'] ?? null;
            $aud = is_string($aud) ? [$aud] : $aud;
            if (! in_array($claims['iss'] ?? null, $issuers, true) || ! is_array($aud) || ! array_intersect($audiences, $aud) ||
                (count($aud) > 1 && ! in_array($claims['azp'] ?? null, $audiences, true)) ||
                ! is_int($claims['exp'] ?? null) || $claims['exp'] <= time() ||
                ! is_int($claims['iat'] ?? null) || $claims['iat'] > time() + 60 ||
                ! is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255) {
                throw new \UnexpectedValueException;
            }
            if ($provider === 'apple' && ($nonce === null || ! is_string($claims['nonce'] ?? null) || ! hash_equals(hash('sha256', $nonce), $claims['nonce']))) {
                throw new \UnexpectedValueException;
            }

            return $claims;
        } catch (HttpException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages(['id_token' => 'The identity token could not be verified.']);
        }
    }

    private function keys(string $provider): array
    {
        return Cache::remember('mobile_jwks_'.$provider, 3600, function () use ($provider) {
            try {
                $response = Http::acceptJson()->connectTimeout(3)->timeout(8)->get(self::JWKS[$provider])->throw();
                if (strlen($response->body()) > 65536 || ! is_array($response->json('keys'))) {
                    throw new \UnexpectedValueException;
                }

                return $response->json();
            } catch (\Throwable $error) {
                throw new HttpException(503, 'Unable to contact the identity provider.');
            }
        });
    }

    private function appleSecret(string $clientId): string
    {
        $key = file_get_contents((string) config('mobile_auth.apple.private_key_path'));

        return JWT::encode(['iss' => config('mobile_auth.apple.team_id'), 'iat' => time(), 'exp' => time() + 300,
            'aud' => 'https://appleid.apple.com', 'sub' => $clientId], $key, 'ES256', config('mobile_auth.apple.key_id'));
    }

    public function exchangeApple(string $code, string $clientId): array
    {
        $body = ['grant_type' => 'authorization_code', 'code' => $code, 'client_id' => $clientId, 'client_secret' => $this->appleSecret($clientId)];
        if ($clientId === config('mobile_auth.apple.client_id') && config('mobile_auth.apple.redirect_uri')) {
            $body['redirect_uri'] = config('mobile_auth.apple.redirect_uri');
        }
        try {
            $result = Http::asForm()->acceptJson()->connectTimeout(3)->timeout(10)->post('https://appleid.apple.com/auth/token', $body)->throw()->json();
            if (! is_string($result['id_token'] ?? null)) {
                throw new \UnexpectedValueException;
            }

            return $result;
        } catch (\Throwable $error) {
            throw ValidationException::withMessages(['authorization_code' => 'Apple could not verify this authorization code. Start sign-in again.']);
        }
    }

    public function revokeApple(string $refreshToken, string $clientId): void
    {
        try {
            Http::asForm()->connectTimeout(3)->timeout(10)->post('https://appleid.apple.com/auth/revoke', [
                'client_id' => $clientId, 'client_secret' => $this->appleSecret($clientId),
                'token' => $refreshToken, 'token_type_hint' => 'refresh_token',
            ])->throw();
        } catch (\Throwable $error) {
            throw new HttpException(503, 'Apple authorization could not be revoked. Please retry account deletion.');
        }
    }
}
