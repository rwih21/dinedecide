<?php

namespace App\Services;

class SawService
{
    // Criteria weights — must sum to 1.0
    private array $weights = [
        'distance'    => 0.35, // C1 — Cost
        'food_match'  => 0.30, // C2 — Benefit
        'rating'      => 0.20, // C3 — Benefit
        'price_level' => 0.15, // C4 — Cost
    ];
    // Radius of indifference for C1 (meters)
    private float $indifferenceRadius = 1000.0;

    /**
     * Main entry point.
     * $candidates = array of restaurants, each must have:
     *   distance (float, meters), food_match (0|1),
     *   rating (float, 0-5), price_level (int, 1-4)
     *
     * Returns the same array with saw_score and criteria_breakdown added,
     * sorted descending by saw_score.
     */
    public function rank(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        // Step 1 — Filter by food match (C2) first
        $filtered = $this->filterByFoodMatch($candidates);

        if (empty($filtered)) {
            return [];
        }
        
        $filtered = $this->applyBayesianRating($filtered); // apply Bayesian rating adjustment before normalize

        // Step 2 — Normalize each criterion
        $normalized = $this->normalize($filtered);

        // Step 3 — Calculate preference value Vi for each
        $scored = $this->calculateScores($normalized);

        // Step 4 — Sort descending by saw_score
        usort($scored, fn($a, $b) => $b['saw_score'] <=> $a['saw_score']);

        // Step 5 — Assign rank
        foreach ($scored as $i => &$restaurant) {
            $restaurant['rank'] = $i + 1;
        }

        return $scored;
    }

    // --- PRIVATE METHODS ---

    private function filterByFoodMatch(array $candidates): array
    {
        return array_values(
            array_filter($candidates, fn($r) => $r['food_match'] === 1)
        );
    }

    /**
     * Apply Bayesian average to smooth ratings based on review count.
     * Prevents low-review restaurants from dominating on rating alone.
     *
     * Formula: (n × r + m × C) / (n + m)
     *   n = review count
     *   r = raw rating
     *   m = confidence threshold (min reviews before we trust fully)
     *   C = global mean rating across all candidates
     */
    private function applyBayesianRating(array $candidates): array
    {
        $m = 200;
        $C = 4.0; // fixed global mean — never overwritten

        foreach ($candidates as &$r) {
            $n = $r['review_count'] ?? 0;
            $rawRating = $r['rating'];
            $r['adjusted_rating'] = round(($n * $rawRating + $m * $C) / ($n + $m), 4);
        }

        return $candidates;
    }
    private function normalize(array $candidates): array
    {
        $distances   = array_column($candidates, 'distance');
        $ratings     = array_column($candidates, 'adjusted_rating');
        $priceLevels = array_column($candidates, 'price_level');

        $minDistance  = min($distances);
        $maxRating    = max($ratings);
        $minPrice     = min($priceLevels);

        foreach ($candidates as &$r) {
            // C1: Distance — Cost
            if ($r['distance'] <= $this->indifferenceRadius) {
                $r['r_distance'] = 1.0;
            } else {
                $r['r_distance'] = $minDistance / $r['distance'];
            }

            // C2: Food match — Benefit
            $r['r_food_match'] = (float) $r['food_match'];

            // C3: Rating — Benefit (Bayesian adjusted)
            $r['r_rating'] = $maxRating > 0
                ? $r['adjusted_rating'] / $maxRating
                : 0.0;

            // C4: Price level — Cost
            $r['r_price_level'] = $r['price_level'] > 0
                ? $minPrice / $r['price_level']
                : 0.0;
        }

        return $candidates;
    }

    private function calculateScores(array $candidates): array
    {
        foreach ($candidates as &$r) {
            $vi =
                ($this->weights['distance']    * $r['r_distance'])    +
                ($this->weights['food_match']  * $r['r_food_match'])  +
                ($this->weights['rating']      * $r['r_rating'])      +
                ($this->weights['price_level'] * $r['r_price_level']);

            $r['saw_score'] = round($vi, 6);

            $r['criteria_breakdown'] = [
                'C1_distance'     => round($r['r_distance'],    4),
                'C2_food_match'   => round($r['r_food_match'],  4),
                'C3_rating'       => round($r['r_rating'],      4),
                'C4_price_level'  => round($r['r_price_level'], 4),
                'raw_rating'      => $r['rating'],
                'adjusted_rating' => $r['adjusted_rating'],
                'review_count'    => $r['review_count'] ?? 0,
                'weights'         => $this->weights,
            ];
        }

        return $candidates;
    }
}