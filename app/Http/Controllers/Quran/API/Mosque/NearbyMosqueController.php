<?php

namespace App\Http\Controllers\Quran\API\Mosque;

use App\Http\Controllers\Controller;
use App\Services\Mosque\NearbyMosqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NearbyMosqueController extends Controller
{
    public function __construct(private NearbyMosqueService $mosques)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $input = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'integer', 'between:1,'.NearbyMosqueService::MAX_RADIUS_KM],
            'lang' => ['nullable', 'string', 'regex:/^[a-z]{2,3}$/'],
        ]);
        $result = $this->mosques->nearby(
            (float) $input['lat'],
            (float) $input['lng'],
            (int) ($input['radius'] ?? 2),
            $input['lang'] ?? 'en',
        );
        if ($result === null) {
            // Never seen tile and every Overpass server unavailable.
            return response()->json(['status' => false, 'message' => 'Mosque search temporarily unavailable'], 503)
                ->header('Retry-After', '60');
        }

        return response()->json(['status' => true] + $result)
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
