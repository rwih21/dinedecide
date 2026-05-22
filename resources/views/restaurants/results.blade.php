<x-app-layout>
    <div class="min-h-screen px-4 py-10" style="background:#F9F9F8">
        <div class="max-w-2xl mx-auto">

            {{-- Back + query header --}}
            <div class="mb-8">
                <a href="{{ route('restaurants.index') }}"
                   class="inline-flex items-center gap-2 text-sm font-medium text-neutral-400 hover:text-emerald-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    New search
                </a>
                
                <div class="mt-5">
                    <p class="text-neutral-400 text-xs font-bold uppercase tracking-widest">Results for</p>
                    <h2 class="text-2xl font-bold text-neutral-900 mt-1 leading-tight">"{{ $rawQuery }}"</h2>
                </div>

                {{-- Color-coded intent pills --}}
                <div class="flex gap-2 mt-4 flex-wrap">
                    <span class="text-xs font-medium bg-orange-50 text-orange-700 px-3 py-1.5 rounded-lg border border-orange-100">
                        🍜 {{ $intent['FoodType'] }}
                    </span>
                    <span class="text-xs font-medium bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-lg border border-emerald-100">
                        💰 Up to {{ $intent['MaxPrice'] }}
                    </span>
                    <span class="text-xs font-medium bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg border border-blue-100">
                        📍 Within {{ number_format($intent['MaxDistance'] / 1000, 1) }}km
                    </span>
                    @if($intent['Occasion'] !== 'any')
                    <span class="text-xs font-medium bg-purple-50 text-purple-700 px-3 py-1.5 rounded-lg border border-purple-100">
                        🎭 {{ ucfirst($intent['Occasion']) }}
                    </span>
                    @endif
                    @if($intent['VisitTime'] !== 'now')
                    <span class="text-xs font-medium bg-rose-50 text-rose-700 px-3 py-1.5 rounded-lg border border-rose-100">
                        🕐 {{ ucfirst($intent['VisitTime']) }}
                    </span>
                    @endif
                </div>
            </div>

            {{-- Relaxed Warning --}}
            @if($relaxed)
            <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-start gap-3"
                 style="background:#FFFBEB; color:#D97706; border:1px solid #FDE68A">
                <span>⚠️</span>
                <p>No exact match found nearby. Showing the best rated alternatives instead.</p>
            </div>
            @endif

            {{-- Top Pick --}}
            <div class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-bold text-emerald-600 uppercase tracking-widest">Top Pick</p>
                </div>
                
                {{-- Highlighted Card --}}
                <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-6 relative overflow-hidden transition-all"
                     style="border: 2px solid #10B981; box-shadow: 0 4px 20px rgba(16, 185, 129, 0.08);"
                     x-data="{ showMath: false }">
                    
                    <div class="flex flex-col sm:flex-row gap-5">
                        
                        {{-- Top Pick Image (Hero-ish) --}}
                        <div class="relative shrink-0 w-full sm:w-40 h-48 sm:h-auto rounded-xl overflow-hidden bg-neutral-100">
                            {{-- Placeholder image, replace src with $topPick['image_url'] later --}}
                            <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600&q=80" 
                                 alt="{{ $topPick['name'] }}" 
                                 class="absolute inset-0 w-full h-full object-cover">
                            
                            {{-- Floating #1 Badge --}}
                            <div class="absolute top-3 left-3 flex items-center justify-center w-7 h-7 bg-emerald-600 text-white text-xs font-bold rounded-full shadow-md">
                                1
                            </div>
                        </div>

                        {{-- Top Pick Details --}}
                        <div class="flex-1 flex flex-col justify-between py-1">
                            <div>
                                <h3 class="text-xl font-bold text-neutral-900 leading-tight">{{ $topPick['name'] }}</h3>
                                
                                <div class="flex flex-wrap items-center gap-3 mt-3">
                                    <span class="flex items-center gap-1 text-sm font-medium text-neutral-700 bg-neutral-100 px-2.5 py-1 rounded-lg">⭐ {{ $topPick['rating'] }}</span>
                                    <span class="text-sm font-medium text-neutral-500">📍 {{ number_format($topPick['distance'], 0) }}m</span>
                                    <span class="text-sm font-medium text-neutral-400">•</span>
                                    <span class="text-sm font-medium text-neutral-500 font-mono tracking-widest">{{ str_repeat('$', $topPick['price_level']) }}</span>
                                </div>

                                @if($topPick['time_warning'] ?? null)
                                <div class="mt-3 inline-flex">
                                    <span class="text-xs px-3 py-1 rounded-lg font-bold border" style="background:#FEF3C7; color:#D97706; border-color:#FDE68A">
                                        ⚠ {{ $topPick['time_warning'] }}
                                    </span>
                                </div>
                                @endif
                            </div>
                            
                            {{-- Score Badge & CTA --}}
                            <div class="flex items-end justify-between border-t border-neutral-100 pt-4 mt-4">
                                <div class="text-left">
                                    <p class="text-3xl font-black text-emerald-600 leading-none">{{ round($topPick['saw_score'] * 100) }}%</p>
                                    <p class="text-[10px] font-bold text-neutral-400 uppercase tracking-widest mt-1">Match</p>
                                </div>
                                <a href="https://maps.google.com/?q={{ urlencode($topPick['name']) }}" target="_blank"
                                   class="bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-semibold px-4 py-2 rounded-xl transition-colors">
                                    Directions
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Toggle math --}}
                    <div class="mt-5 pt-4 border-t border-neutral-100">
                        <button @click="showMath = !showMath" class="w-full flex items-center justify-between text-xs font-bold text-neutral-400 hover:text-emerald-600 transition-colors uppercase tracking-widest">
                            <span>Decision Logic (SAW)</span>
                            <span x-text="showMath ? 'Hide' : 'View'"></span>
                        </button>

                        {{-- Beautiful Data Grid for Math --}}
                        <div x-show="showMath" x-collapse.duration.300ms class="mt-4">
                            @php $b = $topPick['criteria_breakdown']; @endphp
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <div class="bg-neutral-50 rounded-xl p-3 border border-neutral-100">
                                    <p class="text-[10px] text-neutral-400 uppercase font-bold mb-1">Distance</p>
                                    <p class="text-sm font-mono font-bold text-neutral-700">{{ number_format($b['C1_distance'] * 0.35, 4) }}</p>
                                </div>
                                <div class="bg-neutral-50 rounded-xl p-3 border border-neutral-100">
                                    <p class="text-[10px] text-neutral-400 uppercase font-bold mb-1">Food Match</p>
                                    <p class="text-sm font-mono font-bold text-neutral-700">{{ number_format($b['C2_food_match'] * 0.30, 4) }}</p>
                                </div>
                                <div class="bg-neutral-50 rounded-xl p-3 border border-neutral-100">
                                    <p class="text-[10px] text-neutral-400 uppercase font-bold mb-1">Rating</p>
                                    <p class="text-sm font-mono font-bold text-neutral-700">{{ number_format($b['C3_rating'] * 0.20, 4) }}</p>
                                </div>
                                <div class="bg-neutral-50 rounded-xl p-3 border border-neutral-100">
                                    <p class="text-[10px] text-neutral-400 uppercase font-bold mb-1">Price</p>
                                    <p class="text-sm font-mono font-bold text-neutral-700">{{ number_format($b['C4_price_level'] * 0.15, 4) }}</p>
                                </div>
                            </div>
                            <div class="flex justify-between items-center mt-3 px-1">
                                <p class="text-[10px] text-neutral-400">Raw Rating: {{ $b['raw_rating'] }} ({{ $b['review_count'] }} reviews)</p>
                                <p class="text-[10px] font-bold text-neutral-500 uppercase">Final: {{ $topPick['saw_score'] }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Alternatives --}}
            @if(!empty($alternatives))
            <div>
                <p class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4">Other Good Options</p>
                <div class="space-y-3">
                    @foreach($alternatives as $alt)
                    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-4 hover:border-neutral-300 transition-colors"
                         x-data="{ showMath: false }">
                        
                        <div class="flex gap-4">
                            {{-- Thumbnail Image --}}
                            <img src="https://images.unsplash.com/photo-1552566626-52f8b828add9?w=300&q=80" 
                                 alt="{{ $alt['name'] }}" 
                                 class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl object-cover shrink-0 bg-neutral-100">

                            <div class="flex-1 flex flex-col sm:flex-row justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-bold text-neutral-400">#{{ $alt['rank'] }}</span>
                                        <h3 class="text-base font-bold text-neutral-800">{{ $alt['name'] }}</h3>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 mt-1">
                                        <span class="text-xs font-medium text-neutral-700 bg-neutral-100 px-2 py-0.5 rounded-md">⭐ {{ $alt['rating'] }}</span>
                                        <span class="text-xs font-medium text-neutral-500">📍 {{ number_format($alt['distance'], 0) }}m</span>
                                        <span class="text-xs font-medium text-neutral-400">•</span>
                                        <span class="text-xs font-medium text-neutral-500 font-mono tracking-widest">{{ str_repeat('$', $alt['price_level']) }}</span>
                                    </div>
                                    @if($alt['time_warning'] ?? null)
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-bold mt-2 inline-block border"
                                          style="background:#FEF3C7; color:#D97706; border-color:#FDE68A">
                                        ⚠ {{ $alt['time_warning'] }}
                                    </span>
                                    @endif
                                </div>
                                
                                <div class="flex items-center justify-between sm:justify-end sm:flex-col gap-2 border-t sm:border-t-0 border-neutral-100 pt-3 sm:pt-0">
                                    <div class="text-left sm:text-right">
                                        <p class="text-lg font-bold text-neutral-700">{{ round($alt['saw_score'] * 100) }}%</p>
                                    </div>
                                    <button @click="showMath = !showMath" class="text-[10px] font-bold text-neutral-400 uppercase tracking-widest hover:text-neutral-600 bg-neutral-50 px-3 py-1.5 rounded-lg border border-neutral-200 shrink-0">
                                        <span x-text="showMath ? 'Hide' : 'Math'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Alternative Math Grid --}}
                        <div x-show="showMath" x-collapse.duration.300ms class="mt-4 pt-3 border-t border-neutral-100">
                            @php $b = $alt['criteria_breakdown']; @endphp
                            <div class="grid grid-cols-4 gap-2">
                                <div class="bg-neutral-50 rounded-lg p-2 text-center">
                                    <p class="text-[9px] text-neutral-400 uppercase font-bold mb-0.5">Distance</p>
                                    <p class="text-xs font-mono font-bold text-neutral-600">{{ number_format($b['C1_distance'] * 0.35, 4) }}</p>
                                </div>
                                <div class="bg-neutral-50 rounded-lg p-2 text-center">
                                    <p class="text-[9px] text-neutral-400 uppercase font-bold mb-0.5">F. Match</p>
                                    <p class="text-xs font-mono font-bold text-neutral-600">{{ number_format($b['C2_food_match'] * 0.30, 4) }}</p>
                                </div>
                                <div class="bg-neutral-50 rounded-lg p-2 text-center">
                                    <p class="text-[9px] text-neutral-400 uppercase font-bold mb-0.5">Rate</p>
                                    <p class="text-xs font-mono font-bold text-neutral-600">{{ number_format($b['C3_rating'] * 0.20, 4) }}</p>
                                </div>
                                <div class="bg-neutral-50 rounded-lg p-2 text-center">
                                    <p class="text-[9px] text-neutral-400 uppercase font-bold mb-0.5">Price</p>
                                    <p class="text-xs font-mono font-bold text-neutral-600">{{ number_format($b['C4_price_level'] * 0.15, 4) }}</p>
                                </div>
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
