<x-app-layout>
    <div class="min-h-screen bg-gray-50 px-4 py-10">
        <div class="max-w-2xl mx-auto">

            {{-- Back + query header --}}
            <div class="mb-8">
                <a href="{{ route('restaurants.index') }}"
                   class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
                    ← New search
                </a>
                <p class="text-gray-400 text-sm mt-3">Results for</p>
                <h2 class="text-xl font-semibold text-gray-800 mt-0.5">"{{ $rawQuery }}"</h2>
                <div class="flex gap-2 mt-2 flex-wrap">
                    <span class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full">
                        🍜 {{ $intent['FoodType'] }}
                    </span>
                    <span class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full">
                        💰 Price level ≤ {{ $intent['MaxPrice'] }}
                    </span>
                    <span class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full">
                        📍 Within {{ number_format($intent['MaxDistance'] / 1000, 1) }}km
                    </span>
                    @if($intent['Occasion'] !== 'any')
                    <span class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full">
                        🎭 {{ ucfirst($intent['Occasion']) }}
                    </span>
                    @endif
                    @if($intent['VisitTime'] !== 'now')
                    <span class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full">
                        🕐 {{ ucfirst($intent['VisitTime']) }}
                    </span>
                    @endif
                </div>
            </div>

            {{-- Relaxed --}}
            @if($relaxed)
            <div class="mb-4 px-4 py-3 rounded-xl text-sm"
                 style="background:#FFFBEB; color:#D97706; border:1px solid #FDE68A">
                No exact match found nearby — showing top rated options instead.
            </div>
            @endif

            {{-- Top Pick --}}
            <div class="mb-6">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-widest mb-3">Top Pick</p>
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5"
                     x-data="{ showMath: false }">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-xs font-medium bg-gray-800 text-white px-2 py-0.5 rounded-full">#1</span>
                                <h3 class="text-lg font-semibold text-gray-800">{{ $topPick['name'] }}</h3>
                            </div>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <span class="text-xs text-gray-500">⭐ {{ $topPick['rating'] }}</span>
                                <span class="text-xs text-gray-500">📍 {{ number_format($topPick['distance'], 0) }}m away</span>
                                <span class="text-xs text-gray-500">💰 {{ str_repeat('$', $topPick['price_level']) }}</span>
                            </div>
                            @if($topPick['time_warning'] ?? null)
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium mt-1 inline-block"
                                style="background:#FEF3C7; color:#D97706">
                                ⚠ {{ $topPick['time_warning'] }}
                            </span>
                            @endif
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-2xl font-bold text-gray-800">{{ round($topPick['saw_score'] * 100) }}%</p>
                            <p class="text-xs text-gray-400">Match</p>
                        </div>
                    </div>

                    {{-- Toggle math --}}
                    <button
                        @click="showMath = !showMath"
                        class="mt-4 text-xs text-gray-400 hover:text-gray-600 transition-colors flex items-center gap-1"
                    >
                        <span x-text="showMath ? '▾ Hide calculation' : '▸ Show calculation'"></span>
                    </button>

                    <div x-show="showMath" x-transition class="mt-3 bg-gray-50 rounded-xl p-4 space-y-2">
                        <p class="text-xs font-medium text-gray-500 mb-2">Recommendation Breakdown</p>
                        @php $b = $topPick['criteria_breakdown']; @endphp
                        <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                            <div>Distance <span class="text-gray-400">(w=0.35)</span></div>
                            <div class="text-right font-mono">{{ $b['C1_distance'] }} × 0.35 = {{ number_format($b['C1_distance'] * 0.35, 4) }}</div>
                            <div>Food Match <span class="text-gray-400">(w=0.30)</span></div>
                            <div class="text-right font-mono">{{ $b['C2_food_match'] }} × 0.30 = {{ number_format($b['C2_food_match'] * 0.30, 4) }}</div>
                            <div>Rating <span class="text-gray-400">(w=0.20)</span></div>
                            <div class="text-right font-mono">{{ $b['C3_rating'] }} × 0.20 = {{ number_format($b['C3_rating'] * 0.20, 4) }}</div>
                            <div class="text-gray-400 col-span-2 font-mono" style="font-size:10px">
                                ★ {{ $b['raw_rating'] }} raw · {{ $b['review_count'] }} reviews adjusted {{ $b['adjusted_rating'] }}
                            </div>
                            <div>Price <span class="text-gray-400">(w=0.15)</span></div>
                            <div class="text-right font-mono">{{ $b['C4_price_level'] }} × 0.15 = {{ number_format($b['C4_price_level'] * 0.15, 4) }}</div>
                        </div>
                        <div class="border-t border-gray-200 pt-2 flex justify-between text-xs font-semibold text-gray-700">
                            <span>Final Score (Vᵢ)</span>
                            <span class="font-mono">{{ $topPick['saw_score'] }}</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Alternatives --}}
            @if(!empty($alternatives))
            <div>
                <p class="text-xs font-medium text-gray-400 uppercase tracking-widest mb-3">Alternatives</p>
                <div class="space-y-3">
                    @foreach($alternatives as $alt)
                    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4"
                         x-data="{ showMath: false }">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-xs text-gray-400 font-medium">#{{ $alt['rank'] }}</span>
                                    <h3 class="text-base font-medium text-gray-800">{{ $alt['name'] }}</h3>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <span class="text-xs text-gray-500">⭐ {{ $alt['rating'] }}</span>
                                    <span class="text-xs text-gray-500">📍 {{ number_format($alt['distance'], 0) }}m</span>
                                    <span class="text-xs text-gray-500">💰 {{ str_repeat('$', $alt['price_level']) }}</span>
                                </div>
                                @if($alt['time_warning'] ?? null)
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium mt-1 inline-block"
                                    style="background:#FEF3C7; color:#D97706">
                                    ⚠ {{ $alt['time_warning'] }}
                                </span>
                                @endif
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-lg font-semibold text-gray-700">{{ round($alt['saw_score'] * 100) }}%</p>
                                <p class="text-xs text-gray-400">Match</p>
                            </div>
                        </div>

                        <button
                            @click="showMath = !showMath"
                            class="mt-3 text-xs text-gray-400 hover:text-gray-600 transition-colors"
                        >
                            <span x-text="showMath ? '▾ Hide' : '▸ Show math'"></span>
                        </button>

                        <div x-show="showMath" x-transition class="mt-2 bg-gray-50 rounded-xl p-3 space-y-1">
                            @php $b = $alt['criteria_breakdown']; @endphp
                            <div class="grid grid-cols-2 gap-1 text-xs text-gray-600">
                                <div>Distance</div><div class="text-right font-mono">{{ number_format($b['C1_distance'] * 0.35, 4) }}</div>
                                <div>Food Match</div><div class="text-right font-mono">{{ number_format($b['C2_food_match'] * 0.30, 4) }}</div>
                                <div>Rating</div><div class="text-right font-mono">{{ number_format($b['C3_rating'] * 0.20, 4) }}</div>
                                <div class="text-gray-400 col-span-2 font-mono" style="font-size:10px">
                                    ★ {{ $b['raw_rating'] }} · {{ $b['review_count'] }} reviews adj. {{ $b['adjusted_rating'] }}
                                </div>
                                <div>Price</div><div class="text-right font-mono">{{ number_format($b['C4_price_level'] * 0.15, 4) }}</div>
                            </div>
                            <div class="border-t border-gray-200 pt-1 flex justify-between text-xs font-semibold text-gray-700">
                                <span>Vᵢ</span>
                                <span class="font-mono">{{ $alt['saw_score'] }}</span>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>