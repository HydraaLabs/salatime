<?php

namespace App\Http\Middleware;

use App\Models\Mobile\MobileAccount;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

/** Bearer only; never accepts an administrator's session or API token. */
class AuthenticateMobileAccount implements \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests
{
    public function handle(Request $request, Closure $next)
    {
        $plain = $request->bearerToken();
        $token = is_string($plain) && strlen($plain) <= 512
            ? PersonalAccessToken::findToken($plain) : null;
        if (! $token || ! $token->tokenable instanceof MobileAccount ||
            ! $token->can('mobile:account') ||
            ($token->expires_at && $token->expires_at->isPast())) {
            return response()->json(['message' => 'Authentication required.', 'code' => 'unauthenticated'], 401);
        }
        $account = $token->tokenable->withAccessToken($token);
        $request->setUserResolver(fn () => $account);
        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
