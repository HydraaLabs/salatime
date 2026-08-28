<?php

namespace App\Http\Controllers\Quran\API\Chapter;

use App\Http\Controllers\Controller;
use App\Http\Resources\Quran\Chapter\ChapterCollection;
use App\Models\Quran\Chapter\Chapter;
use App\Services\Quran\TranslatorLanguageResolver;
use Illuminate\Http\Request;

class ChapterController extends Controller
{
    public function __construct(private readonly TranslatorLanguageResolver $translatorLanguageResolver) {}

    public function index(Request $request)
    {
        try {
            $translatorId = $request->query('translator_id');
            $this->translatorLanguageResolver->resolve($request, $translatorId);

            $chapters = Chapter::query()
                ->select(['id', 'arabic_name', 'verses_count'])
                ->with('translateChapters', function ($query) use ($translatorId) {
                    $query->where('translator_id', $translatorId);
                })
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully',
                'data' => ChapterCollection::collection($chapters),
            ]);

        } catch (\Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}
