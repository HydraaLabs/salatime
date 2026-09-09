<?php

namespace App\Services\Prayer;

use App\Vendor\PrayerTimes\Method;
use App\Vendor\PrayerTimes\PrayerTimes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class PrayerPageService
{
    private const PRAYER_KEYS = ['Fajr', 'Sunrise', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'];

    public function schedule(string $slug, array $city, CarbonImmutable $start, int $days = 7): array
    {
        $timezone = $city['timezone'] ?? config('prayer_pages.timezone');
        $method = $city['method'] ?? config('prayer_pages.method', Method::METHOD_MOROCCO);
        $school = $city['school'] ?? PrayerTimes::SCHOOL_STANDARD;
        $start = $start->setTimezone($timezone)->startOfDay();
        $fingerprint = hash('sha256', json_encode([$city['latitude'], $city['longitude'], $timezone, $method, $school]));
        $cacheKey = sprintf('prayer-pages:v4:%s:%s:%s:%d', $fingerprint, $slug, $start->format('Y-m-d'), $days);

        return Cache::remember($cacheKey, now()->addHours(30), function () use ($city, $start, $days, $timezone, $method, $school) {
            $calculator = new PrayerTimes(
                $method,
                $school
            );
            $schedule = [];

            for ($offset = 0; $offset < $days; $offset++) {
                $date = $start->addDays($offset);
                $rawTimes = $calculator->getTimesForToday(
                    // Use the daytime offset on days when clocks change after midnight.
                    $date->format('Y-m-d').' 12:00:00',
                    $city['latitude'],
                    $city['longitude'],
                    $timezone
                );

                // At polar latitudes there may be no sunrise or sunset. Do not invent a timetable.
                $sun = date_sun_info($date->setTime(12, 0)->timestamp, $city['latitude'], $city['longitude']);
                $polar = is_bool($sun['sunrise']) || is_bool($sun['sunset']);
                $times = [];
                foreach (self::PRAYER_KEYS as $key) {
                    $value = $rawTimes[$key] ?? PrayerTimes::INVALID_TIME;
                    $times[$key] = $polar || $value === PrayerTimes::INVALID_TIME ? '—' : $value;
                }

                $schedule[] = [
                    'date' => $date,
                    'times' => $times,
                    'polar' => $polar,
                ];
            }

            return $schedule;
        });
    }

    public function nextPrayer(array $today, array $tomorrow, CarbonImmutable $now, ?array $yesterday = null): array
    {
        $candidates = [];
        foreach (array_filter([$yesterday, $today, $tomorrow]) as $day) {
            $previous = null;
            foreach (['Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha'] as $key) {
                $time = $day['times'][$key] ?? '—';
                if ($time === '—') {
                    continue;
                }
                $at = CarbonImmutable::createFromFormat('!Y-m-d H:i', $day['date']->format('Y-m-d').' '.$time, $now->timezone);
                // Isha can fall after midnight and belong to the previous prayer day.
                if ($previous !== null && $at->lt($previous)) {
                    $at = $at->addDay();
                }
                $previous = $at;
                if ($now->lt($at)) {
                    $candidates[] = ['key' => $key, 'time' => $time, 'tomorrow' => ! $at->isSameDay($now), 'at' => $at];
                }
            }
        }

        return collect($candidates)->sortBy(fn ($prayer) => $prayer['at']->timestamp)->first()
            ?? ['key' => null, 'time' => '—', 'tomorrow' => false, 'at' => null];
    }

    public function nearestCities(string $currentSlug, array $cities, int $limit = 8): array
    {
        $current = $cities[$currentSlug];

        return collect($cities)
            ->except($currentSlug)
            ->map(function (array $city, string $slug) use ($current) {
                $lat = deg2rad($city['latitude'] - $current['latitude']);
                $lng = deg2rad($city['longitude'] - $current['longitude']);
                $a = sin($lat / 2) ** 2
                    + cos(deg2rad($current['latitude'])) * cos(deg2rad($city['latitude'])) * sin($lng / 2) ** 2;

                return $city + [
                    'slug' => $slug,
                    'distance' => 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a)),
                ];
            })
            ->sortBy('distance')
            ->take($limit)
            ->values()
            ->all();
    }
}
