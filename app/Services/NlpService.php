<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class NlpService
{
    private Client $client;
    private string $model = 'qwen2.5:3b';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => env('OLLAMA_BASE_URL', 'http://127.0.0.1:11434/v1/'),
            'timeout'  => 60.0,
            'headers'  => [
                'Authorization' => 'Bearer ollama',
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function extractIntent(string $rawQuery): array
    {
        $cacheKey = 'nlp_intent_' . md5(strtolower(trim($rawQuery)));

        if ($cached = cache()->get($cacheKey)) {
            Log::info('NLP cache hit for query: ' . $rawQuery);
            return $cached;
        }

        try {
            $response = $this->client->post('chat/completions', [
                'json' => [
                    'model'       => $this->model,
                    'messages'    => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user',   'content' => "Extract intent from this query: \"{$rawQuery}\""],
                    ],
                    'temperature' => 0.1,
                ],
            ]);

            $body    = json_decode($response->getBody()->getContents(), true);
            $content = $body['choices'][0]['message']['content'] ?? '';

            $result = $this->parseResponse($content);
            Log::info('NLP intent extracted', $result);

            // Only cache if Ollama returned something meaningful
            if ($result['FoodSearch'] !== '' || $result['MaxPrice'] !== 0) {
                cache()->put($cacheKey, $result, now()->addHours(24));
            }

            return $result;

        } catch (GuzzleException $e) {
            Log::error('NlpService API error: ' . $e->getMessage());
            return $this->fallbackIntent($rawQuery);
        }
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are a structured intent extractor for a restaurant recommendation app in Indonesia.
Extract the user's intent and return ONLY a valid JSON object — no markdown, no explanation, no extra text.

FIELD RULES:

"FoodSearch": The exact food, dish, drink, or cuisine the user wants to find.
  - Preserve the user's words as closely as possible. Do NOT translate or generalize.
  - "chicken katsu" → "chicken katsu"
  - "ayam geprek" → "ayam geprek"
  - "ayam penyet pedas" → "ayam penyet"
  - "kopi susu" → "kopi susu"
  - "ramen hangat" → "ramen"
  - "I want something to eat" → "" (empty string — no food specified)
  - If nothing food-related is mentioned, return an empty string "".

"FoodType": A simplified category for internal scoring. Must be one of:
  any, indonesian, chicken, ramen, sushi, japanese, burger, pizza, coffee, korean, seafood, chinese, steak, thai, fastfood
  - "ayam geprek" → "chicken"
  - "ayam penyet" → "chicken"
  - "chicken katsu" → "chicken"
  - "tonkatsu" → "japanese"
  - "kopi susu" → "coffee"
  - "nasi padang" → "indonesian"
  - "pad thai" → "thai"
  - "bibimbap" → "korean"
  - If unsure, pick the closest category. Only use "any" if truly nothing food-related is mentioned.

"MaxPrice": Maximum budget as an exact integer in IDR.
  - "under 50k" / "50rb" → 50000
  - "25k to 60k" → 60000 (use the higher number)
  - "cheap" / "budget" / "murah" → 30000
  - "expensive" / "fine dining" → 300000
  - not mentioned → 0

"MaxDistance": Search radius in meters.
  - "nearby" / "dekat" / "near" → 1000
  - "walking distance" → 500
  - "2km" → 2000
  - not mentioned → 3000

"Occasion": Social context. Must be one of: family, romantic, formal, casual, any
"VisitTime": When they plan to visit. Must be one of: now, morning, lunch, afternoon, evening, night

EXAMPLES:

Input: "I want chicken katsu under 50k"
Output: {"FoodSearch": "chicken katsu", "FoodType": "chicken", "MaxPrice": 50000, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}

Input: "ayam geprek deket sini"
Output: {"FoodSearch": "ayam geprek", "FoodType": "chicken", "MaxPrice": 0, "MaxDistance": 1000, "Occasion": "any", "VisitTime": "now"}

Input: "pengen ramen hangat malem ini"
Output: {"FoodSearch": "ramen", "FoodType": "ramen", "MaxPrice": 0, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "night"}

Input: "cari nasi padang murah sekitar sini"
Output: {"FoodSearch": "nasi padang", "FoodType": "indonesian", "MaxPrice": 30000, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}

Input: "aku mau minum kopi susu deket sini"
Output: {"FoodSearch": "kopi susu", "FoodType": "coffee", "MaxPrice": 0, "MaxDistance": 1000, "Occasion": "any", "VisitTime": "now"}

Input: "dinner romantis buat anniversary"
Output: {"FoodSearch": "", "FoodType": "any", "MaxPrice": 0, "MaxDistance": 3000, "Occasion": "romantic", "VisitTime": "evening"}

Return exactly this shape:
{"FoodSearch": "", "FoodType": "any", "MaxPrice": 0, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}
PROMPT;
    }

    private function parseResponse(string $content): array
    {
        $clean = preg_replace('/```json|```/', '', $content);
        $clean = preg_replace('/<think>.*?<\/think>/s', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['FoodType'])) {
            Log::warning('NlpService bad parse: ' . $content);
            return $this->fallbackIntent();
        }

        $validFoodTypes = ['any','indonesian','chicken','ramen','sushi','japanese','burger','pizza','coffee','korean','seafood','chinese','steak','thai','fastfood'];
        $validOccasions = ['family','romantic','formal','casual','any'];
        $validTimes     = ['now','morning','lunch','afternoon','evening','night'];

        $foodSearch = trim($data['FoodSearch'] ?? '');
        $foodType   = strtolower(trim($data['FoodType']  ?? 'any'));
        $occasion   = strtolower(trim($data['Occasion']  ?? 'any'));
        $visitTime  = strtolower(trim($data['VisitTime'] ?? 'now'));

        if (!in_array($foodType, $validFoodTypes))  $foodType  = 'any';
        if (!in_array($occasion, $validOccasions))  $occasion  = 'any';
        if (!in_array($visitTime, $validTimes))     $visitTime = 'now';

        return [
            'FoodSearch'  => $foodSearch,   // passed directly to Google textQuery
            'FoodType'    => $foodType,      // used for SAW food_match scoring only
            'MaxPrice'    => (int)   ($data['MaxPrice']    ?? 0),
            'MaxDistance' => (float) ($data['MaxDistance'] ?? 3000),
            'Occasion'    => $occasion,
            'VisitTime'   => $visitTime,
        ];
    }

    private function fallbackIntent(string $rawQuery = ''): array
    {
        return [
            'FoodSearch'  => $rawQuery, // fall back to full raw query so Google still gets something
            'FoodType'    => 'any',
            'MaxPrice'    => 0,
            'MaxDistance' => 3000.0,
            'Occasion'    => 'any',
            'VisitTime'   => 'now',
        ];
    }
}