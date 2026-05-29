<?php

namespace App\Http\Controllers;

use App\Models\RecommendationLog;
use App\Models\SearchHistory;
use App\Services\GooglePlacesService;
use App\Services\NlpService;
use App\Services\SawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\PromotionService;

class RestaurantController extends Controller
{
    public function __construct(
        private NlpService           $nlp,
        private GooglePlacesService  $places,
        private SawService           $saw,
        private PromotionService     $promotions
    ) {}

    // Show the main search page
    public function index()
    {
        return view('restaurants.index');
    }

    // Browse all nearby places (with session cache)
    public function browse(Request $request)
    {
        $fromCache     = false;
        $cacheKey      = 'nearby_places';
        $cachedAt      = session('nearby_cached_at');
        $maxAgeSeconds = 600; // 10 minutes

        $places = null;

        $promotedPlaces = \App\Models\PromotedPlace::active()->latest()->get();

        // Use cached data if it exists and is fresh enough
        if ($cachedAt && now()->diffInSeconds($cachedAt) < $maxAgeSeconds) {
            $places    = session($cacheKey);
            $fromCache = true;
        }

        // Fetch fresh if cache is missing or stale
        if (empty($places)) {
            // Use the user's last known location from session, or fall back to default
            $userLat = session('last_lat', -6.2233);
            $userLng = session('last_lng', 106.6491);

            $places = $this->places->getNearbyRestaurants('any', 3000, $userLat, $userLng, 0);
            $places = $this->places->applyFoodMatch($places, 'any');
            $places = $this->places->applyTimeWarning($places, 'now');

            // Sort by distance for browse view (natural order, not SAW ranked)
            usort($places, fn($a, $b) => $a['distance'] <=> $b['distance']);

            // Save to session cache
            session([
                $cacheKey          => $places,
                'nearby_cached_at' => now(),
            ]);
        }

        return view('restaurants.browse', compact('places', 'fromCache', 'promotedPlaces'));
    }

    // Handle the search request
    public function search(Request $request)
    {
        $request->validate([
            'mode'         => 'required|in:nlp,filter',
            'query'        => 'nullable|string|min:3|max:255',
            'food_type'    => 'nullable|string',
            'max_price'    => 'nullable|integer|min:1|max:4',
            'max_distance' => 'nullable|integer',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
        ]);

        if ($request->input('mode') === 'nlp' && !$request->filled('query')) {
            return back()->withErrors(['query' => 'Please describe what you\'re craving.']);
        }

        // Dynamic user location from form
        $userLat  = (float) $request->input('latitude');
        $userLng  = (float) $request->input('longitude');
        $rawQuery = '';
        $relaxed  = false;

        // Save user location to session so browse can reuse it
        session(['last_lat' => $userLat, 'last_lng' => $userLng]);

        // --- Build intent ---
        if ($request->input('mode') === 'nlp') {
            $rawQuery = $request->input('query');
            $intent   = $this->nlp->extractIntent($rawQuery);
        } else {
            $rawQuery = 'Filter: '
                . ($request->input('food_type', 'any')) . ', '
                . '$' . str_repeat('$', (int)$request->input('max_price', 4) - 1) . ', '
                . ($request->input('max_distance', 3000) / 1000) . 'km';

            $intent = [
                'FoodType'    => $request->input('food_type', 'any'),
                'MaxPrice'    => (int) $request->input('max_price', 4),
                'MaxDistance' => (float) $request->input('max_distance', 3000),
                'Occasion'    => 'any',
                'VisitTime'   => 'now',
            ];
        }

        // Convert the user's intent into an exact IDR budget for the new math
        $userBudget = 0;
        if ($request->input('mode') === 'nlp') {
            $userBudget = $intent['MaxPrice'] ?? 0;
        } else {
            $budgetMap  = [1 => 30000, 2 => 75000, 3 => 150000, 4 => 300000];
            $userBudget = $budgetMap[$intent['MaxPrice']] ?? 300000;
        }

        // Save it to the intent so we can use it below
        $intent['MaxBudget'] = $userBudget;

        // Fetch Promoted Place
        $promotedPlaces = $this->promotions->pick($intent);

        // --- Fetch candidates with dynamic location ---
        $candidates = $this->places->getNearbyRestaurants(
            $intent['FoodType'],
            $intent['MaxDistance'],
            $userLat,
            $userLng,
            $intent['MaxBudget']
        );

        $candidates = $this->places->applyFoodMatch($candidates, $intent['FoodType']);
        $candidates = $this->places->applyTimeWarning($candidates, $intent['VisitTime']);

        $candidates = array_values(array_filter(
            $candidates,
            fn($r) => $intent['MaxBudget'] === 0 || $r['exact_price'] <= ($intent['MaxBudget'] * 1.5)
        ));

        $ranked = $this->saw->rank($candidates);

        // --- Fallback ---
        if (empty($ranked)) {
            $allCandidates = $this->places->getNearbyRestaurants(
                'any',
                $intent['MaxDistance'],
                $userLat,
                $userLng,
                $intent['MaxBudget']
            );

            $allCandidates = $this->places->applyFoodMatch($allCandidates, 'any');
            $allCandidates = $this->places->applyTimeWarning($allCandidates, $intent['VisitTime']);

            $allCandidates = array_values(array_filter(
                $allCandidates,
                fn($r) => $intent['MaxBudget'] === 0 || $r['exact_price'] <= ($intent['MaxBudget'] * 1.5)
            ));

            $ranked  = $this->saw->rank($allCandidates);
            $relaxed = true;
        }

        if (empty($ranked)) {
            return back()->with('error', 'No restaurants found in this area. Try increasing your distance.');
        }

        // --- Warm the browse cache as a side effect ---
        // Only if the cache is missing or stale — avoids an extra API call
        // when the cache is already fresh
        if (!session('nearby_cached_at') ||
            now()->diffInSeconds(session('nearby_cached_at')) >= 600
        ) {
            $allNearby = $this->places->getNearbyRestaurants('any', 3000, $userLat, $userLng, 0);
            $allNearby = $this->places->applyFoodMatch($allNearby, 'any');
            $allNearby = $this->places->applyTimeWarning($allNearby, 'now');
            usort($allNearby, fn($a, $b) => $a['distance'] <=> $b['distance']);
            session([
                'nearby_places'    => $allNearby,
                'nearby_cached_at' => now(),
            ]);
        }

        // --- Save ---
        $search = SearchHistory::create([
            'user_id'             => Auth::id(),
            'raw_query'           => $rawQuery,
            'extracted_food_type' => $intent['FoodType'],
            'latitude'            => $userLat,
            'longitude'           => $userLng,
        ]);

        foreach ($ranked as $restaurant) {
            if ($restaurant['rank'] > 5) break;
            RecommendationLog::create([
                'search_id'          => $search->id,
                'restaurant_name'    => $restaurant['name'],
                'google_place_id'    => $restaurant['google_place_id'] ?? null,
                'saw_score'          => $restaurant['saw_score'],
                'rank'               => $restaurant['rank'],
                'criteria_breakdown' => $restaurant['criteria_breakdown'],
            ]);
        }

        $topPick      = $ranked[0];
        $alternatives = array_slice($ranked, 1, 4);

        return view('restaurants.results', compact(
            'topPick',
            'alternatives',
            'intent',
            'rawQuery',
            'relaxed',
            'userLat',
            'userLng',
            'promotedPlaces'
        ));
    }
}