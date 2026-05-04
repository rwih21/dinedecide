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
            'max_distance' => 'nullable|integer'
        ]);

        // dd([
        //     'mode'       => $request->input('mode'),
        //     'intent'     => $request->input('mode') === 'nlp'
        //                     ? $this->nlp->extractIntent($request->input('query'))
        //                     : ['FoodType' => $request->input('food_type'), 'MaxPrice' => $request->input('max_price'), 'MaxDistance' => $request->input('max_distance')],
        //     'api_key_set' => !empty(config('services.google_places.key')),
        // ]);

        if ($request->input('mode') === 'nlp' && !$request->filled('query')) {
            return back()->withErrors(['query' => 'Please describe what you\'re craving.']);
        }

        $userLat = -6.2233;
        $userLng =  106.6491;
        $rawQuery = '';
        $relaxed  = false; // tracks if we fell back to Option 2

        // --- Build intent depending on mode ---
        if ($request->input('mode') === 'nlp') {
            $rawQuery = $request->input('query');
            $intent   = $this->nlp->extractIntent($rawQuery);
        } else {
            // Filter chip mode — intent comes directly from user selections
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

        // --- Fetch candidates ---
        $candidates = $this->places->getNearbyRestaurants(
            $intent['FoodType'],
            $intent['MaxDistance']
        );

        $candidates = $this->places->applyFoodMatch($candidates, $intent['FoodType']);

        // Apply time warning (soft filter)
        $candidates = $this->places->applyTimeWarning($candidates, $intent['VisitTime']);

// Filter by MaxPrice

        // Filter by MaxPrice
        $candidates = array_values(array_filter(
            $candidates,
            fn($r) => $r['price_level'] <= $intent['MaxPrice']
        ));

        // --- SAW ranking ---
        $ranked = $this->saw->rank($candidates);

        // --- Option 2 fallback: relax food filter if empty ---
        if (empty($ranked)) {
            $allCandidates = $this->places->getNearbyRestaurants('any', $intent['MaxDistance']);
            $allCandidates = $this->places->applyFoodMatch($allCandidates, 'any');
            $allCandidates = $this->places->applyTimeWarning($allCandidates, $intent['VisitTime']);
            $allCandidates = array_values(array_filter(
                $allCandidates,
                fn($r) => $r['price_level'] <= $intent['MaxPrice']
            ));
            $ranked  = $this->saw->rank($allCandidates);
            $relaxed = true;
        }

        // If still empty after fallback, truly nothing nearby
        if (empty($ranked)) {
            return back()->with('error', 'No restaurants found in this area. Try increasing your distance.');
        }

        // --- Save to database ---
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
            'relaxed'
        ));
    }
}