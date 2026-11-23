<?php

namespace App\Http\Controllers\Quran\API\PrayerTime;

use App\Http\Controllers\Controller;
use App\Models\Quran\Prayer\PrayerTime;
use App\Services\Prayer\PrayerCalendarService;
use App\Services\Prayer\PrayerPageCatalog;
use App\Vendor\PrayerTimes\Method;
use App\Vendor\PrayerTimes\PrayerTimes;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PrayerTimeController extends Controller
{
    /**
     * Retrieve the columns used for prayer times.
     *
     * @return array
     */
    public function columns()
    {
        return [
            'date',
            'imsak',
            'sunrise',
            'fajr_start',
            'zuhr_start',
            'asr_start',
            'maghrib_start',
            'isha_start',
        ];
    }


    public function getPrayerTime(Request $request)
    {
        $request->validate([
            'type' => 'required|in:manual,automatic',
        ]);

        switch ($request->type) {
            case 'manual':
                return $this->getManualPrayerTime($request);
            case 'automatic':
                return $this->getAutomaticPrayerTime($request);
            default:
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid request type',
                    'data' => $this->defaultPrayerTimeResponse(),
                ]);
        }
    }

    /**
     * Return the complete J-7 to J+45 prayer calendar in one HTTP request.
     */
    public function getPrayerCalendar(Request $request, PrayerCalendarService $calendarService)
    {
        $request->validate([
            'type' => 'required|in:manual,automatic',
            'date' => 'required|date_format:Y-m-d',
        ]);

        if ($request->get('type') === 'manual') {
            $context = $request->validate([
                'type' => 'required|in:manual',
                'date' => 'required|date_format:Y-m-d',
                'city' => 'required|string|max:255',
            ]);
        } else {
            $context = $request->validate([
                'type' => 'required|in:automatic',
                'date' => 'required|date_format:Y-m-d',
                'lat' => 'required|numeric|between:-90,90',
                'lng' => 'required|numeric|between:-180,180',
                'prayer_method' => 'required|in:0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,99',
                'school' => 'required|in:HANAFI,STANDARD',
                'timezone' => 'required|timezone',
            ]);
        }

        try {
            if ($context['type'] === 'manual') {
                $data = $calendarService->manual($context);
            } else {
                $data = $calendarService->automatic($context);
            }

            return response()->json([
                'status' => true,
                'message' => 'Prayer time calendar retrieved successfully.',
                'data' => $data,
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch prayer time calendar.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Fetch prayer time data manually based on the date and city.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getManualPrayerTime(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'city' => 'required|string|max:255',
        ]);

        try {
            $data = PrayerTime::query()
                ->where('date', $request->get('date'))
                ->where('city', $request->get('city'))
                ->select($this->columns())
                ->first();

            if ($data === null) {
                return response()->json([
                    'status' => false,
                    'message' => 'Data not found.',
                    'data' => $this->defaultPrayerTimeResponse(),
                ]);
            }

            $data['is_jumma'] = date('l', strtotime($request->get('date'))) === 'Friday';
            $this->fillDefaultPrayerTimeValues($data);

            return response()->json([
                'status' => true,
                'message' => 'Manual prayer time data retrieved successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Data not found.',
                'data' => $this->defaultPrayerTimeResponse(),
            ]);
        }
    }

    /**
     * Fetch automatic prayer times based on location and calculation methods.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAutomaticPrayerTime(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'prayer_method' => 'required|in:0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,99',
            'school' => 'required|in:HANAFI,STANDARD',
            'timezone' => 'required|timezone',
            'date' => 'required|date_format:Y-m-d',
        ]);

        try {
            $prayerTimes = new PrayerTimes($request->prayer_method, $request->school);
            $times = $prayerTimes->getTimesForToday(
                // Use the daytime offset on days when clocks change after midnight.
                $request->date.' 12:00:00',
                (float) $request->lat,
                (float) $request->lng,
                $request->timezone
            );

            $data = [
                'date' => Carbon::parse($request->date)->format('Y-m-d'),
                'is_jumma' => date('l', strtotime($request->get('date'))) === 'Friday',
                'imsak' => $times['Imsak'],
                'fajr_start' => $times['Fajr'],
                'sunrise' => $times['Sunrise'],
                'zuhr_start' => $times['Dhuhr'],
                'asr_start' => $times['Asr'],
                'maghrib_start' => $times['Maghrib'],
                'isha_start' => $times['Isha'],
                'sehri' => $times['Sehri'],
                'iftar' => $times['Iftar']
            ];

            return response()->json([
                'status' => true,
                'message' => 'Automatic prayer time data retrieved successfully.',
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch prayer times.',
                'data' => $this->defaultPrayerTimeResponse(),
            ]);
        }
    }

    /**
     * Provide default prayer time values.
     *
     * @return array
     */
    private function defaultPrayerTimeResponse(): array
    {
        return [
            'date' => '',
            'imsak' => '0:00',
            'fajr_start' => '0:00',
            'sunrise' => '0:00',
            'zuhr_start' => '0:00',
            'asr_start' => '0:00',
            'maghrib_start' => '0:00',
            'isha_start' => '0:00',
        ];
    }

    /**
     * Fill default values for nullable prayer time fields.
     *
     * @param array $data
     */
    private function fillDefaultPrayerTimeValues($data)
    {
        $fields = ['date', 'imsak', 'sunrise', 'fajr_start', 'zuhr_start', 'asr_start', 'maghrib_start', 'isha_start','sehri','iftar'];
        foreach ($fields as $field) {
            $data[$field] = $data[$field] ?? '0:00';
        }
        return $this;
    }

    public function prayerByLocation(Request $request, PrayerPageCatalog $catalog)
    {
        $validated = $request->validate([
            'lat'      => 'required|numeric|between:-90,90',
            'lng'      => 'required|numeric|between:-180,180',
            'timezone' => 'required|timezone',
            'lang'     => 'nullable|string|in:en,ar,fr,es,bn,hi',
        ]);

        $lat      = (float) $validated['lat'];
        $lng      = (float) $validated['lng'];
        $timezone = $validated['timezone'];
        $locale   = $validated['lang'] ?? 'en';
        $now      = CarbonImmutable::now($timezone);
        $date     = $now->format('Y-m-d');

        // Pick regional calculation defaults from the nearest catalog city.
        $nearest = $catalog->nearestCity($lat, $lng);
        $method  = $nearest['method'] ?? Method::METHOD_MWL;
        $school  = $nearest['school'] ?? PrayerTimes::SCHOOL_STANDARD;

        // Tile-cached reverse geocode (0.02° ≈ 2 km) to respect Nominatim limits.
        $tileLat = round(round($lat / 0.02) * 0.02, 2);
        $tileLng = round(round($lng / 0.02) * 0.02, 2);
        $geoKey  = sprintf('salatime:reverse-geo:v1:%.2f:%.2f', $tileLat, $tileLng);
        $city    = Cache::remember($geoKey, now()->addDays(7), function () use ($tileLat, $tileLng, $nearest, $locale) {
            try {
                $response = Http::timeout(4)
                    ->withHeaders(['User-Agent' => 'SalaTime/1.0 (+https://salatime.net; contact@salatime.net)'])
                    ->get('https://nominatim.openstreetmap.org/reverse', [
                        'lat'    => $tileLat,
                        'lon'    => $tileLng,
                        'format' => 'json',
                    ]);

                if ($response->successful()) {
                    $addr     = $response->json('address') ?? [];
                    $cityName = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? null;
                    $country  = $addr['country'] ?? '';
                    if (is_string($cityName) && $cityName !== '') {
                        return $country !== '' ? "{$cityName}, {$country}" : $cityName;
                    }
                }
            } catch (\Throwable) {
            }

            if ($nearest !== null) {
                return ($nearest['names'][$locale] ?? $nearest['names']['en'])
                    .', '.($nearest['country_names'][$locale] ?? $nearest['country_names']['en']);
            }

            return 'Your Location';
        });

        try {
            $pt    = new PrayerTimes($method, $school);
            $times = $pt->getTimesForToday($date.' 12:00:00', $lat, $lng, $timezone);
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Failed to calculate prayer times.'], 500);
        }

        // Determine the next obligatory prayer (never highlight Sunrise as next prayer).
        $nextPrayerKey = null;
        $previousAt    = null;
        foreach (['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'] as $key) {
            $raw = $times[$key] ?? null;
            if (! $raw || $raw === PrayerTimes::INVALID_TIME) {
                continue;
            }
            $at = CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$raw, $timezone);
            if ($previousAt !== null && $at->lt($previousAt)) {
                $at = $at->addDay();
            }
            $previousAt = $at;
            if ($now->lt($at)) {
                $nextPrayerKey = $key;
                break;
            }
        }
        if ($nextPrayerKey === null && ! empty($times['Fajr']) && $times['Fajr'] !== PrayerTimes::INVALID_TIME) {
            $nextPrayerKey = 'Fajr';
        }

        $prayers = [];
        foreach (['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'] as $key) {
            $raw = $times[$key] ?? null;
            $prayers[] = [
                'name'    => $key,
                'time'    => ($raw && $raw !== PrayerTimes::INVALID_TIME)
                    ? CarbonImmutable::createFromFormat('!Y-m-d H:i', $date.' '.$raw, $timezone)->format('h:i A')
                    : '—',
                'is_next' => $key === $nextPrayerKey,
            ];
        }

        $nearestCityPayload = null;
        $nearestCityUrl = null;
        if ($nearest !== null) {
            $urls = [];
            foreach (array_keys(config('prayer_pages.locales')) as $loc) {
                $urls[$loc] = $catalog->url($loc, $nearest['country_code'], $nearest['slug']);
            }
            $nearestCityUrl = $urls[$locale] ?? $urls['en'];
            $nearestCityPayload = [
                'names' => $nearest['names'],
                'urls' => $urls,
            ];
        }

        return response()->json([
            'status'           => true,
            'city'             => $city,
            'method'           => $method,
            'school'           => $school,
            'method_label'     => __('prayer_pages.methods.'.$method, [], $locale),
            'nearest_city_url' => $nearestCityUrl,
            'nearest_city'     => $nearestCityPayload,
            'prayers'          => $prayers,
        ]);
    }

    public function getCities()
    {
        $cities = PrayerTime::select('city')->distinct()->orderBy('city', 'ASC')->pluck('city')->toArray();

        return response()->json([
            'status' => true,
            'message' => 'Data fetched successfully',
            'data' => $cities,
        ]);
    }


}
