<?php

namespace App\Services;

class SawService
{
    // Criteria weights — must sum to 1.0
    // C1: Distance   (Cost criterion)
    // C2: Food Match (Benefit criterion) — now a real lexical relevance score [0,1]
    // C3: Rating     (Benefit criterion) — Bayesian-adjusted
    // C4: Price      (Cost criterion)
    private array $weights = [
        'distance'   => 0.30,
        'food_match' => 0.35,
        'rating'     => 0.20,
        'price'      => 0.15,
    ];

    // Radius of indifference for C1 (meters)
    // Candidates within this distance all receive C1 = 1.0
    private float $indifferenceRadius = 1000.0;

    /**
     * Main entry point.
     *
     * $candidates = array of restaurants, each must have:
     *   distance (float, meters)
     *   food_match (float, 0.0–1.0) — set by GooglePlacesService::applyFoodMatch()
     *   rating (float, 0–5)
     *   review_count (int)
     *   exact_price (float, IDR)
     *
     * Returns the same array with saw_score and criteria_breakdown added,
     * sorted descending by saw_score, with rank assigned.
     */
    public function rank(array $candidates): array
    {
        if (empty($candidates)) {
            return [];
        }

        // Step 1 — Filter: only keep candidates with food_match > 0
        // When FoodSearch is empty, all candidates have food_match = 1.0 (abstain mode),
        // so none are filtered out. When FoodSearch is specific, zero-match candidates
        // are considered irrelevant.
        $filtered = $this->filterByFoodMatch($candidates);

        if (empty($filtered)) {
            return [];
        }

        // Step 2 — Edge case: if every remaining candidate has food_match = 0
        // after filtering (shouldn't happen given Step 1, but defensive check),
        // set all to 1.0 so C2 abstains rather than zeroing all scores.
        $filtered = $this->resolveAllZeroFoodMatch($filtered);

        // Step 3 — Bayesian rating adjustment
        $filtered = $this->applyBayesianRating($filtered);

        // Step 4 — Normalize each criterion
        $normalized = $this->normalize($filtered);

        // Step 5 — Calculate preference value Vi for each candidate
        $scored = $this->calculateScores($normalized);

        // Step 6 — Sort descending by saw_score
        usort($scored, fn($a, $b) => $b['saw_score'] <=> $a['saw_score']);

        // Step 7 — Assign rank
        foreach ($scored as $i => &$restaurant) {
            $restaurant['rank'] = $i + 1;
        }

        return $scored;
    }

    // --- PRIVATE METHODS ---

    private function filterByFoodMatch(array $candidates): array
    {
        return array_values(
            array_filter($candidates, fn($r) => $r['food_match'] > 0)
        );
    }

    /**
     * If all candidates have food_match = 0 (e.g. open-vocabulary query like
     * "vegetarian" where no Google type matched), set everyone to 1.0 so
     * C2 does not flatten the entire ranking. This lets C1, C3, C4 differentiate.
     *
     * This edge case is distinct from the empty-query abstain path (handled in
     * GooglePlacesService::applyFoodMatch) but has the same intended outcome.
     */
    private function resolveAllZeroFoodMatch(array $candidates): array
    {
        $allZero = collect($candidates)->every(fn($r) => $r['food_match'] == 0.0);

        if ($allZero) {
            return array_map(function ($r) {
                $r['food_match'] = 1.0;
                return $r;
            }, $candidates);
        }

        return $candidates;
    }

    private function applyBayesianRating(array $candidates): array
    {
        $m = 200;
        $C = 4.0; // fixed global mean

        foreach ($candidates as &$r) {
            $n = $r['review_count'] ?? 0;
            $rawRating = $r['rating'];
            $r['adjusted_rating'] = round(($n * $rawRating + $m * $C) / ($n + $m), 4);
        }

        return $candidates;
    }

    private function normalize(array $candidates): array
    {
        $distances = array_column($candidates, 'distance');
        $ratings   = array_column($candidates, 'adjusted_rating');
        $prices    = array_column($candidates, 'exact_price');

        $minDistance = min($distances);
        $maxRating   = max($ratings);
        $minPrice    = min($prices);

        foreach ($candidates as &$r) {
            // C1: Distance — Cost criterion
            if ($r['distance'] <= $this->indifferenceRadius) {
                $r['r_distance'] = 1.0;
            } else {
                $r['r_distance'] = $minDistance / $r['distance'];
            }

            // C2: Food Match — Benefit criterion
            // food_match is already in [0,1] from applyFoodMatch(), use directly
            $r['r_food_match'] = (float) $r['food_match'];

            // C3: Rating — Benefit criterion (Bayesian adjusted)
            $r['r_rating'] = $maxRating > 0
                ? $r['adjusted_rating'] / $maxRating
                : 0.0;

            // C4: Price — Cost criterion (exact IDR)
            $r['r_price'] = $r['exact_price'] > 0
                ? $minPrice / $r['exact_price']
                : 0.0;
        }

        return $candidates;
    }

    private function calculateScores(array $candidates): array
    {
        foreach ($candidates as &$r) {
            $vi =
                ($this->weights['distance']   * $r['r_distance'])   +
                ($this->weights['food_match'] * $r['r_food_match']) +
                ($this->weights['rating']     * $r['r_rating'])     +
                ($this->weights['price']      * $r['r_price']);

            $r['saw_score'] = round($vi, 6);

            $r['criteria_breakdown'] = [
                'C1_distance'     => round($r['r_distance'],    4),
                'C2_food_match'   => round($r['r_food_match'],  4),
                'C3_rating'       => round($r['r_rating'],      4),
                'C4_price'        => round($r['r_price'],       4),
                'exact_price'     => $r['exact_price'],
                'raw_rating'      => $r['rating'],
                'adjusted_rating' => $r['adjusted_rating'],
                'review_count'    => $r['review_count'] ?? 0,
                'weights'         => $this->weights,
            ];
        }

        return $candidates;
    }
}