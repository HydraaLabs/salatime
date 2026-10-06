<?php

namespace App\Services\Mobile;

use App\Models\Mobile\MobileAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\AcceptHeader;

class AccountLocale
{
    public const SUPPORTED = ['en', 'fr', 'ar', 'tr', 'ur', 'id', 'ms', 'es', 'bn', 'fa'];

    public static function supported(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = strtolower(trim($value));
        if (! preg_match('/^[a-z]{2,3}(?:[-_][a-z0-9]{2,8})*$/', $value)) {
            return null;
        }
        $language = preg_split('/[-_]/', $value)[0];

        return in_array($language, self::SUPPORTED, true) ? $language : null;
    }

    public static function normalize(mixed $value): string
    {
        return self::supported($value) ?? 'en';
    }

    public static function forAccount(MobileAccount $account): string
    {
        if ($account->locale !== null) {
            return self::normalize($account->locale);
        }
        if ($account->exists) {
            $raw = DB::table('mobile_preferences')->where('mobile_account_id', $account->getKey())->value('preferences');
            $preferences = is_string($raw) ? json_decode($raw, true, 32) : null;

            return self::normalize(is_array($preferences) ? ($preferences['language'] ?? null) : null);
        }

        return 'en';
    }

    public static function forRegistration(Request $request): string
    {
        if ($request->exists('locale')) {
            return self::normalize($request->input('locale'));
        }
        // Older clients only identify their language through this header.
        foreach (AcceptHeader::fromString($request->header('Accept-Language'))->all() as $language) {
            if ($language->getQuality() > 0 && ($supported = self::supported($language->getValue())) !== null) {
                return $supported;
            }
        }

        return 'en';
    }
}
