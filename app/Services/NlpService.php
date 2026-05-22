<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class NlpService
{
    private Client $client;
    private string $model = 'qwen2.5-coder:1.5b';

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'http://127.0.0.1:11434/v1/',
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

            return $this->parseResponse($content);

        } catch (GuzzleException $e) {
            Log::error('NlpService API error: ' . $e->getMessage());
            return $this->fallbackIntent();
        }
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
        CRITICAL RULE: "FoodType" must ONLY be a food or cuisine. Words like "family", "romantic", "casual", "date", "dinner", "lunch" are NEVER valid FoodType values — they belong in "Occasion" instead.
        
        You are a structured entity extractor for a restaurant recommendation app.
        Extract the user's intent and return ONLY a valid JSON object — no markdown, no explanation, no extra text.

        IMPORTANT: Each field has a strict allowed value list. Never return a value outside these lists.

        Field rules:

        "FoodType": The TYPE OF FOOD OR CUISINE only. Must be one of:
            ramen, sushi, japanese, indonesian, burger, pizza, chicken, coffee, any
            - "family dinner" is NOT a food type return "any"
            - "date night" is NOT a food type return "any"
            - "tonkotsu" "ramen", "nasi goreng" "indonesian", "ayam" "chicken"
            - If no specific food is mentioned return "any"

        "MaxPrice": Google price level 1-4:
            under 30k / cheap / budget    = 1
            under 50k / affordable        = 2
            under 100k / moderate         = 3
            expensive / fine dining       = 4
            not mentioned                 = 4

        "MaxDistance": in meters:
            nearby / dekat / near         = 1000
            walking distance              = 500
            a number in km                = that number × 1000
            not mentioned                 = 3000

        "Occasion": The SOCIAL CONTEXT or PURPOSE of the meal. Must be one of:
            family, romantic, formal, casual, any
            - "family dinner" / "with kids" / "keluarga"         = family
            - "date night" / "romantic" / "berdua"               = romantic
            - "business lunch" / "meeting" / "formal"            = formal
            - "quick bite" / "hangout" / "santai" / "nongkrong"  = casual
            - not mentioned / unclear                            = any

        "VisitTime": WHEN they plan to visit. Must be one of:
            now, morning, lunch, afternoon, evening, night
            - breakfast / pagi                     = morning
            - lunch / siang / makan siang          = lunch
            - afternoon / sore                     = afternoon
            - dinner / evening / malam / tonight   = evening
            - late night / larut malam             = night
            - "later" / "soon" / not mentioned     = now

        Example input: "I will be having a family dinner later this evening"
        Example output: {"FoodType": "any", "MaxPrice": 4, "MaxDistance": 3000, "Occasion": "family", "VisitTime": "evening"}

        Return exactly this shape:
        {"FoodType": "any", "MaxPrice": 4, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}
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

        // Allowed value lists — sanitize anything outside these
        $validFoodTypes = ['ramen','sushi','japanese','indonesian','burger','pizza','chicken','coffee','any'];
        $validOccasions = ['family','romantic','formal','casual','any'];
        $validTimes     = ['now','morning','lunch','afternoon','evening','night'];

        $foodType = strtolower(trim($data['FoodType'] ?? 'any'));
        $occasion = strtolower(trim($data['Occasion']  ?? 'any'));
        $visitTime = strtolower(trim($data['VisitTime'] ?? 'now'));

        // If model returned an invalid value, fall back to safe default
        if (!in_array($foodType, $validFoodTypes)) $foodType = 'any';
        if (!in_array($occasion, $validOccasions)) $occasion = 'any';
        if (!in_array($visitTime, $validTimes))    $visitTime = 'now';

        return [
            'FoodType'    => $foodType,
            'MaxPrice'    => (int)   ($data['MaxPrice']    ?? 4),
            'MaxDistance' => (float) ($data['MaxDistance'] ?? 3000),
            'Occasion'    => $occasion,
            'VisitTime'   => $visitTime,
        ];
    }

    private function fallbackIntent(): array
    {
        return [
            'FoodType'    => 'any',
            'MaxPrice'    => 4,
            'MaxDistance' => 3000.0,
            'Occasion'    => 'any',
            'VisitTime'   => 'now',
        ];
    }
}