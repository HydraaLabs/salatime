<?php

namespace App\Http\Controllers\Quran\API\Juze;

use App\Http\Controllers\Controller;
use App\Models\Quran\Chapter\ChapterDetail;
use App\Services\Quran\TranslatorLanguageResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JuzesController extends Controller
{
    public function __construct(private readonly TranslatorLanguageResolver $translatorLanguageResolver) {}

    public function index(Request $request)
    {
        $translatorId = $request->query('translator_id');
        $languageCode = $this->translatorLanguageResolver->resolve($request, $translatorId);

        $chapterDetails = ChapterDetail::query()
            ->whereHas('translators', fn ($query) => $query->where('translator_id', $translatorId))
            ->select('chapter_id', 'juz_number', DB::raw('MIN(verse_number) as min_verse_number'), DB::raw('MAX(verse_number) as max_verse_number'))
            ->with(['chapter' => function ($query) use ($translatorId) {
                $query->select('id', 'arabic_name')
                    ->withWhereHas('translations', fn ($query) => $query->where('translator_id', $translatorId)->select('id', 'chapter_id', 'translate_name'));
            }])
            ->groupBy('chapter_id', 'juz_number')
            ->get();

        $juzList = collect($chapterDetails)->groupBy('juz_number')->map(function ($chapter) use ($languageCode) {
            $juzNumber = $chapter->first()->juz_number;

            return [
                'juz_number' => translateToLanguage($juzNumber, $languageCode),
                'juz_translate_name' => translateToLanguage('juz', $languageCode),
                'chapter_list' => $chapter->map(function ($chapter) use ($languageCode) {
                    return [
                        'chapter_id' => $chapter->chapter_id,
                        'serial_number' => translateToLanguage($chapter->chapter_id, $languageCode),
                        'arabic_name' => $chapter->chapter ? $chapter->chapter->arabic_name : '',
                        'translated_name' => $chapter->chapter && $chapter->chapter->translations ? $chapter->chapter->translations->translate_name : '',
                        'verses_translate_name' => translateToLanguage('verses', $languageCode),
                        'verse_number' => translateToLanguage($chapter->min_verse_number, $languageCode).'-'.translateToLanguage($chapter->max_verse_number, $languageCode),
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        $juzList = array_slice($juzList, 0, 30); // Take the first 30 juz

        return response()->json([
            'status' => true,
            'message' => 'Data fetched successfully',
            'data' => $juzList,
        ]);
    }
}
