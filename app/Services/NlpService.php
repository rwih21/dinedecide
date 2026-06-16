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
  - "dimsum" → "dimsum"
  - "vegetarian" → "vegetarian"
  - "I want something to eat" → "" (empty string — no food specified)
  - If nothing food-related is mentioned, return an empty string "".

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
Output: {"FoodSearch": "chicken katsu", "MaxPrice": 50000, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}

Input: "ayam geprek deket sini"
Output: {"FoodSearch": "ayam geprek", "MaxPrice": 0, "MaxDistance": 1000, "Occasion": "any", "VisitTime": "now"}

Input: "pengen ramen hangat malem ini"
Output: {"FoodSearch": "ramen", "MaxPrice": 0, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "night"}

Input: "cari nasi padang murah sekitar sini"
Output: {"FoodSearch": "nasi padang", "MaxPrice": 30000, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}

Input: "aku mau minum kopi susu deket sini"
Output: {"FoodSearch": "kopi susu", "MaxPrice": 0, "MaxDistance": 1000, "Occasion": "any", "VisitTime": "now"}

Input: "dinner romantis buat anniversary"
Output: {"FoodSearch": "", "MaxPrice": 0, "MaxDistance": 3000, "Occasion": "romantic", "VisitTime": "evening"}

Input: "I want dimsum"
Output: {"FoodSearch": "dimsum", "MaxPrice": 0, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}

Input: "something vegetarian under 80k"
Output: {"FoodSearch": "vegetarian", "MaxPrice": 80000, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}

Return exactly this shape:
{"FoodSearch": "", "MaxPrice": 0, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}
PROMPT;
    }

    private function parseResponse(string $content): array
    {
        $clean = preg_replace('/```json|```/', '', $content);
        $clean = preg_replace('/<think>.*?<\/think>/s', '', $clean);
        $clean = trim($clean);

        $data = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || !array_key_exists('FoodSearch', $data)) {
            Log::warning('NlpService bad parse: ' . $content);
            return $this->fallbackIntent();
        }

        $validOccasions = ['family', 'romantic', 'formal', 'casual', 'any'];
        $validTimes     = ['now', 'morning', 'lunch', 'afternoon', 'evening', 'night'];

        $foodSearch = trim($data['FoodSearch'] ?? '');
        $occasion   = strtolower(trim($data['Occasion']  ?? 'any'));
        $visitTime  = strtolower(trim($data['VisitTime'] ?? 'now'));

        if (!in_array($occasion, $validOccasions))  $occasion  = 'any';
        if (!in_array($visitTime, $validTimes))     $visitTime = 'now';

        return [
            'FoodSearch'  => $foodSearch,
            'MaxPrice'    => (int)   ($data['MaxPrice']    ?? 0),
            'MaxDistance' => (float) ($data['MaxDistance'] ?? 3000),
            'Occasion'    => $occasion,
            'VisitTime'   => $visitTime,
        ];
    }

    private function fallbackIntent(string $rawQuery = ''): array
    {
        return [
            'FoodSearch'  => $rawQuery,
            'MaxPrice'    => 0,
            'MaxDistance' => 3000.0,
            'Occasion'    => 'any',
            'VisitTime'   => 'now',
        ];
    }
}