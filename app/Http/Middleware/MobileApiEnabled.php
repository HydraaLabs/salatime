<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MobileApiEnabled
{
    public function handle(Request $request, Closure $next)
    {
        if (! config('mobile_auth.enabled')) {
            return response()->json(['message' => 'Account services are unavailable.', 'code' => 'auth_unavailable'], 503);
        }
        if (strlen($request->getContent()) > 65536) {
            return response()->json(['message' => 'Request too large.'], 413);
        }

        return $next($request);
    }
}
