<?php

namespace App\Services;

use App\Models\PromotedPlace;
use Illuminate\Support\Collection;

class PromotionService
{
    public function __construct(
        private GooglePlacesService $placesService
    ) {}

    /**
     * Given the user's intent, pick the most relevant active promoted places.
     *
     * Scoring uses weighted token overlap between FoodSearch and the promoted
     * place's (name ∪ food_types ∪ description). Field weights:
     *   food_types   → 2.0  (admin-curated, highest signal)
     *   name         → 1.5
     *   description  → 0.5
     *
     * Budget matching adds +1.0 when the place is within budget.
     *
     * When FoodSearch is empty, all active places are eligible and ranked
     * by budget match only.
     *
     * Returns a collection of up to 3 top-scoring places (shuffled within
     * tied scores to rotate among equally relevant promotions).
     */
    public function pick(array $intent): Collection
    {
        $active = PromotedPlace::active()->get();

        if ($active->isEmpty()) {
            return collect();
        }

        $foodSearch = trim($intent['FoodSearch'] ?? '');
        $maxBudget  = (int) ($intent['MaxBudget'] ?? 0);

        $queryTokens = $this->placesService->tokenize($foodSearch);

        $scored = $active->map(function (PromotedPlace $place) use ($queryTokens, $maxBudget) {
            $score = 0.0;

            // Food relevance via token overlap
            if (empty($queryTokens)) {
                // Empty query → all places equally eligible
                $score += 1.0;
            } else {
                $relevance = $this->computePromotedRelevance($queryTokens, $place);
                $score    += $relevance * 2.0; // scale to make food match dominant
            }

            // Budget match
            if ($maxBudget === 0 || $place->min_price <= $maxBudget) {
                $score += 1.0;
            }

            return [
                'place' => $place,
                'score' => $score,
            ];
        });

        // When FoodSearch is specific, disqualify places with zero food relevance
        $eligible = $scored->filter(function ($s) use ($queryTokens) {
            if (!empty($queryTokens) && $s['score'] <= 1.0) {
                // score <= 1.0 means only budget matched, food didn't — disqualify
                return false;
            }
            return $s['score'] > 0;
        });

        if ($eligible->isEmpty()) {
            return collect();
        }

        $maxScore = $eligible->max('score');

        return $eligible
            ->filter(fn($s) => $s['score'] === $maxScore)
            ->map(fn($s) => $s['place'])
            ->shuffle()
            ->take(3)
            ->values();
    }

    /**
     * Compute token overlap relevance between query and a PromotedPlace.
     *
     * Uses higher field weights than the Google candidates path because
     * admin-curated food_types are richer and more reliable than Google types.
     *
     * @param  array         $queryTokens  Pre-tokenized query
     * @param  PromotedPlace $place
     * @return float                       Score in [0.0, 1.0]
     */
    private function computePromotedRelevance(array $queryTokens, PromotedPlace $place): float
    {
        $fieldWeights = [
            'food_types'  => 2.0,  // Admin-curated tags — highest confidence
            'name'        => 1.5,  // Place name
            'description' => 0.5,  // Description — supplementary signal
        ];

        $fieldTokens = [
            'food_types'  => $this->placesService->tokenize(
                implode(' ', $place->food_types ?? [])
            ),
            'name'        => $this->placesService->tokenize($place->name ?? ''),
            'description' => $this->placesService->tokenize($place->description ?? ''),
        ];

        $totalWeight = 0.0;

        foreach ($queryTokens as $token) {
            $bestWeight = 0.0;
            foreach ($fieldWeights as $field => $weight) {
                if (in_array($token, $fieldTokens[$field], true)) {
                    $bestWeight = max($bestWeight, $weight);
                }
            }
            $totalWeight += $bestWeight;
        }

        return min(1.0, $totalWeight / count($queryTokens));
    }
}