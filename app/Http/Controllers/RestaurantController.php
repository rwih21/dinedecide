<?php

namespace App\Http\Controllers;

use App\Models\PromotedPlace;
use App\Models\RecommendationLog;
use App\Models\SearchHistory;
use App\Services\GooglePlacesService;
use App\Services\NlpService;
use App\Services\PromotionService;
use App\Services\SawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestaurantController extends Controller
{
    public function __construct(
        private NlpService           $nlp,
        private GooglePlacesService  $places,
        private SawService           $saw,
        private PromotionService     $promotions
    ) {}

    public function index()
    {
        return view('restaurants.index', [
            'lastLat' => session('last_lat', -6.2233),
            'lastLng' => session('last_lng', 106.6491),
        ]);
    }

    public function browse(Request $request)
    {
        $fromCache     = false;
        $cacheKey      = 'nearby_places';
        $cachedAt      = session('nearby_cached_at');
        $maxAgeSeconds = 600;

        $places = null;

        $allPromoted    = PromotedPlace::active()->latest()->get();
        $promotedPlaces = $allPromoted->isNotEmpty()
            ? $allPromoted->get(now()->second % $allPromoted->count())
            : null;

        if ($cachedAt && now()->diffInSeconds($cachedAt) < $maxAgeSeconds) {
            $places    = session($cacheKey);
            $fromCache = true;
        }

        if (empty($places)) {
            $userLat = session('last_lat', -6.2233);
            $userLng = session('last_lng', 106.6491);

            $places = $this->places->getNearbyRestaurants('restaurant', 3000, $userLat, $userLng, 0);
            $places = $this->places->applyFoodMatch($places, '');
            $places = $this->places->applyTimeWarning($places, 'now');

            usort($places, fn($a, $b) => $a['distance'] <=> $b['distance']);

            session([
                $cacheKey          => $places,
                'nearby_cached_at' => now(),
            ]);
        }

        return view('restaurants.browse', compact('places', 'fromCache', 'promotedPlaces'));
    }

    public function search(Request $request)
    {
        $request->validate([
            'mode'         => 'required|in:nlp,filter',
            'query'        => 'nullable|string|min:3|max:255',
            'food_type'    => 'nullable|string',
            'max_price'    => 'nullable|integer|min:0|max:200000',
            'max_distance' => 'nullable|integer',
            'visit_time'   => 'nullable|string',
            'latitude'     => 'required|numeric|between:-90,90',
            'longitude'    => 'required|numeric|between:-180,180',
        ]);

        if ($request->input('mode') === 'nlp' && !$request->filled('query')) {
            return back()->withErrors(['query' => 'Please describe what you\'re craving.']);
        }

        $userLat  = (float) $request->input('latitude');
        $userLng  = (float) $request->input('longitude');
        $rawQuery = '';
        $relaxed  = false;

        session(['last_lat' => $userLat, 'last_lng' => $userLng]);

        // --- Build intent ---
        if ($request->input('mode') === 'nlp') {
            $rawQuery = $request->input('query');
            $intent   = $this->nlp->extractIntent($rawQuery);

        } else {
            // Filter mode: derive FoodSearch from the dropdown value
            $selectedFood = $request->input('food_type', 'any');

            // Build a human-readable search string for Google
            $foodSearch = $selectedFood === 'any' ? '' : $selectedFood;

            $rawQuery = 'Filter: '
                . $selectedFood . ', '
                . 'Rp ' . number_format((int) $request->input('max_price', 0)) . ', '
                . ($request->input('max_distance', 3000) / 1000) . 'km';

            $intent = [
                'FoodSearch'  => $foodSearch,
                'MaxPrice'    => (int) $request->input('max_price', 0),
                'MaxDistance' => (float) $request->input('max_distance', 3000),
                'Occasion'    => 'any',
                'VisitTime'   => $request->input('visit_time', 'now'),
            ];
        }

        $userBudget          = $intent['MaxPrice'] ?? 0;
        $intent['MaxBudget'] = $userBudget;

        // --- Fetch promoted place ---
        $picked         = $this->promotions->pick($intent);
        $promotedPlaces = $picked->isNotEmpty()
            ? $picked->get(now()->second % $picked->count())
            : null;

        // --- Fetch candidates ---
        // FoodSearch is the single source of truth for the Google query.
        // Use 'restaurant' as fallback when FoodSearch is empty so Google
        // still returns nearby places.
        $googleQuery = !empty($intent['FoodSearch']) ? $intent['FoodSearch'] : 'restaurant';

        $candidates = $this->places->getNearbyRestaurants(
            $googleQuery,
            $intent['MaxDistance'],
            $userLat,
            $userLng,
            $intent['MaxBudget']
        );

        // Apply weighted token overlap relevance scoring for C2
        $candidates = $this->places->applyFoodMatch($candidates, $intent['FoodSearch']);
        $candidates = $this->places->applyTimeWarning($candidates, $intent['VisitTime']);

        // Filter by budget (allow 1.5× tolerance)
        $candidates = array_values(array_filter(
            $candidates,
            fn($r) => $intent['MaxBudget'] === 0 || $r['exact_price'] <= ($intent['MaxBudget'] * 1.5)
        ));

        // Deduplicate chains — keep only the closest branch
        $seen       = [];
        $candidates = array_filter($candidates, function ($r) use (&$seen) {
            $baseName = preg_replace('/[-–|@]\s*.+$/', '', $r['name']);
            $baseName = strtolower(trim($baseName));

            if (isset($seen[$baseName])) {
                return false;
            }
            $seen[$baseName] = true;
            return true;
        });
        $candidates = array_values($candidates);

        $ranked = $this->saw->rank($candidates);

        // --- Fallback: no results → relax food constraint ---
        if (empty($ranked)) {
            $allCandidates = $this->places->getNearbyRestaurants(
                'restaurant',
                $intent['MaxDistance'],
                $userLat,
                $userLng,
                $intent['MaxBudget']
            );

            $allCandidates = $this->places->applyFoodMatch($allCandidates, '');
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

        // --- Persist search history ---
        $search = SearchHistory::create([
            'user_id'              => Auth::id(),
            'raw_query'            => $rawQuery,
            'extracted_food_search' => $intent['FoodSearch'],
            'latitude'             => $userLat,
            'longitude'            => $userLng,
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