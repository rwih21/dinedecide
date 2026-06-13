<x-app-layout>
<div class="min-h-screen px-4 py-10" style="background:#F9F9F8">
    <div class="max-w-2xl mx-auto">

        {{-- Header --}}
        <div class="mb-8">
            <a x-ref="topButton" href="{{ route('restaurants.index') }}"
                   class="inline-flex items-center gap-2 text-sm font-medium text-neutral-500 hover:text-emerald-600 transition-colors bg-white px-4 py-2 rounded-xl border border-neutral-200 shadow-sm w-fit">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                    Back to search
                </a>

            <div class="mt-5">
                <p class="text-neutral-400 text-xs font-bold uppercase tracking-widest">Exploring</p>
                <h2 class="text-2xl font-bold text-neutral-900 mt-1 leading-tight">All Nearby Places</h2>
                <p class="text-sm text-neutral-400 mt-1">
                    {{ count($places) }} places found within 3km
                    {{-- @if($fromCache)
                        <span class="ml-2 inline-flex items-center gap-1 text-xs font-semibold px-2 py-0.5 rounded-full"
                              style="background:#F0FDF4; color:#059669; border:1px solid #BBF7D0">
                            ⚡ Instant
                        </span>
                    @endif --}}
                </p>
            </div>
        </div>

        {{-- Promoted Places Carousel --}}
        @if(isset($promotedPlaces) && $promotedPlaces)
        <div class="mb-6">
            <div class="bg-white rounded-2xl p-4 flex gap-4 relative"
                style="border:1.5px solid #E5E5E5; box-shadow: 0 2px 12px rgba(0,0,0,0.04)">

                <span class="absolute top-3 right-3 text-[9px] font-semibold uppercase tracking-wider px-2 py-0.5 rounded-full"
                    style="background:#F5F5F4; color:#A3A3A3; border:1px solid #E7E7E7">
                    Sponsored
                </span>

                <img src="{{ $promotedPlaces->photo_url ?? 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=300&q=80' }}"
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

        {{-- Filter bar --}}
        <div x-data="{ active: 'all' }">

            <div class="flex overflow-x-auto gap-2 no-scrollbar whitespace-nowrap mb-6">
                <button @click="active = 'all'"
                        class="text-xs px-3 py-1.5 rounded-full border font-semibold transition-all duration-150"
                        :style="active === 'all'
                            ? 'background:#059669; color:white; border-color:#059669'
                            : 'background:white; color:#525252; border-color:#E5E5E5'">
                    All
                </button>

                @php
                    $allTypes = collect($places)
                        ->flatMap(fn($p) => $p['types'])
                        ->unique()
                        ->sort()
                        ->values();
                @endphp

                @foreach($allTypes as $type)
                <button @click="active = '{{ $type }}'"
                        class="text-xs px-3 py-1.5 rounded-full border font-semibold transition-all duration-150 capitalize"
                        :style="active === '{{ $type }}'
                            ? 'background:#059669; color:white; border-color:#059669'
                            : 'background:white; color:#525252; border-color:#E5E5E5'">
                    {{ \Illuminate\Support\Str::headline($type) }}
                </button>
                @endforeach
            </div>

            {{-- Place cards --}}
            <div class="space-y-3">
                @foreach($places as $place)
                <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-4 hover:border-neutral-300 transition-colors"
                     x-show="active === 'all' || {{ json_encode($place['types']) }}.includes(active)"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0">

                    <div class="flex gap-4">
                        {{-- Photo --}}
                        <img src="{{ $place['photo_url'] ?? 'https://images.unsplash.com/photo-1552566626-52f8b828add9?w=300&q=80' }}"
                             alt="{{ $place['name'] }}"
                             class="w-20 h-20 rounded-xl object-cover shrink-0 bg-neutral-100">

                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-neutral-800 truncate">{{ $place['name'] }}</h3>
                            <p class="text-xs text-neutral-400 mt-0.5 truncate">{{ $place['vicinity'] }}</p>

                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <span class="text-xs font-medium text-neutral-700 bg-neutral-100 px-2 py-0.5 rounded-md">
                                    ⭐ {{ $place['rating'] }}
                                </span>
                                <span class="text-xs font-medium text-neutral-500">
                                    📍 {{ number_format($place['distance'], 0) }}m
                                </span>
                                <span class="text-xs font-medium text-emerald-600">
                                    {{ $place['price_display'] }}
                                </span>
                            </div>

                            {{-- Type tags --}}
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($place['types'] as $type)
                                <span class="text-[10px] px-2 py-0.5 rounded-full capitalize font-medium"
                                      style="background:#F0F0EF; color:#525252">
                                    {{ \Illuminate\Support\Str::headline($type) }}
                                </span>
                                @endforeach
                            </div>
                        </div>

                        {{-- Open/closed status --}}
                        <div class="shrink-0 text-right">
                            @if(isset($place['open_now']))
                                @if($place['open_now'])
                                    <span class="text-[10px] font-bold" style="color:#059669">● Open</span>
                                @else
                                    <span class="text-[10px] font-bold" style="color:#EF4444">● Closed</span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

        </div>

        {{-- Search CTA --}}
        <div class="mt-10 pt-6 text-center" style="border-top:1px solid #E5E5E5">
            <p class="text-sm text-neutral-400 mb-3">Want a personalised recommendation instead?</p>
            <a href="{{ route('restaurants.index') }}"
               class="inline-flex items-center gap-2 text-sm font-semibold text-white px-6 py-3 rounded-xl transition-all duration-200 active:scale-95"
               style="background:#059669">
                Start searching
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
        </div>

    </div>
</div>
<style>
    /* Hide scrollbar for Chrome, Safari and Opera */
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }

    /* Hide scrollbar for IE, Edge and Firefox */
    .no-scrollbar {
        -ms-overflow-style: none;  /* IE and Edge */
        scrollbar-width: none;  /* Firefox */
    }
</style>
</x-app-layout>