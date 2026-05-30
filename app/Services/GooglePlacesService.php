<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.google_places.key', '');
    }

    public function getNearbyRestaurants(
        string $foodType,
        float $maxDistance,
        float $userLat = -6.2233,
        float $userLng = 106.6491,
        int $userBudget = 0
    ): array {
        if (empty($this->apiKey)) {
            Log::warning('No API Key found.');
            return [];
        }

        $query = 'Restaurant';
        if ($foodType !== 'any') {
            $query = $foodType . ' restaurant';
        }

        $response = Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey,
            'X-Goog-FieldMask' => 'places.id,places.displayName,places.priceLevel,places.priceRange,places.rating,places.userRatingCount,places.photos,places.types,places.location,places.formattedAddress,places.regularOpeningHours',
        ])->post('https://places.googleapis.com/v1/places:searchText', [
            'textQuery' => $query,
            'locationBias' => [
                'circle' => [
                    'center' => ['latitude' => $userLat, 'longitude' => $userLng],
                    'radius' => (float) $maxDistance
                ]
            ],
            'maxResultCount' => 20,
        ]);

        if ($response->failed()) {
            Log::error('Google Places API Error: ' . $response->body());
            return [];
        }

        $places = $response->json('places') ?? [];
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

            // --- FIX: build priceDisplay correctly ---
            // priceRange takes priority (exact IDR range from Google)
            // priceLevel fallback maps the enum to $/$$/$$$/$$$$
            // Never pass the float from getExactPriceForSaw() into str_repeat()
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
                'food_match'      => 0,
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

    public function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat/2) * sin($dLat/2)
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
              * sin($dLng/2) * sin($dLng/2);
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    private function cleanTypes(array $googleTypes, string $name = ''): array
    {
        $noise = [
            'restaurant', 'food', 'point_of_interest',
            'establishment', 'store', 'meal_takeaway',
            'meal_delivery', 'cafe', 'bar',
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
            'fast_food_restaurant'  => 'fastfood',
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
            'ramen'         => 'ramen',
            'mie'           => 'ramen',
            'noodle'        => 'ramen',
            'pho'           => 'ramen',
            'sushi'         => 'sushi',
            'sashimi'       => 'sushi',
            'japanese'      => 'japanese',
            'jepang'        => 'japanese',
            'yoshinoya'     => 'japanese',
            'hokben'        => 'japanese',
            'pepper lunch'  => 'japanese',
            'shaburi'       => 'japanese',
            'yakiniku'      => 'japanese',
            'ichiban'       => 'japanese',
            'bakso'         => 'indonesian',
            'nasi'          => 'indonesian',
            'soto'          => 'indonesian',
            'padang'        => 'indonesian',
            'warteg'        => 'indonesian',
            'warung'        => 'indonesian',
            'masakan'       => 'indonesian',
            'indonesia'     => 'indonesian',
            'taman santap'  => 'indonesian',
            'bandar djakarta' => 'indonesian',
            'omakyo'        => 'indonesian',
            'burger'        => 'burger',
            'mcdonald'      => 'burger',
            'mcdonalds'     => 'burger',
            'wendy'         => 'burger',
            'smashburger'   => 'burger',
            'pizza'         => 'pizza',
            'chicken'       => 'chicken',
            'rooster'       => 'chicken',
            'ayam'          => 'chicken',
            'kfc'           => 'chicken',
            'nene'          => 'chicken',
            'chick'         => 'chicken',
            'coffee'        => 'coffee',
            'kopi'          => 'coffee',
            'cafe'          => 'coffee',
            'starbucks'     => 'coffee',
            'espresso'      => 'coffee',
            'brew'          => 'coffee',
            'fastfood'      => 'fastfood',
            'fast food'     => 'fastfood',
        ];

        $inferred = [];
        foreach ($keywords as $keyword => $type) {
            if (str_contains($name, $keyword)) {
                $inferred[] = $type;
            }
        }

        return empty($inferred) ? ['indonesian'] : array_unique($inferred);
    }

    public function applyFoodMatch(array $candidates, string $foodType): array
    {
        // If the user didn't specify a food, or if we explicitly searched Google 
        // using this foodType, we inherently TRUST that Google returned valid matches.
        // We give them all a baseline food_match of 1 so SAW doesn't delete them.
        return array_map(function ($r) use ($foodType) {
            $r['food_match'] = 1; 

            // Optional: Give a slight SAW score BOOST (+0.5) if the Google category 
            // strictly matches, just as an extra reward for perfect categorization.
            if ($foodType !== 'any') {
                $direct  = in_array($foodType, $r['types']);
                $partial = !$direct && collect($r['types'])->contains(
                    fn($t) => str_contains($t, $foodType) || str_contains($foodType, $t)
                );
                
                if ($direct || $partial) {
                    $r['food_match'] = 1.5; // Extra weight for exact categorical match
                }
            }

            return $r;
        }, $candidates);
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