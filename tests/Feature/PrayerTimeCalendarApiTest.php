<?php

namespace Tests\Feature;

use App\Services\Prayer\PrayerCalendarService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PrayerTimeCalendarApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('cache.default', 'array');
        Cache::flush();
    }

    public function test_it_returns_the_complete_rolling_calendar_in_one_response(): void
    {
        $response = $this->postJson('/api/prayer-time-calendar', [
            'type' => 'automatic',
            'date' => '2026-09-08',
            'lat' => 34.0331,
            'lng' => -5.0003,
            'prayer_method' => 21,
            'school' => 'STANDARD',
            'timezone' => 'Africa/Casablanca',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.anchor_date', '2026-09-08')
            ->assertJsonPath('data.start_date', '2026-09-01')
            ->assertJsonPath('data.end_date', '2026-10-23')
            ->assertJsonPath('data.past_days', 7)
            ->assertJsonPath('data.future_days', 45)
            ->assertJsonPath('data.count', PrayerCalendarService::TOTAL_DAYS)
            ->assertJsonCount(PrayerCalendarService::TOTAL_DAYS, 'data.days')
            ->assertJsonPath('data.days.0.date', '2026-09-01')
            ->assertJsonPath('data.days.52.date', '2026-10-23')
            ->assertJsonPath('data.missing_dates', []);

        $days = $response->json('data.days');
        $this->assertCount(PrayerCalendarService::TOTAL_DAYS, array_unique(array_column($days, 'date')));
        $this->assertTrue(collect($days)->every(fn (array $day) => preg_match('/^\d{2}:\d{2}$/', $day['fajr_start']) === 1
            && preg_match('/^\d{2}:\d{2}$/', $day['isha_start']) === 1
        ));
    }

    public function test_it_rejects_an_invalid_location_context(): void
    {
        $this->postJson('/api/prayer-time-calendar', [
            'type' => 'automatic',
            'date' => '2026-09-08',
            'lat' => 95,
            'lng' => -5.0003,
            'prayer_method' => 21,
            'school' => 'STANDARD',
            'timezone' => 'Not/A_Timezone',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['lat', 'timezone']);
    }
}
