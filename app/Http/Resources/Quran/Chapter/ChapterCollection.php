<?php

namespace App\Http\Resources\Quran\Chapter;

use App\Services\Quran\TranslatorLanguageResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChapterCollection extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $languageCode = $request->attributes->get(TranslatorLanguageResolver::LANGUAGE_ATTRIBUTE);

        return [
            'id' => $this->id,
            'serial_number' => translateToLanguage($this->id, $languageCode),
            'arabic_name' => $this->arabic_name,
            'translate_name' => $this->translateChapters ? $this->translateChapters->translate_name : null,
            'verses_translate_name' => translateToLanguage('verses', $languageCode),
            'verses_count' => translateToLanguage($this->verses_count, $languageCode),
        ];
    }
}
