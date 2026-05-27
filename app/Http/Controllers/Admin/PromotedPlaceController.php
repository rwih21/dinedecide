<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromotedPlace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PromotedPlaceController extends Controller
{
    public function index()
    {
        $places = PromotedPlace::latest()->get();
        return view('admin.promoted.index', compact('places'));
    }

    public function create()
    {
        return view('admin.promoted.form', ['place' => new PromotedPlace]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);

        if ($request->hasFile('photo')) {
            $validated['photo_path'] = $request->file('photo')->store('promotions', 'public');
        }

        $validated['food_types'] = array_filter(
            array_map('trim', explode(',', $request->input('food_types_raw', '')))
        );

        PromotedPlace::create($validated);

        return redirect()->route('admin.promoted.index')
                         ->with('status', 'Promoted place created successfully.');
    }

    public function edit(PromotedPlace $promoted)
    {
        return view('admin.promoted.form', ['place' => $promoted]);
    }

    public function update(Request $request, PromotedPlace $promoted)
    {
        $validated = $this->validateRequest($request, $promoted->id);

        if ($request->hasFile('photo')) {
            // Delete old photo if it exists
            if ($promoted->photo_path) {
                Storage::disk('public')->delete($promoted->photo_path);
            }
            $validated['photo_path'] = $request->file('photo')->store('promotions', 'public');
        }

        $validated['food_types'] = array_filter(
            array_map('trim', explode(',', $request->input('food_types_raw', '')))
        );

        $promoted->update($validated);

        return redirect()->route('admin.promoted.index')
                         ->with('status', 'Promoted place updated successfully.');
    }

    public function destroy(PromotedPlace $promoted)
    {
        if ($promoted->photo_path) {
            Storage::disk('public')->delete($promoted->photo_path);
        }

        $promoted->delete();

        return redirect()->route('admin.promoted.index')
                         ->with('status', 'Promoted place deleted.');
    }

    public function toggle(PromotedPlace $promoted)
    {
        $promoted->update(['is_active' => !$promoted->is_active]);

        return back()->with('status', 'Status updated.');
    }

    private function validateRequest(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'          => 'required|string|max:255',
            'description'   => 'nullable|string|max:1000',
            'address'       => 'nullable|string|max:500',
            'latitude'      => 'nullable|numeric|between:-90,90',
            'longitude'     => 'nullable|numeric|between:-180,180',
            'food_types_raw'=> 'required|string',   // comma-separated, e.g. "chicken, indonesian"
            'price_display' => 'required|string|max:100',
            'min_price'     => 'required|integer|min:0',
            'photo'         => 'nullable|image|max:2048',
            'whatsapp'      => 'nullable|string|max:30',
            'gmaps_url'     => 'nullable|url|max:500',
            'is_active'     => 'boolean',
            'starts_at'     => 'nullable|date',
            'ends_at'       => 'nullable|date|after_or_equal:starts_at',
        ]);
    }
}