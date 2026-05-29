<?php

namespace App\Services;

use App\Models\PromotedPlace;
use Illuminate\Support\Collection;

class PromotionService
{
    /**
     * Given the user's intent, pick the most relevant active promoted places.
     *
     * Scoring:
     * +2.0  exact food type match
     * +1.0  partial food type match (e.g. user wants "japanese", place has "sushi")
     * +1.0  budget match (place min_price <= user's MaxBudget, or no budget set)
     *
     * Returns a collection of up to 3 tied highest-scoring places.
     * Enforces strict matching: if a specific food type is requested, 
     * places that do not match the food type are disqualified.
     */
    public function pick(array $intent): Collection
    {
        $active = PromotedPlace::active()->get();

        if ($active->isEmpty()) {
            return collect(); // Return empty collection instead of null
        }

        $foodType  = strtolower($intent['FoodType'] ?? 'any');
        $maxBudget = (int) ($intent['MaxBudget'] ?? 0);

        $scored = $active->map(function (PromotedPlace $place) use ($foodType, $maxBudget) {
            $score       = 0.0;
            $placeTypes  = array_map('strtolower', $place->food_types);
            $foodMatched = false;

            // Food type scoring
            if ($foodType === 'any') {
                $score += 1.0; // neutral — all places equally relevant
                $foodMatched = true;
            } elseif (in_array($foodType, $placeTypes)) {
                $score += 2.0; // exact match
                $foodMatched = true;
            } else {
                // Partial match: e.g. user wants "japanese", place has "sushi" or "ramen"
                $relatedTypes = $this->relatedFoodTypes($foodType);
                foreach ($placeTypes as $pt) {
                    if (in_array($pt, $relatedTypes)) {
                        $score += 1.0;
                        $foodMatched = true;
                        break;
                    }
                }
            }

            // Budget scoring
            if ($maxBudget === 0 || $place->min_price <= $maxBudget) {
                $score += 1.0;
            }

            return [
                'place'        => $place,
                'score'        => $score,
                'food_matched' => $foodMatched
            ];
        });

        // Strict Filtering: Only consider places with a score > 0 AND that matched the food intent
        $eligible = $scored->filter(function ($s) use ($foodType) {
            if ($foodType !== 'any' && !$s['food_matched']) {
                return false; // Disqualify if it didn't match the specific food requested
            }
            return $s['score'] > 0;
        });

        if ($eligible->isEmpty()) {
            return collect();
        }

        // Find the highest score
        $maxScore = $eligible->max('score');

        // Among all places tied at the highest score, grab up to 3 to rotate
        $topTier = $eligible->filter(fn($s) => $s['score'] === $maxScore)
                            ->map(fn($s) => $s['place'])
                            ->shuffle() // randomize for promoted place more than 3
                            ->take(3)
                            ->values();

        return $topTier;
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