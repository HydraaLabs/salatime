<?php

namespace App\Http\Controllers\Quran\API\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\Quran\Settings\SettingsResourceCollection;
use App\Models\Quran\DeviceInfo\DeviceInfo;
use App\Services\Setting\SettingService;
use Illuminate\Http\Request;
use Jenssegers\Agent\Facades\Agent;
use Stevebauman\Location\Facades\Location;

class SettingsController extends Controller
{
    public function __construct(SettingService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        try {
            $ipAddress = $request->ip();
            $this->getDeviceInfo($ipAddress);

            // Only the settings read is cached. Device/IP collection still runs
            // independently for every request and is never part of the cache key.
            $data = $this->service->getCachedFormattedSettings();

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully',
                'data' => new SettingsResourceCollection($data),
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function getDeviceInfo($ipAddress)
    {
        $osType = Agent::platform() == 0 ? 'Android' : Agent::platform();

        $existingDeviceInfo = DeviceInfo::where('os', $osType)
            ->where('ip_address', $ipAddress)
            ->first();

        // Avoid a remote GeoIP lookup when this device has already been seen.
        if ($existingDeviceInfo) {
            return $this;
        }

        $newIp = env('IS_DEMO_VERSION') ? '103.161.68.149' : $ipAddress;
        $position = Location::get($newIp);

        DeviceInfo::updateOrCreate(
            ['ip_address' => $ipAddress, 'os' => $osType],
            [
                'os' => $osType,
                'country' => $position ? $position->countryName : null,
                'state' => $position ? $position->cityName : null,
                'latitude' => $position ? $position->latitude : null,
                'longitude' => $position ? $position->longitude : null,
                'count' => 1,
            ]
        );

        return $this;
    }
}
