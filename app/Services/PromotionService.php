<?php

namespace App\Services;

use App\Models\PromotedPlace;

class PromotionService
{
    /**
     * Given the user's intent, pick the most relevant active promoted place.
     *
     * Scoring:
     *   +2.0  exact food type match
     *   +1.0  partial food type match (e.g. user wants "japanese", place has "sushi")
     *   +1.0  budget match (place min_price <= user's MaxBudget, or no budget set)
     *
     * Returns the highest-scoring place, or null if nothing is active.
     * If multiple places tie, one is chosen randomly among them.
     */
    public function pick(array $intent): ?PromotedPlace
    {
        $active = PromotedPlace::active()->get();

        if ($active->isEmpty()) {
            return null;
        }

        $foodType  = strtolower($intent['FoodType'] ?? 'any');
        $maxBudget = (int) ($intent['MaxBudget'] ?? 0);

        $scored = $active->map(function (PromotedPlace $place) use ($foodType, $maxBudget) {
            $score      = 0.0;
            $placeTypes = array_map('strtolower', $place->food_types);

            // Food type scoring
            if ($foodType === 'any') {
                $score += 1.0; // neutral — all places equally relevant
            } elseif (in_array($foodType, $placeTypes)) {
                $score += 2.0; // exact match
            } else {
                // Partial match: e.g. user wants "japanese", place has "sushi" or "ramen"
                $relatedTypes = $this->relatedFoodTypes($foodType);
                foreach ($placeTypes as $pt) {
                    if (in_array($pt, $relatedTypes)) {
                        $score += 1.0;
                        break;
                    }
                }
            }

            // Budget scoring
            if ($maxBudget === 0 || $place->min_price <= $maxBudget) {
                $score += 1.0;
            }

            return ['place' => $place, 'score' => $score];
        });

        // Only consider places with a score > 0
        $eligible = $scored->filter(fn($s) => $s['score'] > 0);

        if ($eligible->isEmpty()) {
            return null;
        }

        // Find the highest score
        $maxScore = $eligible->max('score');

        // Among all places tied at the highest score, pick one randomly
        $topTier = $eligible->filter(fn($s) => $s['score'] === $maxScore)->values();

        return $topTier->random()['place'];
    }

    /**
     * Maps a food type to its "family" — used for partial matching.
     */
    private function relatedFoodTypes(string $foodType): array
    {
        $families = [
            'japanese' => ['sushi', 'ramen', 'japanese'],
            'sushi'    => ['sushi', 'japanese'],
            'ramen'    => ['ramen', 'japanese'],
            'chicken'  => ['chicken', 'indonesian'],
            'burger'   => ['burger', 'fastfood'],
            'pizza'    => ['pizza', 'fastfood'],
            'coffee'   => ['coffee'],
        ];

        return $families[$foodType] ?? [];
    }
}