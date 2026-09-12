<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Mobile\MobileAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PreferencesController extends Controller
{
    private const REMINDER_ANCHORS = [
        'fajrAlarm' => ['beforeFajr', 'afterFajr'],
        'duha' => ['beforeDhuhr', 'afterSunrise'],
        'morning' => ['afterFajr'],
        'evening' => ['afterAsr', 'beforeMaghrib'],
        'bedtime' => ['afterIsha'],
        'monday' => ['afterIsha', 'clock'],
        'thursday' => ['afterIsha', 'clock'],
        'whiteDays' => ['afterIsha', 'clock'],
        'mondayThursday' => ['clock'],
        'middleNight' => ['beforeMiddleNight'],
        'lastThird' => ['beforeLastThird'],
        'friday' => ['beforeMaghrib', 'beforeDhuhr'],
    ];

    private const MAPS = ['prayerAdjustments', 'sounds', 'reminders', 'prayerNotifications', 'prayerNotificationSettings', 'widgets', 'silence', 'reader', 'additionalReminders'];

    private const TOP = 'schemaVersion,themeMode,language,country,homeLayout,use24HourFormat,calculationMethod,madhab,prayerAdjustments,sounds,reminders,prayerNotifications,prayerNotificationSettings,widgets,silence,hijriOffset,reader,additionalReminders';

    private function soundRule(): \Closure
    {
        return function ($attribute, $value, $fail) {
            if (! is_string($value) || (! in_array($value, config('mobile_sound_keys', []), true) && ! preg_match('/^custom_[a-f0-9]{64}$/', $value))) {
                $fail('Unknown notification sound.');
            }
        };
    }

    public function show(Request $request)
    {
        return response()->json(['data' => $this->snapshot($request->user()->id)]);
    }

    public function update(Request $request)
    {
        $rules = [
            'version' => 'required|integer|min:0|max:2147483646',
            'preferences' => ['present', 'array:'.self::TOP],
            'preferences.schemaVersion' => ['required_with:preferences', 'integer', Rule::in([1])],
            'preferences.themeMode' => ['sometimes', Rule::in(['daylight', 'light', 'dark'])],
            'preferences.language' => ['sometimes', Rule::in(['en', 'fr', 'ar', 'tr', 'ur', 'id', 'ms', 'es', 'bn', 'fa'])],
            'preferences.country' => ['sometimes', 'string', 'regex:/^[A-Z]{2}$/'],
            'preferences.homeLayout' => ['sometimes', Rule::in(['modern', 'classic'])],
            'preferences.use24HourFormat' => ['sometimes', 'boolean'],
            'preferences.calculationMethod' => ['sometimes', 'string', Rule::in(['0', '1', '2', '3', '4', '5', '7', '8', '9', '10', '11', '12', '13', '14', '15', '16', '17', '18', '19', '20', '21', '22', '23'])],
            'preferences.madhab' => ['sometimes', Rule::in(['STANDARD', 'HANAFI'])],
            'preferences.prayerAdjustments' => ['sometimes', 'array:fajr,sunrise,zuhr,asr,maghrib,isha,sehri,iftar'],
            'preferences.prayerAdjustments.*' => ['integer', 'between:-120,120'],
            'preferences.sounds' => ['sometimes', 'array:adhan,before,after'],
            'preferences.sounds.*' => ['string', $this->soundRule()],
            'preferences.reminders' => ['sometimes', 'array:beforeEnabled,afterEnabled,beforeMinutes,afterMinutes'],
            'preferences.prayerNotifications' => ['sometimes', 'array:1,2,3,4,5'],
            'preferences.prayerNotifications.*' => ['boolean'],
            'preferences.prayerNotificationSettings' => ['sometimes', 'array:before,adhan,after'],
            'preferences.prayerNotificationSettings.*' => ['array:fajr,sunrise,dhuhr,jumaa,asr,maghrib,isha'],
            'preferences.prayerNotificationSettings.*.*' => ['array:enabled,sound,minutes'],
            'preferences.prayerNotificationSettings.*.*.enabled' => ['sometimes', 'boolean'],
            'preferences.prayerNotificationSettings.*.*.sound' => ['sometimes', 'string', $this->soundRule()],
            'preferences.widgets' => ['sometimes', 'array:countdown,seconds,city,date,illustration,opacity'],
            'preferences.widgets.opacity' => ['sometimes', 'integer', 'between:0,100'],
            'preferences.silence' => ['sometimes', 'array:enabled,delay,duration,fridayDuration,fridayOverride,prayers'],
            'preferences.silence.delay' => ['sometimes', 'integer', 'between:0,60'],
            'preferences.silence.duration' => ['sometimes', 'integer', 'between:5,120'],
            'preferences.silence.fridayDuration' => ['sometimes', 'integer', 'between:5,120'],
            'preferences.silence.prayers' => ['sometimes', 'array', 'max:5'],
            'preferences.silence.prayers.*' => ['integer', 'distinct', 'between:1,5'],
            'preferences.hijriOffset' => ['sometimes', 'integer', 'between:-2,2'],
            'preferences.reader' => ['sometimes', 'array:arabicSize,translationSize,font,translator,translation,goal'],
            'preferences.reader.arabicSize' => ['sometimes', 'numeric', 'between:10,64'],
            'preferences.reader.translationSize' => ['sometimes', 'numeric', 'between:10,64'],
            'preferences.reader.font' => ['sometimes', Rule::in(['Scheherazade New', 'Amiri', 'AmiriQuran', 'Lateef', 'NotoKufiArabic', 'NotoNaskhArabic', 'NotoNastaliqUrdu', 'NotoSansArabic', 'ReadexPro'])],
            'preferences.reader.translator' => ['sometimes', 'string', 'regex:/^[0-9]{1,5}$/'],
            'preferences.reader.translation' => ['sometimes', 'string', 'max:100'],
            'preferences.reader.goal' => ['sometimes', 'integer', 'between:1,1000'],
            'preferences.additionalReminders' => ['sometimes', 'array:'.implode(',', array_keys(self::REMINDER_ANCHORS))],
            'preferences.additionalReminders.*' => ['array:enabled,sound,minutes,useDefaultSound,anchor'],
            'preferences.additionalReminders.*.enabled' => ['sometimes', 'boolean'],
            'preferences.additionalReminders.*.useDefaultSound' => ['sometimes', 'boolean'],
            'preferences.additionalReminders.*.sound' => ['sometimes', 'string', $this->soundRule()],
            'preferences.additionalReminders.*.minutes' => ['sometimes', 'integer', 'between:0,1439'],
        ];
        foreach (['before', 'adhan', 'after'] as $phase) {
            $rules['preferences.prayerNotificationSettings.'.$phase.'.*.minutes'] = ['sometimes', 'integer', $phase === 'adhan' ? 'in:0' : 'between:0,120'];
        }
        foreach (self::REMINDER_ANCHORS as $type => $anchors) {
            $anchor = $request->input('preferences.additionalReminders.'.$type.'.anchor');
            $max = $anchor === 'clock' || ($anchor === null && in_array($type, ['mondayThursday', 'whiteDays'], true)) ? 1439 : 120;
            $rules['preferences.additionalReminders.'.$type.'.minutes'] = ['sometimes', 'integer', 'between:0,'.$max];
            $rules['preferences.additionalReminders.'.$type.'.anchor'] = ['sometimes', 'string', Rule::in($anchors)];
        }
        foreach (['beforeEnabled', 'afterEnabled'] as $key) {
            $rules['preferences.reminders.'.$key] = ['sometimes', 'boolean'];
        }
        foreach (['beforeMinutes', 'afterMinutes'] as $key) {
            $rules['preferences.reminders.'.$key] = ['sometimes', 'integer', 'between:1,60'];
        }
        foreach (['countdown', 'seconds', 'city', 'date', 'illustration'] as $key) {
            $rules['preferences.widgets.'.$key] = ['sometimes', 'boolean'];
        }
        foreach (['enabled', 'fridayOverride'] as $key) {
            $rules['preferences.silence.'.$key] = ['sometimes', 'boolean'];
        }
        $values = $request->validate($rules);
        // JSON booleans and integers must retain their types on every device.
        $this->assertJsonTypes($values['preferences'], $rules);
        $values['preferences'] = $this->portableSounds($values['preferences']);

        return DB::transaction(function () use ($request, $values) {
            MobileAccount::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->snapshot($request->user()->id);
            if ($snapshot['version'] !== (int) $values['version']) {
                return response()->json(['message' => 'Preferences changed on another device.', 'code' => 'preferences_conflict', 'data' => $snapshot], 409);
            }
            DB::table('mobile_preferences')->updateOrInsert(['mobile_account_id' => $request->user()->id], [
                'version' => $snapshot['version'] + 1,
                'preferences' => json_encode($values['preferences'], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return response()->json(['data' => $this->snapshot($request->user()->id)]);
        });
    }

    private function portableSounds(array $preferences): array
    {
        foreach ($preferences['prayerNotificationSettings'] ?? [] as $phase => $prayers) {
            foreach ($prayers as $prayer => $setting) {
                if (isset($setting['sound']) && str_starts_with($setting['sound'], 'custom_')) {
                    $preferences['prayerNotificationSettings'][$phase][$prayer]['sound'] = config('mobile_notification_defaults.prayerSounds.'.$phase.'.'.$prayer);
                }
            }
        }
        foreach ($preferences['additionalReminders'] ?? [] as $type => $setting) {
            if (isset($setting['sound']) && str_starts_with($setting['sound'], 'custom_')) {
                $preferences['additionalReminders'][$type]['sound'] = config('mobile_notification_defaults.additionalSounds.'.$type);
            }
        }

        return $preferences;
    }

    private function assertJsonTypes(array $preferences, array $rules): void
    {
        // Laravel's boolean/integer rules also accept strings. Do not persist
        // these strings into a schema consumed by strongly typed mobile clients.
        $validator = validator(['preferences' => $preferences], $rules);
        foreach ($validator->getRules() as $path => $constraints) {
            if (! str_starts_with($path, 'preferences.')) {
                continue;
            }
            $value = data_get(['preferences' => $preferences], $path, '__missing__');
            if ($value === '__missing__') {
                continue;
            }
            $valid = (! in_array('boolean', $constraints) || is_bool($value)) &&
                (! in_array('integer', $constraints) || is_int($value)) &&
                (! in_array('numeric', $constraints) || is_int($value) || is_float($value));
            if (! $valid) {
                throw \Illuminate\Validation\ValidationException::withMessages([$path => 'Invalid JSON value type.']);
            }
        }
    }

    private function snapshot(int $accountId): array
    {
        $row = DB::table('mobile_preferences')->where('mobile_account_id', $accountId)->first();
        $prefs = $row ? json_decode($row->preferences, true, 32, JSON_THROW_ON_ERROR) : [];
        foreach (self::MAPS as $key) {
            if (! isset($prefs[$key])) {
                continue;
            }
            if ($key === 'additionalReminders') {
                foreach ($prefs[$key] as &$value) {
                    $value = (object) $value;
                }
                unset($value);
            }
            if ($key === 'prayerNotificationSettings') {
                foreach ($prefs[$key] as &$phase) {
                    foreach ($phase as &$setting) {
                        $setting = (object) $setting;
                    }
                    unset($setting);
                    $phase = (object) $phase;
                }
                unset($phase);
            }
            $prefs[$key] = (object) $prefs[$key];
        }

        return ['version' => $row ? (int) $row->version : 0, 'preferences' => (object) $prefs,
            'updated_at' => $row ? \Illuminate\Support\Carbon::parse($row->updated_at)->toIso8601String() : null];
    }
}
