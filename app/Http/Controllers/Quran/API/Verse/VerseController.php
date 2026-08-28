<?php

namespace App\Http\Controllers\Quran\API\Verse;

use App\Http\Controllers\Controller;
use App\Models\Quran\Chapter\Chapter;
use App\Models\Quran\Chapter\ChapterDetail;
use App\Services\Quran\TranslatorLanguageResolver;
use Illuminate\Http\Request;

class VerseController extends Controller
{
    public function __construct(private readonly TranslatorLanguageResolver $translatorLanguageResolver) {}

    public function index(Request $request, Chapter $chapter)
    {
        $translatorId = $request->query('translator_id');

        try {
            $languageCode = $this->translatorLanguageResolver->resolve($request, $translatorId);

            $chapterData = $this->getChapterInfo($chapter, $translatorId);

            if (! $chapterData) {
                return response()->json([
                    'status' => true,
                    'message' => 'Data fetched successfully',
                ]);
            }

            $chapterInfo = $this->getChapterDetails($chapter, $translatorId);

            $output = [
                'chapter' => $this->formatChapterInfo($chapterData, $languageCode),
                'chapter_info' => $this->formatChapterDetails($chapterInfo, $languageCode),
            ];

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully',
                'data' => $output,
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    private function getChapterInfo(Chapter $chapter, $translatorId): ?object
    {
        return Chapter::query()
            ->withWhereHas('translateChapters', fn ($builder) => $builder->where([
                ['translator_id', $translatorId],
                ['chapter_id', $chapter->id],
            ]))
            ->first();
    }

    private function getChapterDetails(Chapter $chapter, $translatorId): \Illuminate\Database\Eloquent\Collection|array
    {
        return ChapterDetail::query()
            ->withWhereHas('translators', fn ($builder) => $builder->where('translator_id', $translatorId))
            ->where('chapter_id', $chapter->id)
            ->get()
            ->groupBy('page_number');
    }

    private function formatChapterInfo($chapter, ?string $languageCode): array
    {
        return [
            'id' => $chapter->id,
            'serial_number' => translateToLanguage($chapter->id, $languageCode),
            'arabic_name' => $chapter->arabic_name,
            'translated_name' => $chapter->translateChapters ? $chapter->translateChapters->translate_name : '',
            'verses_translate_name' => translateToLanguage('verses', $languageCode),
            'verses_count' => translateToLanguage($chapter->verses_count, $languageCode),
        ];
    }

    private function formatChapterDetails($chapterDetails, ?string $languageCode): array
    {
        $chapterInfo = [];
        $number = 0;
        foreach ($chapterDetails as $pageNumber => $pageDetails) {
            $pageVerses = [];
            $concatenatedAyah = '';
            foreach ($pageDetails as $detail) {
                $translatedName = $detail->translators && isset($detail->translators[0]) ? $detail->translators[0]->translate_name : '';
                $concatenatedAyah .= $detail['arabic_name'].' ('.translateToLanguage($detail['verse_number'], 'ar').')'.'  ';
                $pageVerses[] = [
                    'id' => $detail->id,
                    'chapter_id' => $detail->chapter_id,
                    'verses_translate_name' => translateToLanguage('verses', $languageCode),
                    // 'verses_number' => translateToLanguage($detail->verse_number, $languageCode),
                    'verses_number' => strval($detail->verse_number),
                    'arabic_name' => $detail->arabic_name,
                    'translated_name' => $translatedName,
                    'english_transliteration' => $detail->english_transliteration,
                ];
            }

            $chapterInfo[] = [
                'eng_page_number' => $pageNumber,
                'page_key' => $number++, // Add index key
                'page_number' => translateToLanguage($pageNumber, $languageCode),
                'page_verses' => $pageVerses,
                'page_arabic_ayah' => trim($concatenatedAyah),
            ];
        }

        return $chapterInfo;
    }
}
