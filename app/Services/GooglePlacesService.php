<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private string $apiKey;

    /**
     * Field weights for token overlap relevance scoring.
     * Higher weight = stronger signal when a query token matches this field.
     */
    private array $fieldWeights = [
        'types'    => 2.0,  // Google place types (e.g. "ramen_restaurant" → "ramen")
        'name'     => 1.5,  // Restaurant name — very informative
        'vicinity' => 0.5,  // Address — low signal, but catches neighbourhood food names
    ];

    /**
     * Stop words to strip before token overlap computation.
     * Bilingual ID/EN to cover both input modes.
     */
    private array $stopWords = [
        'the', 'a', 'an', 'and', 'or', 'i', 'want', 'need', 'find', 'get',
        'something', 'some', 'any', 'good', 'nice', 'best', 'great',
        'yang', 'untuk', 'saya', 'aku', 'mau', 'makan', 'di', 'ke',
        'dan', 'atau', 'ada', 'cari', 'pengen', 'ingin', 'deket',
        'sekitar', 'sini', 'dekat', 'near', 'nearby',
    ];

    public function __construct()
    {
        $this->apiKey = config('services.google_places.key', '');
    }

    public function getNearbyRestaurants(
        string $foodSearch,
        float $maxDistance,
        float $userLat = -6.2233,
        float $userLng = 106.6491,
        int $userBudget = 0
    ): array {
        if (empty($this->apiKey)) {
            Log::warning('No API Key found.');
            return [];
        }

        $query = !empty(trim($foodSearch)) ? trim($foodSearch) : 'restaurant';

        $bucketLat = round($userLat, 2);
        $bucketLng = round($userLng, 2);
        $cacheKey  = 'places_' . md5($query) . "_{$bucketLat}_{$bucketLng}_{$maxDistance}";

        $places = cache()->get($cacheKey);

        if ($places === null) {
            $response = Http::timeout(20)
                ->connectTimeout(10)
                ->retry(3, 200)
                ->withHeaders([
                    'X-Goog-Api-Key'   => $this->apiKey,
                    'X-Goog-FieldMask' => 'places.id,places.displayName,places.priceLevel,places.priceRange,places.rating,places.userRatingCount,places.photos,places.types,places.location,places.formattedAddress,places.regularOpeningHours',
                ])->post('https://places.googleapis.com/v1/places:searchText', [
                    'textQuery'      => $query,
                    'locationBias'   => [
                        'circle' => [
                            'center' => ['latitude' => $userLat, 'longitude' => $userLng],
                            'radius' => (float) $maxDistance,
                        ],
                    ],
                    'maxResultCount' => 20,
                ]);

            if ($response->failed()) {
                Log::error('Google Places API Error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                    'query'  => $query,
                ]);
                return [];
            }

            $places = $response->json('places') ?? [];

            if (!empty($places)) {
                cache()->put($cacheKey, $places, now()->addMinutes(10));
            }

            Log::info('Google Places API called for "' . $query . '": ' .
                implode(', ', array_column(array_column($places, 'displayName'), 'text'))
            );
        } else {
            Log::info('Google Places cache hit for key: ' . $cacheKey);
        }

        $mapped = $this->mapResults($places, $userLat, $userLng);

        if ($userBudget > 0) {
            $mapped = $this->addPriceComments($mapped, $userBudget);
        }

        return $mapped;
    }

    private function mapResults(array $places, float $userLat, float $userLng): array
    {
        $results = [];
        foreach ($places as $place) {
            $lat  = $place['location']['latitude'];
            $lng  = $place['location']['longitude'];
            $name = $place['displayName']['text'] ?? 'Unknown';

            $photoRef = $place['photos'][0]['name'] ?? null;
            $photoUrl = $photoRef
                ? "https://places.googleapis.com/v1/{$photoRef}/media?maxWidthPx=400&key={$this->apiKey}"
                : null;

            $priceDisplay = 'Price N/A';
            if (!empty($place['priceRange'])) {
                $start        = ($place['priceRange']['startPrice']['units'] ?? 0) / 1000;
                $end          = ($place['priceRange']['endPrice']['units']   ?? 0) / 1000;
                $priceDisplay = "Rp {$start}k - {$end}k";
            } elseif (!empty($place['priceLevel'])) {
                $map = [
                    'PRICE_LEVEL_FREE'           => '$',
                    'PRICE_LEVEL_INEXPENSIVE'    => '$',
                    'PRICE_LEVEL_MODERATE'       => '$$',
                    'PRICE_LEVEL_EXPENSIVE'      => '$$$',
                    'PRICE_LEVEL_VERY_EXPENSIVE' => '$$$$',
                ];
                $priceDisplay = $map[$place['priceLevel']] ?? '$$';
            }

            $results[] = [
                'name'            => $name,
                'google_place_id' => $place['id'],
                'lat'             => $lat,
                'lng'             => $lng,
                'distance'        => $this->calculateDistance($userLat, $userLng, $lat, $lng),
                'rating'          => (float) ($place['rating'] ?? 3.0),
                'review_count'    => (int)   ($place['userRatingCount'] ?? 0),
                'exact_price'     => $this->getExactPriceForSaw($place),
                'price_range'     => $place['priceRange'] ?? null,
                'price_display'   => $priceDisplay,
                'types'           => $this->cleanTypes($place['types'] ?? [], $name),
                'food_match'      => 0.0, // will be populated by applyFoodMatch()
                'open_now'        => $place['regularOpeningHours']['openNow'] ?? null,
                'photo_url'       => $photoUrl,
                'vicinity'        => $place['formattedAddress'] ?? '',
            ];
        }
        return $results;
    }

    private function getExactPriceForSaw(array $place): float
    {
        if (!empty($place['priceRange']['startPrice']['units'])) {
            return (float) $place['priceRange']['startPrice']['units'];
        }

        if (!empty($place['priceLevel'])) {
            $level = $place['priceLevel'];
            if ($level === 'PRICE_LEVEL_INEXPENSIVE')    return 30000.0;
            if ($level === 'PRICE_LEVEL_MODERATE')       return 75000.0;
            if ($level === 'PRICE_LEVEL_EXPENSIVE')      return 150000.0;
            if ($level === 'PRICE_LEVEL_VERY_EXPENSIVE') return 300000.0;
        }

        return 50000.0;
    }

    public function addPriceComments(array $candidates, int $userBudget): array
    {
        foreach ($candidates as &$r) {
            $r['price_comment'] = null;

            if (!empty($r['price_range'])) {
                $minPrice = (int) $r['price_range']['startPrice']['units'];
                if ($minPrice <= $userBudget) {
                    $r['price_comment'] = 'Affordable';
                } elseif ($minPrice <= ($userBudget * 1.5)) {
                    $r['price_comment'] = 'Slightly above budget';
                } else {
                    $r['price_comment'] = 'Very expensive';
                }
            }
        }
        return $candidates;
    }

    /**
     * Compute a weighted token overlap relevance score between the user's
     * FoodSearch query and each candidate's available text fields.
     *
     * Formula:
     *   rel(Q, P) = min(1, (1/|Q|) * Σ_{t∈Q} max_field_weight(t, P))
     *
     * When FoodSearch is empty (e.g. "dinner romantis buat anniversary"),
     * all candidates receive food_match = 1.0 so C2 abstains from
     * differentiating and the other three SAW criteria take over.
     *
     * @param  array  $candidates  Restaurant candidates from mapResults()
     * @param  string $foodSearch  Free-text food intent from NLP or filter
     * @return array  Same candidates with food_match populated (0.0–1.0)
     */
    public function applyFoodMatch(array $candidates, string $foodSearch): array
    {
        $queryTokens = $this->tokenize($foodSearch);

        // Empty query → criterion abstains, everyone gets neutral 1.0
        if (empty($queryTokens)) {
            return array_map(function ($r) {
                $r['food_match'] = 1.0;
                return $r;
            }, $candidates);
        }

        return array_map(function ($r) use ($queryTokens) {
            $r['food_match'] = $this->computeRelevance($queryTokens, $r);
            return $r;
        }, $candidates);
    }

    /**
     * Core relevance computation for a single (query, place) pair.
     * Called by applyFoodMatch() for SAW candidates and by PromotionService
     * for promoted place matching.
     *
     * @param  array $queryTokens  Pre-tokenized query terms
     * @param  array $place        A candidate array with 'name', 'types', 'vicinity' keys
     * @return float               Score in [0.0, 1.0]
     */
    public function computeRelevance(array $queryTokens, array $place): float
    {
        if (empty($queryTokens)) {
            return 1.0;
        }

        // Build per-field token sets
        $fieldTokens = [
            'types'    => $this->tokenize(implode(' ', $place['types'] ?? [])),
            'name'     => $this->tokenize($place['name'] ?? ''),
            'vicinity' => $this->tokenize($place['vicinity'] ?? ''),
        ];

        $totalWeight = 0.0;

        foreach ($queryTokens as $token) {
            $bestWeight = 0.0;
            foreach ($this->fieldWeights as $field => $weight) {
                if (in_array($token, $fieldTokens[$field], true)) {
                    $bestWeight = max($bestWeight, $weight);
                }
            }
            $totalWeight += $bestWeight;
        }

        // Normalize by query length, cap at 1.0
        return min(1.0, $totalWeight / count($queryTokens));
    }

    /**
     * Tokenize a string into a clean, comparable token array.
     * - Lowercased
     * - Punctuation stripped
     * - Stop words removed
     * - No stemming (declared limitation for reproducibility)
     *
     * @param  string $text
     * @return array<string>
     */
    public function tokenize(string $text): array
    {
        if (empty(trim($text))) {
            return [];
        }

        // Lowercase and strip punctuation
        $text   = strtolower($text);
        $text   = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        $tokens = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        // Remove stop words
        return array_values(
            array_filter($tokens, fn($t) => !in_array($t, $this->stopWords, true))
        );
    }

    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) * sin($dLat / 2)
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
              * sin($dLng / 2) * sin($dLng / 2);
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    private function cleanTypes(array $googleTypes, string $name = ''): array
    {
        $noise = [
            'restaurant', 'food', 'point_of_interest',
            'establishment', 'store', 'meal_takeaway',
            'meal_delivery', 'bar',
        ];

        $map = [
            'japanese_restaurant'   => 'japanese',
            'ramen_restaurant'      => 'ramen',
            'sushi_restaurant'      => 'sushi',
            'indonesian_restaurant' => 'indonesian',
            'burger_restaurant'     => 'burger',
            'pizza_restaurant'      => 'pizza',
            'chicken_restaurant'    => 'chicken',
            'coffee_shop'           => 'coffee',
            'cafe'                  => 'coffee',
            'fast_food_restaurant'  => 'fastfood',
            'chinese_restaurant'    => 'chinese',
            'korean_restaurant'     => 'korean',
            'seafood_restaurant'    => 'seafood',
            'steak_house'           => 'steak',
            'dim_sum_restaurant'    => 'dimsum',
            'vegetarian_restaurant' => 'vegetarian',
            'vegan_restaurant'      => 'vegan',
        ];

        $cleaned = [];
        foreach ($googleTypes as $type) {
            if (in_array($type, $noise)) continue;
            $cleaned[] = $map[$type] ?? str_replace('_restaurant', '', $type);
        }

        if (empty($cleaned) && !empty($name)) {
            $cleaned = $this->inferTypesFromName($name);
        }

        return array_values(array_unique($cleaned));
    }

    private function inferTypesFromName(string $name): array
    {
        $name = strtolower($name);

        $keywords = [
            'ramen'           => 'ramen',
            'mie'             => 'ramen',
            'noodle'          => 'ramen',
            'pho'             => 'ramen',
            'sushi'           => 'sushi',
            'sashimi'         => 'sushi',
            'japanese'        => 'japanese',
            'jepang'          => 'japanese',
            'yoshinoya'       => 'japanese',
            'hokben'          => 'japanese',
            'pepper lunch'    => 'japanese',
            'shaburi'         => 'japanese',
            'yakiniku'        => 'japanese',
            'ichiban'         => 'japanese',
            'katsu'           => 'japanese',
            'tonkatsu'        => 'japanese',
            'dimsum'          => 'dimsum',
            'dim sum'         => 'dimsum',
            'yumcha'          => 'dimsum',
            'bakso'           => 'indonesian',
            'nasi'            => 'indonesian',
            'soto'            => 'indonesian',
            'padang'          => 'indonesian',
            'warteg'          => 'indonesian',
            'warung'          => 'indonesian',
            'masakan'         => 'indonesian',
            'indonesia'       => 'indonesian',
            'geprek'          => 'indonesian',
            'penyet'          => 'indonesian',
            'taman santap'    => 'indonesian',
            'bandar djakarta' => 'indonesian',
            'omakyo'          => 'indonesian',
            'burger'          => 'burger',
            'mcdonald'        => 'burger',
            'mcdonalds'       => 'burger',
            'wendy'           => 'burger',
            'smashburger'     => 'burger',
            'pizza'           => 'pizza',
            'chicken'         => 'chicken',
            'rooster'         => 'chicken',
            'ayam'            => 'chicken',
            'kfc'             => 'chicken',
            'nene'            => 'chicken',
            'chick'           => 'chicken',
            'coffee'          => 'coffee',
            'kopi'            => 'coffee',
            'cafe'            => 'coffee',
            'starbucks'       => 'coffee',
            'espresso'        => 'coffee',
            'brew'            => 'coffee',
            'vegetarian'      => 'vegetarian',
            'vegan'           => 'vegan',
            'halal'           => 'halal',
            'fastfood'        => 'fastfood',
            'fast food'       => 'fastfood',
        ];

        $inferred = [];
        foreach ($keywords as $keyword => $type) {
            if (str_contains($name, $keyword)) {
                $inferred[] = $type;
            }
        }

        return empty($inferred) ? ['indonesian'] : array_unique($inferred);
    }

    public function applyTimeWarning(array $candidates, string $visitTime): array
    {
        $periodHours = [
            'morning'   => 9,
            'lunch'     => 12,
            'afternoon' => 15,
            'evening'   => 18,
            'night'     => 21,
        ];

        return array_map(function ($r) use ($visitTime, $periodHours) {

            if ($visitTime === 'now') {
                $r['time_warning'] = ($r['open_now'] === false) ? 'Currently closed' : null;
                return $r;
            }

            $targetHour = $periodHours[$visitTime] ?? (int) date('H');
            $types      = $r['types'] ?? [];
            $price      = $r['price_level'] ?? 2;
            $warning    = null;

            if (in_array('fastfood', $types)) {
                if ($targetHour < 7 || $targetHour > 23) {
                    $warning = 'May be closed at this hour';
                }
            } elseif ($price >= 3) {
                if ($targetHour < 11 || $targetHour > 22) {
                    $warning = 'Fine dining may not be open at this hour';
                }
            } elseif (in_array('coffee', $types)) {
                if ($targetHour > 21) {
                    $warning = 'Cafe may be closed at this hour';
                }
            } else {
                if ($targetHour < 10 || $targetHour > 22) {
                    $warning = 'May be closed at this hour';
                }
            }

            $r['time_warning'] = $warning;
            return $r;

        }, $candidates);
    }

    public function getPhotoUrl(string $photoRef, int $maxWidth = 400): string
    {
        return "https://maps.googleapis.com/maps/api/place/photo"
             . "?maxwidth={$maxWidth}"
             . "&photo_reference={$photoRef}"
             . "&key={$this->apiKey}";
    }
}