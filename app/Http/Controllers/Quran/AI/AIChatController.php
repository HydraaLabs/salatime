<?php

namespace App\Http\Controllers\Quran\AI;

use App\Http\Controllers\Controller;
use App\Services\Setting\SettingService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AIChatController extends Controller
{
    public function __construct(SettingService $service)
    {
        $this->service = $service;
    }

    private function aiChat(array $messages, int $maxTokens = 600): string
    {
        $settings = $this->service->getCachedFormattedSettings();

        $apiKey = env('ONEMIN_API_KEY')
            ?: $this->safeDecrypt($settings['islamic_name_api_key']);

        // 1min.ai takes a single prompt string: merge system + user messages.
        $prompt = collect($messages)
            ->map(fn ($m) => strtoupper($m['role']).': '.$m['content'])
            ->implode("\n\n");

        $response = Http::timeout(60)->withHeaders([
            'API-KEY' => $apiKey,
        ])->post('https://api.1min.ai/api/chat-with-ai', [
            'type' => 'UNIFY_CHAT_WITH_AI',
            'model' => 'gpt-4o-mini',
            'promptObject' => [
                'prompt' => $prompt,
                'isMixed' => false,
                'webSearch' => false,
            ],
        ]);

        if (! $response->successful()) {
            throw new \Exception('1min.ai API error: '.$response->status());
        }

        $result = $response->json('aiRecord.aiRecordDetail.resultObject');
        if (is_array($result)) {
            return implode("\n", array_filter($result));
        }

        return is_string($result) ? $result : '';
    }

    public function chat(Request $request)
    {
        $request->validate(['message' => 'required|string|max:500']);

        try {
            $reply = $this->aiChat([
                [
                    'role' => 'system',
                    'content' => 'You are SalaTime AI, a knowledgeable Islamic scholar assistant. Answer questions about Islam, Quran, Hadith, prayer, fiqh, Islamic history, halal/haram, duas, and Islamic lifestyle. Be respectful, accurate, and cite Quranic verses or hadith references when relevant. Include Arabic text for prayers and duas with English translation. Keep answers helpful, clear, and concise. If asked about unrelated topics, politely redirect to Islamic subjects.',
                ],
                ['role' => 'user', 'content' => $request->message],
            ], 600);

            return response()->json(['success' => true, 'reply' => $reply]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'AI service temporarily unavailable. Please try again.'], 503);
        }
    }

    public function generateNames(Request $request)
    {
        $request->validate([
            'gender' => 'required|in:boy,girl,any',
            'origin' => 'nullable|string|max:50',
            'theme' => 'nullable|string|max:100',
            'starts_with' => 'nullable|string|max:3',
            'count' => 'nullable|integer|min:1|max:30',
        ]);

        $gender = $request->gender;
        $origin = trim($request->origin ?? '');
        $theme = trim($request->theme ?? '');
        $startsWith = trim($request->starts_with ?? '');
        $count = $request->integer('count') ?: 15;

        $filters = [];
        if ($gender !== 'any') {
            $filters[] = "Gender: {$gender} names only";
        }
        if ($origin !== '' && strtolower($origin) !== 'any') {
            $filters[] = "Origin: {$origin}";
        }
        if ($theme !== '') {
            $filters[] = "Meaning must relate to: \"{$theme}\"";
        }
        if ($startsWith !== '') {
            $filters[] = 'English transliteration must start with: "'.strtoupper($startsWith).'"';
        }
        $filterText = empty($filters)
            ? 'Provide a beautiful and diverse variety of names.'
            : implode("\n", $filters);

        $prompt = "Generate exactly {$count} Islamic baby names as a valid JSON array only.\n\n{$filterText}\n\n"
            .'Each object must have these exact fields:'
            ."\n- arabic (string)\n- english (string)\n- meaning (string)\n- origin (string)\n- gender (string: \"boy\" or \"girl\")\n- quranicReference (string or null)"
            ."\n\nReturn only the JSON array, nothing else.";

        try {
            $content = $this->aiChat([
                ['role' => 'system', 'content' => 'You are an Islamic names expert. Always respond with a valid JSON array only — no markdown fences, no explanation, no other text.'],
                ['role' => 'user', 'content' => $prompt],
            ], 1500);

            preg_match('/\[[\s\S]*\]/u', $content, $m);
            $names = json_decode($m[0] ?? '[]', true) ?? [];

            return response()->json(['success' => true, 'names' => $names]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'AI service temporarily unavailable. Please try again.'], 503);
        }
    }

    protected function safeDecrypt($value)
    {
        try {
            return decrypt($value);
        } catch (DecryptException $e) {
            return $value;
        }
    }
}
