<x-app-layout>
    {{-- 1. THE SINGLE MAIN WRAPPER --}}
    {{-- All Alpine state lives here so both the top button and bottom sticky bar can talk to each other --}}
    <div x-data="{ 
        showSticky: false,
        initObserver() {
            this.$nextTick(() => {
                let observer = new IntersectionObserver((entries) => {
                    this.showSticky = !entries[0].isIntersecting;
                }, { threshold: 0 }); 
                
                if (this.$refs.topButton) {
                    observer.observe(this.$refs.topButton);
                }
            });
        }
    }" 
    x-init="initObserver()"
    class="min-h-screen px-4 pt-10 pb-[100px] relative" style="background:#F9F9F8">
        
        <div class="max-w-2xl mx-auto">

            {{-- Back + query header --}}
            <div class="mb-8">
                <a x-ref="topButton" href="{{ route('restaurants.index') }}"
                   class="inline-flex items-center gap-2 text-sm font-medium text-neutral-500 hover:text-emerald-600 transition-colors bg-white px-4 py-2 rounded-xl border border-neutral-200 shadow-sm w-fit">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    New search
                </a>
                
                <div class="mt-5">
                    <p class="text-neutral-400 text-xs font-bold uppercase tracking-widest">Results for</p>
                    <h2 class="text-2xl font-bold text-neutral-900 mt-1 leading-tight">"{{ $rawQuery }}"</h2>
                </div>

                {{-- Color-coded intent pills --}}
                <div class="flex gap-2 mt-4 flex-wrap">
                    @if(!empty($intent['FoodSearch']))
                    <span class="text-xs font-medium bg-orange-50 text-orange-700 px-3 py-1.5 rounded-lg border border-orange-100">
                        🍜 {{ $intent['FoodSearch'] }}
                    </span>
                    @endif
                    @if($intent['MaxBudget'] > 0)
                    <span class="text-xs font-medium bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-lg border border-emerald-100">
                        💰 Up to {{ number_format($intent['MaxBudget'], 0, ',', '.') }}
                    </span>
                    @endif
                    <span class="text-xs font-medium bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg border border-blue-100">
                        📍 Within {{ number_format($intent['MaxDistance'] / 1000, 1) }}km
                    </span>
                    {{-- @if($intent['VisitTime'] !== 'now')
                    <span class="text-xs font-medium bg-rose-50 text-rose-700 px-3 py-1.5 rounded-lg border border-rose-100">
                        🕐 {{ ucfirst($intent['VisitTime']) }}
                    </span>
                    @endif --}}
                </div>
            </div>

            {{-- Relaxed Warning --}}
            @if($relaxed)
            <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium flex items-start gap-3"
                 style="background:#FFFBEB; color:#D97706; border:1px solid #FDE68A">
                <span>⚠️</span>
                <p>No exact match found nearby. Showing the best rated alternative instead.</p>
            </div>
            @endif

            {{-- Promoted Places Carousel --}}
            @if(isset($promotedPlaces) && $promotedPlaces !== null)
            <div class="mb-6">
                <div class="bg-white rounded-2xl p-4 flex gap-4 relative"
                    style="border:1.5px solid #E5E5E5; box-shadow: 0 2px 12px rgba(0,0,0,0.04)">

                    <span class="absolute top-3 right-3 text-[9px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full"
                        style="background:#F5F5F4; color:#A3A3A3; border:1px solid #E7E7E7">
                        Sponsored
                    </span>

                    <img src="{{ $promotedPlaces->photo_url }}"
                        alt="{{ $promotedPlaces->name }}"
                        class="w-20 h-20 rounded-xl object-cover shrink-0 bg-neutral-100">

                    <div class="flex-1 min-w-0">
                        <h3 class="font-bold text-neutral-800 pr-16 truncate">{{ $promotedPlaces->name }}</h3>

                        @if($promotedPlaces->description)
                        <p class="text-xs text-neutral-400 mt-0.5 line-clamp-2">{{ $promotedPlaces->description }}</p>
                        @endif

                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <span class="text-xs font-medium text-emerald-600">{{ $promotedPlaces->price_display }}</span>
                            @if($promotedPlaces->address)
                            <span class="text-xs text-neutral-400 truncate">📍 {{ $promotedPlaces->address }}</span>
                            @endif
                        </div>

                        <div class="flex gap-2 mt-3">
                            @if($promotedPlaces->gmaps_url)
                            <a href="{{ $promotedPlaces->gmaps_url }}" target="_blank"
                            class="text-[11px] font-semibold text-white px-3 py-1.5 rounded-lg"
                            style="background:#059669">Directions</a>
                            @endif
                            @if($promotedPlaces->whatsapp)
                            <a href="https://wa.me/{{ $promotedPlaces->whatsapp }}" target="_blank"
                            class="text-[11px] font-semibold px-3 py-1.5 rounded-lg border"
                            style="color:#059669; border-color:#BBF7D0; background:#F0FDF4">WhatsApp</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            @endif

            <div class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-xs font-bold text-emerald-600 uppercase tracking-widest">Your Recommendation</p>
                </div>
                
                <div class="bg-white rounded-2xl shadow-sm p-4 sm:p-6 relative overflow-hidden transition-all"
                     style="border: 2px solid #10B981; box-shadow: 0 4px 20px rgba(16, 185, 129, 0.08);"
                     x-data="{ showMath: false }">
                    
                    <div class="flex flex-col sm:flex-row gap-5">
                        <div class="relative shrink-0 w-full sm:w-40 h-48 sm:h-auto rounded-xl overflow-hidden bg-neutral-100">
                            <img src="{{ $topPick['photo_url'] ?? 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=600&q=80' }}" 
                                 alt="{{ $topPick['name'] }}" 
                                 class="absolute inset-0 w-full h-full object-cover">
                        </div>

                        <div class="flex-1 flex flex-col justify-between py-1">
                            <div>
                                <h3 class="text-xl font-bold text-neutral-900 leading-tight">{{ $topPick['name'] }}</h3>
                                
                                <div class="flex flex-wrap items-center gap-3 mt-3">
                                    <span class="flex items-center gap-1 text-sm font-medium text-neutral-700 bg-neutral-100 px-2.5 py-1 rounded-lg">⭐ {{ $topPick['rating'] }}</span>
                                    <span class="text-sm font-medium text-neutral-500">📍 {{ number_format($topPick['distance'], 0) }}m</span>
                                    <span class="text-sm font-medium text-neutral-400">•</span>
                                    <span class="text-sm font-medium text-emerald-600">{{ $topPick['price_display'] }}</span>
                                </div>

                                @if(!empty($topPick['price_comment']))
                                <div class="mt-3 inline-flex">
                                    <span class="text-xs px-3 py-1 rounded-lg font-bold border 
                                        {{ $topPick['price_comment'] === 'Affordable' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                                          ($topPick['price_comment'] === 'Very expensive' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-700 border-amber-200') }}">
                                        {{ $topPick['price_comment'] === 'Affordable' ? '✓' : '⚠' }} {{ $topPick['price_comment'] }}
                                    </span>
                                </div>
                                @endif

                                @if($topPick['time_warning'] ?? null)
                                <div class="mt-2 inline-flex">
                                    <span class="text-xs px-3 py-1 rounded-lg font-bold border" style="background:#FEF3C7; color:#D97706; border-color:#FDE68A">
                                        ⚠ {{ $topPick['time_warning'] }}
                                    </span>
                                </div>
                                @endif
                            </div>
                            
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

                    <div class="mt-5 pt-4 border-t border-neutral-100">
                        <button @click="showMath = !showMath" class="w-full flex items-center justify-between text-xs font-bold text-neutral-400 hover:text-emerald-600 transition-colors uppercase tracking-widest">
                            <span>Decision Logic (SAW)</span>
                            <span x-text="showMath ? 'Hide' : 'View'"></span>
                        </button>

                        <div x-show="showMath" x-collapse.duration.300ms class="mt-4 p-4 bg-neutral-50 rounded-xl border border-neutral-100">
                            @php 
                                $b = $topPick['criteria_breakdown']; 
                                $getW = function($val, $weight) { return min(100, max(0, ($val * $weight) * 100)); };
                            @endphp
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-neutral-500 uppercase tracking-widest mb-1">
                                        <span>Distance</span>
                                        <span>{{ number_format($b['C1_distance'] * 100, 0) }}% Match</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $getW($b['C1_distance'], 1) }}%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-neutral-500 uppercase tracking-widest mb-1">
                                        <span>Craving Match</span>
                                        <span>{{ number_format($b['C2_food_match'] * 100, 0) }}% Match</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $getW($b['C2_food_match'], 1) }}%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-neutral-500 uppercase tracking-widest mb-1">
                                        <span>Quality (Rating)</span>
                                        <span>{{ number_format($b['C3_rating'] * 100, 0) }}% Match</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $getW($b['C3_rating'], 1) }}%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-neutral-500 uppercase tracking-widest mb-1">
                                        <span>Budget</span>
                                        <span>{{ number_format($b['C4_price'] * 100, 0) }}% Match</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $getW($b['C4_price'], 1) }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Alternatives --}}
            @if(!empty($alternatives))
            <div class="mt-8">
                <p class="text-xs font-bold text-neutral-400 uppercase tracking-widest mb-4">Other Good Options</p>
                <div class="space-y-3">
                    @foreach($alternatives as $alt)
                    <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-4 hover:border-neutral-300 transition-colors"
                         x-data="{ showMath: false }">
                        
                        <div class="flex gap-4">
                            <img src="{{ $alt['photo_url'] ?? 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=300&q=80' }}" 
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
                                        <span class="text-xs font-medium text-emerald-600">{{ $alt['price_display'] }}</span>
                                    </div>
                                    
                                    @if(!empty($alt['price_comment']))
                                    <span class="text-[10px] px-2 py-0.5 rounded-md font-bold mt-2 inline-block border 
                                        {{ $alt['price_comment'] === 'Affordable' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 
                                          ($alt['price_comment'] === 'Very expensive' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-700 border-amber-200') }}">
                                        {{ $alt['price_comment'] }}
                                    </span>
                                    @endif
                                </div>
                                
                                <div class="flex items-center justify-between sm:justify-end sm:flex-col gap-2 border-t sm:border-t-0 border-neutral-100 pt-3 sm:pt-0">
                                    <div class="text-left sm:text-right">
                                        <p class="text-lg font-bold text-neutral-700">{{ round($alt['saw_score'] * 100) }}%</p>
                                    </div>
                                    <button @click="showMath = !showMath" class="text-[10px] font-bold text-neutral-400 uppercase tracking-widest hover:text-neutral-600 bg-neutral-50 px-3 py-1.5 rounded-lg border border-neutral-200 shrink-0">
                                        <span x-text="showMath ? 'Hide' : 'View'"></span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Alternative Math Grid --}}
                        <div x-show="showMath" x-collapse.duration.300ms class="mt-4 p-4 bg-neutral-50 rounded-xl border border-neutral-100">
                            @php 
                                $b = $alt['criteria_breakdown']; 
                                $getW = function($val, $weight) { return min(100, max(0, ($val * $weight) * 100)); };
                            @endphp
                            <div class="space-y-3">
                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-neutral-500 uppercase tracking-widest mb-1">
                                        <span>Distance</span>
                                        <span>{{ number_format($b['C1_distance'] * 100, 0) }}% Match</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $getW($b['C1_distance'], 1) }}%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-neutral-500 uppercase tracking-widest mb-1">
                                        <span>Craving Match</span>
                                        <span>{{ number_format($b['C2_food_match'] * 100, 0) }}% Match</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $getW($b['C2_food_match'], 1) }}%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-neutral-500 uppercase tracking-widest mb-1">
                                        <span>Quality (Rating)</span>
                                        <span>{{ number_format($b['C3_rating'] * 100, 0) }}% Match</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $getW($b['C3_rating'], 1) }}%"></div>
                                    </div>
                                </div>

                                <div>
                                    <div class="flex justify-between text-[10px] font-bold text-neutral-500 uppercase tracking-widest mb-1">
                                        <span>Budget</span>
                                        <span>{{ number_format($b['C4_price'] * 100, 0) }}% Match</span>
                                    </div>
                                    <div class="w-full h-1.5 bg-neutral-200 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $getW($b['C4_price'], 1) }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <div class="mt-4 text-center">
                <a href="{{ route('restaurants.browse') }}"
                   class="inline-flex items-center justify-center w-full sm:w-auto gap-2 text-sm font-bold text-neutral-500 bg-neutral-100 hover:bg-neutral-200 px-5 py-3 rounded-xl transition-colors">
                    View all other nearby places
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>

        </div> 
        {{-- ^ End of max-w-2xl --}}

        {{-- 2. THIS IS THE FIXED STICKY BAR, NOW SAFELY INSIDE THE MAIN X-DATA WRAPPER --}}
        <div x-show="showSticky" 
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="opacity-0 translate-y-8"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-8"
             x-cloak
             class="fixed bottom-0 left-0 right-0 p-4 z-50 pointer-events-none" 
             style="padding-bottom: max(1rem, env(safe-area-inset-bottom));">
             
            <div class="max-w-2xl mx-auto flex justify-center pointer-events-auto">
                <a href="{{ route('restaurants.index') }}" 
                class="flex items-center gap-3 px-6 py-3.5 bg-neutral-900 text-white rounded-full shadow-[0_8px_30px_rgba(0,0,0,0.2)] hover:scale-105 transition-transform active:scale-95">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                    <span class="text-sm font-bold tracking-wide">New Search</span>
                </a>
            </div>
        </div>

    </div> 
    {{-- ^ End of min-h-screen main wrapper --}}

    <style>
        [x-cloak] { display: none !important; }
    </style>
</x-app-layout>