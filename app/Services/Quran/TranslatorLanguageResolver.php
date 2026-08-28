<?php

namespace App\Services\Quran;

use App\Models\Quran\Translator\Translator;
use Illuminate\Http\Request;

class TranslatorLanguageResolver
{
    public const LANGUAGE_ATTRIBUTE = 'quran.translator_language_code';

    private const TRANSLATOR_ATTRIBUTE = 'quran.translator_id';

    public function resolve(Request $request, mixed $translatorId): ?string
    {
        $normalizedId = $translatorId === null ? null : (string) $translatorId;

        if (
            $request->attributes->has(self::LANGUAGE_ATTRIBUTE)
            && $request->attributes->get(self::TRANSLATOR_ATTRIBUTE) === $normalizedId
        ) {
            return $request->attributes->get(self::LANGUAGE_ATTRIBUTE);
        }

        $languageCode = $normalizedId === null || $normalizedId === ''
            ? null
            : Translator::query()->whereKey($normalizedId)->value('language_code');

        $request->attributes->set(self::TRANSLATOR_ATTRIBUTE, $normalizedId);
        $request->attributes->set(self::LANGUAGE_ATTRIBUTE, $languageCode);

        return $languageCode;
    }
}
