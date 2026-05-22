<x-app-layout>
    <div class="min-h-screen relative flex flex-col items-center px-4 py-8" style="background:#F9F9F8">
        
        <div class="w-full max-w-md w-full" x-data="{ showMath: false }">

            {{-- Header --}}
            <div class="flex items-center justify-between mb-6">
                <a href="{{ route('restaurants.index') }}" class="text-neutral-400 hover:text-neutral-600 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </a>
                <span class="text-xs font-bold uppercase tracking-widest text-emerald-600">Top Pick</span>
                <div class="w-6"></div> {{-- Spacer for centering --}}
            </div>

            {{-- Relaxed Match Warning --}}
            @if($relaxed)
            <div class="mb-4 px-4 py-3 rounded-xl text-xs font-medium" style="background:#FFFBEB; color:#D97706; border:1px solid #FDE68A">
                No exact match found nearby — showing top rated options instead.
            </div>
            @endif

            {{-- Main Recommendation Card --}}
            <div class="bg-white rounded-3xl overflow-hidden mb-8 shadow-sm" style="box-shadow: 0 4px 24px rgba(0,0,0,0.06); border: 1.5px solid #F0F0EF">
                
                {{-- Image Hero --}}
                <div class="relative h-48 bg-gray-200">
                    {{-- Note: Ensure your controller passes an image_url, or use a placeholder --}}
                    <img src="{{ $topPick['image_url'] ?? 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=800&q=80' }}" 
                         alt="{{ $topPick['name'] }}" 
                         class="w-full h-full object-cover" />
                    
                    @if($topPick['time_warning'] ?? null)
                    <div class="absolute top-4 right-4 bg-white/90 backdrop-blur px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider text-amber-600 shadow-sm">
                        {{ $topPick['time_warning'] }}
                    </div>
                    @endif
                </div>

                {{-- Card Content --}}
                <div class="p-6">
                    <div class="flex justify-between items-start mb-2">
                        <h3 class="text-2xl font-bold tracking-tight text-gray-900">{{ $topPick['name'] }}</h3>
                        <div class="flex items-center gap-1 bg-emerald-50 text-emerald-700 px-2 py-1 rounded-lg text-xs font-bold">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                            {{ $topPick['rating'] }}
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-4 text-sm text-neutral-500 mb-4">
                        <div class="flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 11 22 2 13 21 11 13 3 11"></polygon></svg>
                            {{ number_format($topPick['distance'], 0) }}m
                        </div>
                        <div class="font-mono text-neutral-400">{{ str_repeat('$', $topPick['price_level']) }}</div>
                        <div class="ml-auto flex items-center gap-1.5 bg-orange-50 text-orange-700 px-3 py-1 rounded-full text-[10px] font-bold border border-orange-100">
                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect><line x1="8" y1="6" x2="16" y2="6"></line><line x1="16" y1="14" x2="16" y2="14.01"></line><line x1="16" y1="18" x2="16" y2="18.01"></line><line x1="12" y1="14" x2="12" y2="14.01"></line><line x1="12" y1="18" x2="12" y2="18.01"></line><line x1="8" y1="14" x2="8" y2="14.01"></line><line x1="8" y1="18" x2="8" y2="18.01"></line></svg>
                            SAW: {{ round($topPick['saw_score'] * 100) }}%
                        </div>
                    </div>

                    {{-- Description (Add to controller if you don't have it) --}}
                    <p class="text-neutral-600 text-sm leading-relaxed mb-6">
                        {{ $topPick['description'] ?? 'A highly rated spot nearby that perfectly matches your preferences.' }}
                    </p>

                    {{-- SAW Calculation Breakdown --}}
                    <div class="mb-6 border-t border-neutral-100 pt-4">
                        <button @click="showMath = !showMath" class="flex items-center justify-between w-full text-[10px] font-bold uppercase tracking-widest text-neutral-400 hover:text-emerald-600 transition-colors">
                            <span>Decision Logic</span>
                            <svg x-show="!showMath" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>
                            <svg x-show="showMath" x-cloak xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"></polyline></svg>
                        </button>
                        
                        <div x-show="showMath" x-transition.opacity.duration.300ms class="mt-3 space-y-2">
                            @php $b = $topPick['criteria_breakdown']; @endphp
                            <div class="grid grid-cols-2 gap-2">
                                <div class="bg-neutral-50 p-2 rounded-lg">
                                    <div class="text-[8px] text-neutral-400 uppercase font-bold">C1: Distance (35%)</div>
                                    <div class="text-xs font-mono font-bold">{{ number_format($b['C1_distance'] * 0.35, 4) }}</div>
                                </div>
                                <div class="bg-neutral-50 p-2 rounded-lg">
                                    <div class="text-[8px] text-neutral-400 uppercase font-bold">C2: Match (30%)</div>
                                    <div class="text-xs font-mono font-bold">{{ number_format($b['C2_food_match'] * 0.30, 4) }}</div>
                                </div>
                                <div class="bg-neutral-50 p-2 rounded-lg">
                                    <div class="text-[8px] text-neutral-400 uppercase font-bold">C3: Rating (20%)</div>
                                    <div class="text-xs font-mono font-bold">{{ number_format($b['C3_rating'] * 0.20, 4) }}</div>
                                </div>
                                <div class="bg-neutral-50 p-2 rounded-lg">
                                    <div class="text-[8px] text-neutral-400 uppercase font-bold">C4: Price (15%)</div>
                                    <div class="text-xs font-mono font-bold">{{ number_format($b['C4_price_level'] * 0.15, 4) }}</div>
                                </div>
                            </div>
                            <div class="text-[9px] text-neutral-400 italic">
                                * Raw Rating: {{ $b['raw_rating'] }} · Reviews: {{ $b['review_count'] }} (Adj. {{ $b['adjusted_rating'] }})
                            </div>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex gap-3">
                        <button class="flex-1 bg-emerald-600 text-white py-4 rounded-2xl font-bold text-sm shadow-lg shadow-emerald-600/20 hover:bg-emerald-700 transition-all flex items-center justify-center gap-2">
                            Let's go here
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </button>
                        <a href="https://maps.google.com/?q={{ urlencode($topPick['name']) }}" target="_blank" class="w-14 h-14 bg-neutral-100 text-neutral-600 rounded-2xl flex items-center justify-center hover:bg-neutral-200 transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="21"></line><line x1="15" y1="3" x2="15" y2="21"></line></svg>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Alternatives --}}
            @if(!empty($alternatives))
            <div class="mb-4">
                <h4 class="text-xs font-bold uppercase tracking-widest text-neutral-400 mb-4">Other Options</h4>
                <div class="space-y-3">
                    @foreach($alternatives as $alt)
                    <div class="bg-white p-4 rounded-2xl border border-neutral-100 flex items-center gap-4 hover:border-emerald-500/30 cursor-pointer transition-all group" style="box-shadow: 0 2px 10px rgba(0,0,0,0.02)">
                        {{-- Alt Image Placeholder --}}
                        <img src="{{ $alt['image_url'] ?? 'https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=200&q=80' }}" 
                             alt="{{ $alt['name'] }}" 
                             class="w-16 h-16 rounded-xl object-cover" />
                        
                        <div class="flex-1">
                            <div class="flex justify-between items-center">
                                <h5 class="font-bold text-sm text-gray-900 group-hover:text-emerald-600 transition-colors">{{ $alt['name'] }}</h5>
                                <span class="text-[9px] font-mono font-bold text-orange-600 bg-orange-50 px-1.5 py-0.5 rounded">SAW: {{ round($alt['saw_score'] * 100) }}%</span>
                            </div>
                            <div class="flex items-center gap-3 text-[10px] font-medium text-neutral-400 mt-1 uppercase tracking-wider">
                                <span class="flex items-center gap-0.5">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="currentColor" stroke="none" class="text-emerald-500"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                    {{ $alt['rating'] }}
                                </span>
                                <span>{{ number_format($alt['distance'], 0) }}m</span>
                                <span class="font-mono">{{ str_repeat('$', $alt['price_level']) }}</span>
                            </div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-neutral-300 group-hover:text-emerald-500 transition-colors"><polyline points="9 18 15 12 9 6"></polyline></svg>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            
            <a href="{{ route('restaurants.index') }}" class="block mt-6 mb-10 text-center text-xs font-medium text-neutral-400 hover:text-emerald-600 transition-colors">
                Try a different search
            </a>

        </div>
    </div>
</x-app-layout>