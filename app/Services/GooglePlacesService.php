<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class GooglePlacesService
{
    private Client $client;
    private string $apiKey;
    private string $baseUrl = 'https://maps.googleapis.com/maps/api/place/';

    private float $userLat = -6.2233;
    private float $userLng = 106.6491;

    public function __construct()
    {
        $this->apiKey = config('services.google_places.key', '');

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 10.0,
        ]);
    }

    public function getNearbyRestaurants(
        string $foodType,
        float $maxDistance,
        float $userLat = -6.2233,
        float $userLng = 106.6491
    ): array {
        if (empty($this->apiKey)) {
            Log::warning('GooglePlacesService: No API key, using dummy data.');
            return $this->getDummyData($maxDistance, $userLat, $userLng);
        }

        try {
            $general  = $this->fetchFromGoogle($maxDistance, '', $userLat, $userLng);
            $targeted = [];

            if ($foodType !== 'any') {
                $targeted = $this->fetchFromGoogle($maxDistance, $foodType, $userLat, $userLng);
            }

            $all    = array_merge($general, $targeted);
            $seen   = [];
            $unique = [];

            foreach ($all as $place) {
                if (!in_array($place['google_place_id'], $seen)) {
                    $seen[]   = $place['google_place_id'];
                    $unique[] = $place;
                }
            }

            return $unique;

        } catch (GuzzleException $e) {
            Log::error('GooglePlacesService Guzzle error: ' . $e->getMessage());
            return $this->getDummyData($maxDistance, $userLat, $userLng);
        }
    }

    private function fetchFromGoogle(
        float $maxDistance,
        string $keyword = '',
        float $userLat = -6.2233,
        float $userLng = 106.6491
    ): array {
        $params = [
            'location' => "{$userLat},{$userLng}",
            'radius'   => (int) $maxDistance,
            'type'     => 'restaurant',
            'key'      => $this->apiKey,
        ];

        if (!empty($keyword)) {
            $params['keyword'] = $keyword;
        }

        $response = $this->client->get('nearbysearch/json', ['query' => $params]);
        $data     = json_decode($response->getBody()->getContents(), true);

        if (($data['status'] ?? '') !== 'OK') {
            Log::error('GooglePlacesService API error: ' . ($data['status'] ?? 'unknown'));
            return [];
        }

        return $this->mapResults($data['results'], $userLat, $userLng);
    }

    private function mapResults(
        array $results,
        float $userLat = -6.2233,
        float $userLng = 106.6491
    ): array {
        $mapped = [];

        $nonFoodTypes = [
            'furniture_store', 'home_goods_store', 'hardware_store',
            'clothing_store', 'electronics_store'
        ];

        foreach ($results as $place) {
            if (!empty(array_intersect($place['types'] ?? [], $nonFoodTypes))) {
                continue;
            }

            $lat = $place['geometry']['location']['lat'];
            $lng = $place['geometry']['location']['lng'];

            $mapped[] = [
                'name'            => $place['name'],
                'google_place_id' => $place['place_id'],
                'lat'             => $lat,
                'lng'             => $lng,
                'distance'        => $this->calculateDistance($userLat, $userLng, $lat, $lng),
                'rating'          => (float) ($place['rating'] ?? 3.0),
                'review_count'    => (int)   ($place['user_ratings_total'] ?? 0),
                'price_level'     => (int)   ($place['price_level'] ?? 2),
                'types'           => $this->cleanTypes($place['types'] ?? [], $place['name']),
                'food_match'      => 0,
                'open_now'        => $place['opening_hours']['open_now'] ?? null,
                'photo_ref'       => $place['photos'][0]['photo_reference'] ?? null,
                'vicinity'        => $place['vicinity'] ?? '',
            ];
        }

        return $mapped;
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

        // If Google gave us nothing useful, infer from restaurant name
        if (empty($cleaned) && !empty($name)) {
            $cleaned = $this->inferTypesFromName($name);
        }

        return array_values(array_unique($cleaned));
    }

    private function inferTypesFromName(string $name): array
    {
        $name = strtolower($name);

        $keywords = [
            // Ramen
            'ramen'      => 'ramen',
            'mie'        => 'ramen',
            'noodle'     => 'ramen',
            'pho'        => 'ramen',

            // Sushi / Japanese
            'sushi'      => 'sushi',
            'sashimi'    => 'sushi',
            'japanese'   => 'japanese',
            'jepang'     => 'japanese',
            'yoshinoya'  => 'japanese',
            'hokben'     => 'japanese',
            'pepper lunch' => 'japanese',
            'shaburi'    => 'japanese',
            'yakiniku'   => 'japanese',
            'ichiban'    => 'japanese',

            // Indonesian
            'bakso'      => 'indonesian',
            'nasi'       => 'indonesian',
            'soto'       => 'indonesian',
            'padang'     => 'indonesian',
            'warteg'     => 'indonesian',
            'warung'     => 'indonesian',
            'masakan'    => 'indonesian',
            'indonesia'  => 'indonesian',
            'taman santap' => 'indonesian',
            'bandar djakarta' => 'indonesian',
            'omakyo'     => 'indonesian',

            // Burger
            'burger'     => 'burger',
            'mcdonald'   => 'burger',
            'mcdonalds'  => 'burger',
            'wendy'      => 'burger',
            'smashburger'=> 'burger',

            // Pizza
            'pizza'      => 'pizza',

            // Chicken
            'chicken'    => 'chicken',
            'rooster'    => 'chicken',
            'ayam'       => 'chicken',
            'kfc'        => 'chicken',
            'nene'       => 'chicken',
            'chick'      => 'chicken',

            // Coffee
            'coffee'     => 'coffee',
            'kopi'       => 'coffee',
            'cafe'       => 'coffee',
            'starbucks'  => 'coffee',
            'espresso'   => 'coffee',
            'brew'       => 'coffee',

            // Fastfood
            'fastfood'   => 'fastfood',
            'fast food'  => 'fastfood',
        ];

        $inferred = [];
        foreach ($keywords as $keyword => $type) {
            if (str_contains($name, $keyword)) {
                $inferred[] = $type;
            }
        }

        // Default to indonesian if nothing matched — most restaurants
        // around Alam Sutera that have no specific keyword are local Indonesian
        return empty($inferred) ? ['indonesian'] : array_unique($inferred);
    }

    public function applyFoodMatch(array $candidates, string $foodType): array
    {
        if ($foodType === 'any') {
            return array_map(function ($r) {
                $r['food_match'] = 1;
                return $r;
            }, $candidates);
        }

        return array_map(function ($r) use ($foodType) {
            $direct  = in_array($foodType, $r['types']);
            $partial = !$direct && collect($r['types'])->contains(
                fn($t) => str_contains($t, $foodType) || str_contains($foodType, $t)
            );
            $r['food_match'] = ($direct || $partial) ? 1 : 0;
            return $r;
        }, $candidates);
    }

    /**
 * Apply time warning flag to candidates.
 * Uses open_now for current time, or infers from visit time period.
 */
    public function applyTimeWarning(array $candidates, string $visitTime): array
    {
        // Time period to hour mapping (start hour of period)
        $periodHours = [
            'morning'   => 9,
            'lunch'     => 12,
            'afternoon' => 15,
            'evening'   => 18,
            'night'     => 21,
        ];

        return array_map(function ($r) use ($visitTime, $periodHours) {

            if ($visitTime === 'now') {
                // Use Google's open_now directly
                $r['time_warning'] = ($r['open_now'] === false)
                    ? 'Currently closed'
                    : null;
                return $r;
            }

            $targetHour = $periodHours[$visitTime] ?? (int) date('H');

            // Soft heuristic: fast food usually open all day,
            // fine dining usually closed before 11am and after 10pm,
            // cafes usually closed after 9pm
            $types = $r['types'] ?? [];
            $price = $r['price_level'] ?? 2;
            $warning = null;

            if (in_array('fastfood', $types)) {
                // Fast food: open 07-23, likely fine anytime
                if ($targetHour < 7 || $targetHour > 23) {
                    $warning = 'May be closed at this hour';
                }
            } elseif ($price >= 3) {
                // Fine dining: typically 11-22
                if ($targetHour < 11 || $targetHour > 22) {
                    $warning = 'Fine dining may not be open at this hour';
                }
            } elseif (in_array('coffee', $types)) {
                // Cafes: typically close around 21
                if ($targetHour > 21) {
                    $warning = 'Cafe may be closed at this hour';
                }
            } else {
                // General restaurants: 10-22
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

    public function calculateDistance(
        float $lat1, float $lng1,
        float $lat2, float $lng2
    ): float {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2)
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
           * sin($dLng/2) * sin($dLng/2);
        return round($earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    // dummy data for 
    private function getDummyData(
        float $maxDistance,
        float $userLat = -6.2233,
        float $userLng = 106.6491
    ): array {
        $places = [
            ['name' => 'Ikkudo Ichi Ramen',      'lat' => -6.2248, 'lng' => 106.6510, 'rating' => 4.5, 'price_level' => 2, 'types' => ['ramen', 'japanese']],
            ['name' => 'Ramen Bajuri',            'lat' => -6.2260, 'lng' => 106.6478, 'rating' => 4.2, 'price_level' => 1, 'types' => ['ramen', 'indonesian']],
            ['name' => 'Yoshinoya',               'lat' => -6.2241, 'lng' => 106.6502, 'rating' => 3.8, 'price_level' => 1, 'types' => ['japanese', 'fastfood']],
            ['name' => 'Nasi Goreng Kambing',     'lat' => -6.2290, 'lng' => 106.6520, 'rating' => 4.6, 'price_level' => 2, 'types' => ['indonesian']],
            ['name' => 'Pizza Hut',               'lat' => -6.2310, 'lng' => 106.6540, 'rating' => 3.9, 'price_level' => 2, 'types' => ['pizza']],
            ['name' => 'Sushi Tei',               'lat' => -6.2255, 'lng' => 106.6495, 'rating' => 4.3, 'price_level' => 3, 'types' => ['sushi', 'japanese']],
            ['name' => 'Bakso Malang',            'lat' => -6.2200, 'lng' => 106.6460, 'rating' => 4.4, 'price_level' => 1, 'types' => ['indonesian']],
            ['name' => 'McDonald\'s Alam Sutera', 'lat' => -6.2235, 'lng' => 106.6505, 'rating' => 4.0, 'price_level' => 1, 'types' => ['burger', 'fastfood']],
            ['name' => 'Solaria',                 'lat' => -6.2270, 'lng' => 106.6488, 'rating' => 3.7, 'price_level' => 2, 'types' => ['indonesian']],
            ['name' => 'Pepper Lunch',            'lat' => -6.2245, 'lng' => 106.6498, 'rating' => 4.1, 'price_level' => 2, 'types' => ['japanese']],
            ['name' => 'Hokben',                  'lat' => -6.2238, 'lng' => 106.6492, 'rating' => 4.0, 'price_level' => 1, 'types' => ['japanese', 'fastfood']],
            ['name' => 'Warung Padang Sederhana', 'lat' => -6.2215, 'lng' => 106.6472, 'rating' => 4.5, 'price_level' => 1, 'types' => ['indonesian']],
            ['name' => 'Starbucks Alam Sutera',   'lat' => -6.2242, 'lng' => 106.6507, 'rating' => 4.3, 'price_level' => 3, 'types' => ['coffee']],
            ['name' => 'Burger King',             'lat' => -6.2265, 'lng' => 106.6515, 'rating' => 4.0, 'price_level' => 2, 'types' => ['burger', 'fastfood']],
            ['name' => 'Mie Ramen 88',            'lat' => -6.2225, 'lng' => 106.6480, 'rating' => 4.3, 'price_level' => 1, 'types' => ['ramen']],
            ['name' => 'KFC Alam Sutera',         'lat' => -6.2237, 'lng' => 106.6493, 'rating' => 3.9, 'price_level' => 1, 'types' => ['chicken', 'fastfood']],
            ['name' => 'Shaburi Shabu-shabu',     'lat' => -6.2300, 'lng' => 106.6535, 'rating' => 4.4, 'price_level' => 3, 'types' => ['japanese']],
            ['name' => 'Nene Chicken',            'lat' => -6.2243, 'lng' => 106.6496, 'rating' => 4.2, 'price_level' => 2, 'types' => ['chicken']],
            ['name' => 'Ichiban Sushi',           'lat' => -6.2280, 'lng' => 106.6530, 'rating' => 4.1, 'price_level' => 2, 'types' => ['sushi', 'japanese']],
            ['name' => 'Chatime',                 'lat' => -6.2252, 'lng' => 106.6501, 'rating' => 4.2, 'price_level' => 1, 'types' => ['coffee']],
        ];

        foreach ($places as &$place) {
            $place['distance']        = $this->calculateDistance($userLat, $userLng, $place['lat'], $place['lng']);
            $place['google_place_id'] = 'dummy_' . str_replace(' ', '_', strtolower($place['name']));
            $place['food_match']      = 0;
            $place['open_now']        = true;
            $place['photo_ref']       = null;
            $place['review_count']    = 50;
            $place['vicinity']        = 'Alam Sutera, Tangerang';
        }

        return array_values(array_filter(
            $places,
            fn($p) => $p['distance'] <= $maxDistance
        ));
    }
}