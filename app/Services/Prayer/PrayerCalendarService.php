<?php

namespace App\Services\Prayer;

use App\Models\Quran\Prayer\PrayerTime;
use App\Vendor\PrayerTimes\PrayerTimes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class PrayerCalendarService
{
    public const PAST_DAYS = 7;

    public const FUTURE_DAYS = 45;

    public const TOTAL_DAYS = self::PAST_DAYS + self::FUTURE_DAYS + 1;

    private const CACHE_VERSION = 'v1';

    private const AUTOMATIC_CACHE_DAYS = 30;

    /**
     * Build the rolling prayer calendar for coordinates and a calculation
     * context. Each day is cached independently so the next daily refresh
     * normally calculates only the newly exposed future day.
     */
    public function automatic(array $context): array
    {
        $range = $this->range($context['date'], $context['timezone']);
        $calculator = new PrayerTimes((int) $context['prayer_method'], $context['school']);
        $days = [];

        foreach ($range['dates'] as $date) {
            $cacheKey = $this->automaticCacheKey($context, $date);
            $days[] = Cache::remember(
                $cacheKey,
                now()->addDays(self::AUTOMATIC_CACHE_DAYS),
                fn () => $this->calculateAutomaticDay($calculator, $context, $date)
            );
        }

        return $this->calendarPayload($range, $days);
    }

    /**
     * Return the same rolling range for legacy manually imported city data in
     * one database query. Missing dates remain explicit and are not mistaken
     * for valid offline data by the mobile client.
     */
    public function manual(array $context): array
    {
        $range = $this->range($context['date'], config('app.timezone', 'UTC'));
        $records = PrayerTime::query()
            ->where('city', $context['city'])
            ->whereBetween('date', [$range['start_date'], $range['end_date']])
            ->select([
                'date',
                'imsak',
                'sunrise',
                'fajr_start',
                'zuhr_start',
                'asr_start',
                'maghrib_start',
                'isha_start',
                'sehri',
                'iftar',
            ])
            ->get()
            ->keyBy(fn (PrayerTime $record) => $record->date->format('Y-m-d'));

        $missingDates = [];
        $days = [];

        foreach ($range['dates'] as $date) {
            $dateString = $date->format('Y-m-d');
            $record = $records->get($dateString);

            if ($record === null) {
                $missingDates[] = $dateString;
                $days[] = $this->emptyDay($date);

                continue;
            }

            $values = $record->toArray();
            $days[] = [
                'date' => $dateString,
                'is_jumma' => $date->isFriday(),
                'imsak' => $values['imsak'],
                'fajr_start' => $values['fajr_start'],
                'sunrise' => $values['sunrise'],
                'zuhr_start' => $values['zuhr_start'],
                'asr_start' => $values['asr_start'],
                'maghrib_start' => $values['maghrib_start'],
                'isha_start' => $values['isha_start'],
                'sehri' => $values['sehri'] ?? $values['imsak'],
                'iftar' => $values['iftar'] ?? $values['maghrib_start'],
            ];
        }

        return $this->calendarPayload($range, $days, $missingDates);
    }

    private function range(string $anchorDate, string $timezone): array
    {
        $anchor = CarbonImmutable::createFromFormat('Y-m-d', $anchorDate, $timezone)
            ->startOfDay();
        $start = $anchor->subDays(self::PAST_DAYS);
        $end = $anchor->addDays(self::FUTURE_DAYS);
        $dates = [];

        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $dates[] = $date;
        }

        return [
            'anchor_date' => $anchor->format('Y-m-d'),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'dates' => $dates,
        ];
    }

    private function calculateAutomaticDay(
        PrayerTimes $calculator,
        array $context,
        CarbonImmutable $date
    ): array {
        $dateString = $date->format('Y-m-d');
        $times = $calculator->getTimesForToday(
            $dateString,
            (float) $context['lat'],
            (float) $context['lng'],
            $context['timezone']
        );

        return [
            'date' => $dateString,
            'is_jumma' => $date->isFriday(),
            'imsak' => $times['Imsak'],
            'fajr_start' => $times['Fajr'],
            'sunrise' => $times['Sunrise'],
            'zuhr_start' => $times['Dhuhr'],
            'asr_start' => $times['Asr'],
            'maghrib_start' => $times['Maghrib'],
            'isha_start' => $times['Isha'],
            'sehri' => $times['Sehri'],
            'iftar' => $times['Iftar'],
        ];
    }

    private function automaticCacheKey(array $context, CarbonImmutable $date): string
    {
        $signature = [
            'date' => $date->format('Y-m-d'),
            'lat' => number_format((float) $context['lat'], 3, '.', ''),
            'lng' => number_format((float) $context['lng'], 3, '.', ''),
            'prayer_method' => (int) $context['prayer_method'],
            'school' => $context['school'],
            'timezone' => $context['timezone'],
        ];

        return sprintf(
            'salatime:prayer-calendar:%s:%s',
            self::CACHE_VERSION,
            hash('sha256', json_encode($signature, JSON_UNESCAPED_SLASHES))
        );
    }

    private function emptyDay(CarbonImmutable $date): array
    {
        return [
            'date' => $date->format('Y-m-d'),
            'is_jumma' => $date->isFriday(),
            'imsak' => '0:00',
            'fajr_start' => '0:00',
            'sunrise' => '0:00',
            'zuhr_start' => '0:00',
            'asr_start' => '0:00',
            'maghrib_start' => '0:00',
            'isha_start' => '0:00',
            'sehri' => '0:00',
            'iftar' => '0:00',
        ];
    }

    private function calendarPayload(array $range, array $days, array $missingDates = []): array
    {
        return [
            'anchor_date' => $range['anchor_date'],
            'start_date' => $range['start_date'],
            'end_date' => $range['end_date'],
            'past_days' => self::PAST_DAYS,
            'future_days' => self::FUTURE_DAYS,
            'count' => count($days),
            'days' => $days,
            'missing_dates' => $missingDates,
        ];
    }
}
