<?php

namespace App\Services\Mobile;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppleChallenge
{
    public function issue(string $platform): array
    {
        $id = (string) Str::uuid();
        $nonce = bin2hex(random_bytes(32));
        $state = $id.'.'.bin2hex(random_bytes(32));
        Cache::put('mobile_apple_challenge_'.$id, ['nonce' => hash('sha256', $nonce), 'state' => hash('sha256', $state), 'platform' => $platform], 600);

        return ['challenge_id' => $id, 'nonce' => $nonce, 'state' => $state];
    }

    public function callbackState(string $state): bool
    {
        $id = explode('.', $state)[0];
        if (! Str::isUuid($id)) {
            return false;
        }
        $row = Cache::get('mobile_apple_challenge_'.$id);

        return $row && $row['platform'] === 'android' && hash_equals($row['state'], hash('sha256', $state));
    }

    public function consume(string $id, string $nonce, string $state): void
    {
        Cache::lock('mobile_apple_challenge_lock_'.$id, 10)->block(3, function () use ($id, $nonce, $state) {
            $row = Cache::get('mobile_apple_challenge_'.$id);
            if (! $row || ! hash_equals($row['nonce'], hash('sha256', $nonce)) || ! hash_equals($row['state'], hash('sha256', $state))) {
                throw ValidationException::withMessages(['nonce' => 'The sign-in challenge expired or was already used.']);
            }
            Cache::forget('mobile_apple_challenge_'.$id);
        });
    }
}
