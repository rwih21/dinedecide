<?php

namespace App\Http\Controllers;

use App\Models\RecommendationLog;
use App\Models\SearchHistory;
use App\Services\GooglePlacesService;
use App\Services\NlpService;
use App\Services\SawService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RestaurantController extends Controller
{
    public function __construct(
        private NlpService           $nlp,
        private GooglePlacesService  $places,
        private SawService           $saw,
    ) {}

    // Show the main search page
    public function index()
    {
        return view('restaurants.index');
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

        // --- Fetch candidates with dynamic location ---
        $candidates = $this->places->getNearbyRestaurants(
            $intent['FoodType'],
            $intent['MaxDistance'],
            $userLat,
            $userLng
        );

        $candidates = $this->places->applyFoodMatch($candidates, $intent['FoodType']);
        $candidates = $this->places->applyTimeWarning($candidates, $intent['VisitTime']);

        $candidates = array_values(array_filter(
            $candidates,
            fn($r) => $r['price_level'] <= $intent['MaxPrice']
        ));

        $ranked = $this->saw->rank($candidates);

        // --- Fallback ---
        if (empty($ranked)) {
            $allCandidates = $this->places->getNearbyRestaurants('any', $intent['MaxDistance'], $userLat, $userLng);
            $allCandidates = $this->places->applyFoodMatch($allCandidates, 'any');
            $allCandidates = $this->places->applyTimeWarning($allCandidates, $intent['VisitTime']);
            $allCandidates = array_values(array_filter(
                $allCandidates,
                fn($r) => $r['price_level'] <= $intent['MaxPrice']
            ));

            $ranked  = $this->saw->rank($allCandidates);
            $relaxed = true;
        }

        if (empty($ranked)) {
            return back()->with('error', 'No restaurants found in this area. Try increasing your distance.');
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
            'userLng'
        ));
    }
}