<?php

// Versioned portable sound defaults, aligned with the mobile notification models.
return [
    'prayerSounds' => [
        'before' => [
            'fajr' => 'moatheni_before_prayer_fajr',
            'sunrise' => 'moatheni_water',
            'dhuhr' => 'moatheni_before_prayer_dhuhr',
            'jumaa' => 'moatheni_before_prayer_jumaa',
            'asr' => 'moatheni_before_prayer_asr',
            'maghrib' => 'moatheni_before_prayer_maghrib',
            'isha' => 'moatheni_before_prayer_isha',
        ],
        'adhan' => [
            'fajr' => 'moatheni_on_prayer_fajr',
            'sunrise' => 'moatheni_bird',
            'dhuhr' => 'moatheni_on_prayer_dhuhr',
            'jumaa' => 'moatheni_on_prayer_jumaa',
            'asr' => 'moatheni_on_prayer_asr',
            'maghrib' => 'moatheni_on_prayer_maghrib',
            'isha' => 'moatheni_on_prayer_isha',
        ],
        'after' => [
            'fajr' => 'moatheni_after_prayer_fajr',
            'sunrise' => 'moatheni_short_sound',
            'dhuhr' => 'moatheni_after_prayer_dhuhr',
            'jumaa' => 'moatheni_short_sound',
            'asr' => 'moatheni_after_prayer_asr',
            'maghrib' => 'moatheni_after_prayer_maghrib',
            'isha' => 'moatheni_after_prayer_isha',
        ],
    ],
    'additionalSounds' => [
        'duha' => 'moatheni_duha',
        'lastThird' => 'moatheni_last_third',
        'friday' => 'moatheni_jumaa_hour',
        'morning' => 'moatheni_morning_azkar1',
        'evening' => 'moatheni_evening_azkar1',
        'mondayThursday' => 'moatheni_monday_fasting',
        'whiteDays' => 'moatheni_white_days',
        'fajrAlarm' => 'moatheni_ring1',
        'bedtime' => 'moatheni_sleep_azkar',
        'middleNight' => 'moatheni_midnight',
        'monday' => 'moatheni_monday_fasting',
        'thursday' => 'moatheni_thursday_fasting',
    ],
];
