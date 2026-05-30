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

        IMPORTANT: Each field has strict rules.

        "FoodType": Extract the specific food, dish, or cuisine the user is asking for. Return it exactly as they typed it. 
            - Example: "warm soup" -> "warm soup"
            - Example: "spicy noodles" -> "spicy noodles"
            - "family dinner" / "date night" is NOT a food type, return "any"
            - If no specific food is mentioned, return "any".

        "MaxPrice": Exact integer in IDR based on the user's maximum budget.
            - "under 50k" or "50rb" = 50000
            - "25k to 60k" = 60000 (extract the MAXIMUM they are willing to spend)
            - "cheap" / "budget" = 30000
            - "expensive" / "fine dining" = 300000
            - not mentioned = 0

        "MaxDistance": in meters:
            - nearby / dekat / near         = 1000
            - walking distance              = 500
            - a number in km                = that number * 1000
            - not mentioned                 = 3000

        "Occasion": The SOCIAL CONTEXT or PURPOSE of the meal. Must be one of:
            family, romantic, formal, casual, any

        "VisitTime": WHEN they plan to visit. Must be one of:
            now, morning, lunch, afternoon, evening, night

        Example input: "I want warm soup and I am on a budget. Around 25k to 50k is ok"
        Example output: {"FoodType": "warm soup", "MaxPrice": 50000, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}

        Return exactly this shape:
        {"FoodType": "any", "MaxPrice": 0, "MaxDistance": 3000, "Occasion": "any", "VisitTime": "now"}
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

        // Removed the $validFoodTypes array entirely!
        $validOccasions = ['family','romantic','formal','casual','any'];
        $validTimes     = ['now','morning','lunch','afternoon','evening','night'];

        // Let the LLM string pass through untouched (just trimmed and lowercased)
        $foodType = strtolower(trim($data['FoodType'] ?? 'any'));
        $occasion = strtolower(trim($data['Occasion']  ?? 'any'));
        $visitTime = strtolower(trim($data['VisitTime'] ?? 'now'));

        // Only enforce rigid validation on Occasion and VisitTime
        if (!in_array($occasion, $validOccasions)) $occasion = 'any';
        if (!in_array($visitTime, $validTimes))    $visitTime = 'now';

        return [
            'FoodType'    => $foodType,
            'MaxPrice'    => (int)   ($data['MaxPrice']    ?? 0),
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