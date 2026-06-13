<x-app-layout>
<div class="min-h-screen relative overflow-hidden flex flex-col items-center px-6 py-8" style="background:#F9F9F8">

    {{-- Background blurs --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full opacity-20"
             style="background:radial-gradient(circle, #059669, transparent 70%)"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full opacity-20"
             style="background:radial-gradient(circle, #F59E0B, transparent 70%)"></div>
    </div>

    <div class="relative w-full max-w-md flex flex-col min-h-screen py-8" x-data="searchForm()" x-cloak>

        {{-- INPUT SCREEN --}}
        <div x-show="screen === 'input'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-5"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-5">

            {{-- Headline --}}
            <div class="mb-8 fade-up-delay-1">
                <h1 class="font-bold leading-tight" style="font-size:36px; color:#1A1A1A; letter-spacing:-0.02em">
                    Where should<br>we eat
                    <em style="font-family: Georgia, serif; font-style: italic; color:#059669">today?</em>
                </h1>
                <p class="mt-2 text-sm" style="color:#525252">Choose how you want to decide.</p>
            </div>

            {{-- Location Selector --}}
            {{-- Location Trigger Pill --}}
            <div class="mb-6 fade-up-delay-2 flex justify-center" x-data="locationPicker()">
                <input type="hidden" id="global-latitude" x-model="lat">
                <input type="hidden" id="global-longitude" x-model="lng">

                <button type="button" 
                        @click="openMap()"
                        class="inline-flex items-center gap-2 bg-white px-5 py-2.5 rounded-full shadow-sm border border-neutral-200 text-xs font-semibold text-neutral-700 active:scale-95 transition-all">
                    <span>📍</span>
                    <span class="max-w-[180px] truncate" x-text="label"></span>
                    <svg class="h-3 w-3 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <!-- Modal Overlay -->
                <template x-teleport="body">
                    <div x-show="showManual" 
                        x-cloak
                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                        x-transition:enter="transition ease-out duration-300"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100">
                        
                        <div class="absolute inset-0 bg-neutral-900/20 backdrop-blur-sm" @click="showManual = false"></div>
                        
                        <div class="bg-white rounded-3xl p-6 w-full max-w-sm relative z-10 shadow-2xl">
                            <h3 class="font-bold text-lg mb-4">Set Location</h3>
                            
                            <input type="text" id="map-search-input" placeholder="Search area..." 
                                class="w-full text-sm px-4 py-3 rounded-xl border border-neutral-200 mb-3 focus:ring-0 focus:border-emerald-500">
                            
                            <div id="location-map" class="w-full h-64 rounded-2xl border border-neutral-100 overflow-hidden mb-4"></div>

                            <div class="flex gap-2">
                                <button @click="confirmMapLocation()" class="flex-1 py-3 bg-emerald-600 text-white font-semibold rounded-xl text-sm">Confirm</button>
                                <button @click="showManual = false" class="px-5 py-3 bg-neutral-100 text-neutral-600 font-semibold rounded-xl text-sm">Cancel</button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Mode toggle --}}
            <div class="flex gap-2 mb-6 p-1 rounded-2xl fade-up-delay-2" style="background:#F0F0EF">
                <button type="button"
                        @click="mode = 'nlp'"
                        class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                        :style="mode === 'nlp'
                            ? 'background:white; color:#1A1A1A; box-shadow: 0 1px 4px rgba(0,0,0,0.08)'
                            : 'color:#A3A3A3'">
                    Describe it
                </button>
                <button type="button"
                        @click="mode = 'filter'"
                        class="flex-1 py-2.5 rounded-xl text-sm font-semibold transition-all duration-200"
                        :style="mode === 'filter'
                            ? 'background:white; color:#1A1A1A; box-shadow: 0 1px 4px rgba(0,0,0,0.08)'
                            : 'color:#A3A3A3'">
                    Filter it
                </button>
            </div>

            {{-- NLP Mode --}}
            <div x-show="mode === 'nlp'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-x-2"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 class="flex-1 flex flex-col">

                <form action="{{ route('restaurants.search') }}" method="POST" @submit="handleSubmit">
                    @csrf
                    <input type="hidden" name="mode" value="nlp">
                    <input type="hidden" name="latitude"  id="nlp-lat">
                    <input type="hidden" name="longitude" id="nlp-lng">

                    {{-- Your ORIGINAL Wrapper Styling --}}
                    <div class="relative rounded-2xl transition-all duration-200"
                         style="box-shadow: 0 4px 24px rgba(0,0,0,0.08); border: 1.5px solid #F0F0EF"
                         :style="focused ? 'border-color:#059669' : ''">

                        {{-- Your ORIGINAL Input Styling (Restored bg-white, borders, and default shadow-sm) --}}
                        <input
                            type="text"
                            name="query"
                            @focus="focused = true"
                            @blur="focused = false"
                            placeholder="e.g. I want spicy ramen under 50k..."
                            class="w-full bg-white border border-neutral-200 rounded-2xl py-5 pl-6 pr-14 shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition duration-300 ease-in-out text-sm resize-none"
                            style="color:#1A1A1A"
                        />

                        {{-- Embedded Submit Button (with added drop shadow to stand out) --}}
                        <button type="submit"
                                class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center justify-center w-10 h-10 rounded-xl transition-transform active:scale-95 shadow-md"
                                style="background:#059669; color:white">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 transform rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19V5m-7 7l7-7 7 7" />
                            </svg>
                        </button>
                    </div>

                    @error('query')
                        <p class="text-xs mt-2 text-center" style="color:#EF4444">{{ $message }}</p>
                    @enderror
                </form>
            </div>
            {{-- Filter mode --}}
            <div x-show="mode === 'filter'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-x-2"
                 x-transition:enter-end="opacity-100 translate-x-0"
                 class="flex-1 flex flex-col">

                <form action="{{ route('restaurants.search') }}" method="POST" @submit="handleSubmit">
                    @csrf
                    <input type="hidden" name="mode" value="filter">
                    <input type="hidden" name="latitude"  id="filter-lat">
                    <input type="hidden" name="longitude" id="filter-lng">
                    <input type="hidden" name="food_type"    :value="filter.food">
                    <input type="hidden" name="max_price"    :value="filter.price">
                    <input type="hidden" name="max_distance" :value="filter.distance">
                    <input type="hidden" name="visit_time"   :value="filter.visitTime">

                    <div class="bg-white rounded-2xl overflow-hidden"
                         style="box-shadow: 0 4px 24px rgba(0,0,0,0.08); border:1.5px solid #F0F0EF">

                        {{-- ── SECTION 1: Food Type ── --}}
                        <div class="p-5" style="border-bottom: 1px solid #F5F5F5">
                            <p class="font-bold uppercase tracking-widest mb-3" style="font-size:10px; color:#A3A3A3">
                                What are you craving?
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach([
                                    'any'        => ['emoji' => '🍽️', 'label' => 'Anything'],
                                    'indonesian' => ['emoji' => '🍛', 'label' => 'Indonesian'],
                                    'chicken'    => ['emoji' => '🍗', 'label' => 'Chicken'],
                                    'ramen'      => ['emoji' => '🍜', 'label' => 'Ramen'],
                                    'sushi'      => ['emoji' => '🍣', 'label' => 'Sushi'],
                                    'burger'     => ['emoji' => '🍔', 'label' => 'Burger'],
                                    'pizza'      => ['emoji' => '🍕', 'label' => 'Pizza'],
                                    'coffee'     => ['emoji' => '☕', 'label' => 'Coffee'],
                                    'korean'     => ['emoji' => '🥘', 'label' => 'Korean'],
                                    'seafood'    => ['emoji' => '🦐', 'label' => 'Seafood'],
                                    'chinese'    => ['emoji' => '🥡', 'label' => 'Chinese'],
                                    'steak'      => ['emoji' => '🥩', 'label' => 'Steak'],
                                ] as $value => $item)
                                <button type="button"
                                        @click="filter.food = '{{ $value }}'"
                                        class="flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full border font-semibold transition-all duration-150"
                                        :style="filter.food === '{{ $value }}'
                                            ? 'background:#059669; color:white; border-color:#059669'
                                            : 'background:#FAFAFA; color:#525252; border-color:#E5E5E5'">
                                    <span>{{ $item['emoji'] }}</span>
                                    <span>{{ $item['label'] }}</span>
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- ── SECTION 2: Price Slider ── --}}
                        <div class="p-5" style="border-bottom: 1px solid #F5F5F5">
                            <div class="flex items-center justify-between mb-3">
                                <p class="font-bold uppercase tracking-widest" style="font-size:10px; color:#A3A3A3">
                                    Max Budget
                                </p>
                                <p class="text-sm font-bold tabular-nums" style="color:#059669; font-family:'JetBrains Mono',monospace"
                                   x-text="filter.price === 0 ? 'Any price' : 'Up to Rp ' + filter.price.toLocaleString('id-ID')">
                                </p>
                            </div>

                            {{-- Slider --}}
                            <div class="relative px-1">
                                <input
                                    type="range"
                                    min="0"
                                    max="200000"
                                    step="5000"
                                    x-model.number="filter.price"
                                    x-ref="priceSlider"
                                    class="w-full h-1.5 rounded-full appearance-none cursor-pointer"
                                    style="accent-color: #059669;"
                                    @input="updateSliderFill($el, $event.target.value)"
                                    x-init="$nextTick(() => updateSliderFill($el, filter.price))"
                                >
                            </div>

                            {{-- Tick labels --}}
                            <div class="flex justify-between mt-2 px-1">
                                <span class="text-[10px] font-medium" style="color:#A3A3A3">Any</span>
                                <span class="text-[10px] font-medium" style="color:#A3A3A3">50k</span>
                                <span class="text-[10px] font-medium" style="color:#A3A3A3">100k</span>
                                <span class="text-[10px] font-medium" style="color:#A3A3A3">150k</span>
                                <span class="text-[10px] font-medium" style="color:#A3A3A3">200k</span>
                            </div>

                            {{-- Quick preset chips --}}
                            <div class="flex gap-2 mt-3">
                                @foreach([0 => 'Any', 30000 => 'Under 30k', 50000 => 'Under 50k', 100000 => 'Under 100k'] as $val => $label)
                                <button type="button"
                                        @click="filter.price = {{ $val }}; updateSliderFill($refs.priceSlider, {{ $val }})"
                                        class="flex-1 text-[10px] font-semibold py-1.5 rounded-lg border transition-all duration-150"
                                        :style="filter.price === {{ $val }}
                                            ? 'background:#059669; color:white; border-color:#059669'
                                            : 'background:#FAFAFA; color:#525252; border-color:#E5E5E5'">
                                    {{ $label }}
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- ── SECTION 3: Distance ── --}}
                        <div class="p-5" style="border-bottom: 1px solid #F5F5F5">
                            <p class="font-bold uppercase tracking-widest mb-3" style="font-size:10px; color:#A3A3A3">
                                How far?
                            </p>
                            <div class="grid grid-cols-4 gap-2">
                                @foreach([
                                    500  => ['label' => 'Walking',  'sub' => '< 500m'],
                                    1000 => ['label' => 'Close',    'sub' => '< 1km'],
                                    2000 => ['label' => 'Nearby',   'sub' => '< 2km'],
                                    3000 => ['label' => 'Anywhere', 'sub' => '< 3km'],
                                ] as $meters => $item)
                                <button type="button"
                                        @click="filter.distance = {{ $meters }}"
                                        class="flex flex-col items-center py-2.5 px-1 rounded-xl border transition-all duration-150"
                                        :style="filter.distance === {{ $meters }}
                                            ? 'background:#059669; color:white; border-color:#059669'
                                            : 'background:#FAFAFA; color:#525252; border-color:#E5E5E5'">
                                    <span class="text-xs font-bold">{{ $item['label'] }}</span>
                                    <span class="text-[9px] mt-0.5 opacity-70">{{ $item['sub'] }}</span>
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- ── SECTION 4: Visit Time ── --}}
                        <div class="p-5" style="border-bottom: 1px solid #F5F5F5">
                            <p class="font-bold uppercase tracking-widest mb-3" style="font-size:10px; color:#A3A3A3">
                                When are you going?
                            </p>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach([
                                    'now'       => ['emoji' => '⚡', 'label' => 'Right now'],
                                    'morning'   => ['emoji' => '🌅', 'label' => 'Morning'],
                                    'lunch'     => ['emoji' => '☀️',  'label' => 'Lunch'],
                                    'afternoon' => ['emoji' => '🌤️', 'label' => 'Afternoon'],
                                    'evening'   => ['emoji' => '🌆', 'label' => 'Evening'],
                                    'night'     => ['emoji' => '🌙', 'label' => 'Night'],
                                ] as $value => $item)
                                <button type="button"
                                        @click="filter.visitTime = '{{ $value }}'"
                                        class="flex items-center gap-2 px-3 py-2.5 rounded-xl border text-xs font-semibold transition-all duration-150"
                                        :style="filter.visitTime === '{{ $value }}'
                                            ? 'background:#059669; color:white; border-color:#059669'
                                            : 'background:#FAFAFA; color:#525252; border-color:#E5E5E5'">
                                    <span>{{ $item['emoji'] }}</span>
                                    <span>{{ $item['label'] }}</span>
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- ── FOOTER: Summary + Submit ── --}}
                        <div class="p-5">
                            <p class="text-xs font-mono mb-4 px-1" style="color:#A3A3A3" x-text="filterSummary()"></p>
                            <button type="submit"
                                    class="w-full flex items-center justify-center gap-2 text-sm font-semibold text-white py-3.5 rounded-xl transition-all duration-200 active:scale-95"
                                    style="background:#059669">
                                Find places
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>
                            </button>
                        </div>

                    </div>
                </form>
            </div>

            {{-- Session error --}}
            @if(session('error'))
            <div class="mt-4 px-4 py-3 rounded-xl text-sm" style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA">
                {{ session('error') }}
            </div>
            @endif

        </div>

       
        <div class="mt-6 pt-5 border-t border-neutral-100 text-center">
            <a href="{{ route('restaurants.browse') }}"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-neutral-400 hover:text-emerald-600 transition-colors">
                Just show me everything nearby
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        {{-- PROCESSING SCREEN --}}
        <div x-show="screen === 'processing'"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-5"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="flex flex-col items-center justify-center min-h-96 text-center">

            <div class="w-20 h-20 rounded-full mb-8"
                 style="border:4px solid #F0F0EF; border-top-color:#059669; animation:spin 1s linear infinite">
            </div>

            <div class="space-y-2 w-full text-left max-w-xs">
                <template x-for="(line, i) in visibleSteps" :key="i">
                    <p class="thinking-line text-sm font-medium"
                       :style="`color:${i === visibleSteps.length - 1 ? '#1A1A1A' : '#A3A3A3'};
                                font-family:'JetBrains Mono',monospace;
                                animation-delay:${i * 0.15}s`"
                       x-text="line">
                    </p>
                </template>
            </div>

            <div class="grid grid-cols-2 gap-3 mt-8 w-full max-w-xs text-left">
                <div class="rounded-xl p-3" style="background:white; border:1px solid #F0F0EF">
                    <p class="font-bold uppercase tracking-widest" style="font-size:9px; color:#A3A3A3">C1 · Distance</p>
                    <p class="text-sm font-semibold mt-1 font-mono" style="color:#F59E0B">w = 0.35</p>
                </div>
                <div class="rounded-xl p-3" style="background:white; border:1px solid #F0F0EF">
                    <p class="font-bold uppercase tracking-widest" style="font-size:9px; color:#A3A3A3">C2 · Food Match</p>
                    <p class="text-sm font-semibold mt-1 font-mono" style="color:#F59E0B">w = 0.30</p>
                </div>
                <div class="rounded-xl p-3" style="background:white; border:1px solid #F0F0EF">
                    <p class="font-bold uppercase tracking-widest" style="font-size:9px; color:#A3A3A3">C3 · Rating</p>
                    <p class="text-sm font-semibold mt-1 font-mono" style="color:#F59E0B">w = 0.20</p>
                </div>
                <div class="rounded-xl p-3" style="background:white; border:1px solid #F0F0EF">
                    <p class="font-bold uppercase tracking-widest" style="font-size:9px; color:#A3A3A3">C4 · Price</p>
                    <p class="text-sm font-semibold mt-1 font-mono" style="color:#F59E0B">w = 0.15</p>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
@keyframes spin { to { transform: rotate(360deg); } }
[x-cloak] { display: none !important; }

/* Slider thumb styling */
input[type='range']::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #059669;
    cursor: pointer;
    border: 3px solid white;
    box-shadow: 0 1px 6px rgba(5,150,105,0.4);
    transition: transform 0.15s ease;
}
input[type='range']::-webkit-slider-thumb:hover {
    transform: scale(1.2);
}
input[type='range']::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #059669;
    cursor: pointer;
    border: 3px solid white;
    box-shadow: 0 1px 6px rgba(5,150,105,0.4);
}
input[type='range']::-webkit-slider-runnable-track {
    height: 6px;
    border-radius: 9999px;
}
</style>

<script>
function searchForm() {
    return {
        screen: 'input',
        mode: 'nlp',
        focused: false,
        visibleSteps: [],
        filter: {
            food: 'any',
            price: 0,
            distance: 3000,
            visitTime: 'now',
        },
        allSteps: [
            '> Reading your query...',
            '> Extracting intent with NLP...',
            '> Fetching nearby restaurants...',
            '> Applying food match filter...',
            '> Running SAW algorithm...',
            '> Ranking results...',
        ],
        filterSteps: [
            '> Reading your preferences...',
            '> Fetching nearby restaurants...',
            '> Applying filters...',
            '> Running SAW algorithm...',
            '> Ranking results...',
        ],

        updateSliderFill(el, val) {
            const min = parseFloat(el.min) || 0;
            const max = parseFloat(el.max) || 200000;
            const pct = ((val - min) / (max - min)) * 100;
            el.style.background = `linear-gradient(to right, #059669 0%, #059669 ${pct}%, #E5E5E5 ${pct}%, #E5E5E5 100%)`;
        },

        filterSummary() {
            const foodLabels = {
                any:'Anything', indonesian:'Indonesian', chicken:'Chicken',
                ramen:'Ramen', sushi:'Sushi', burger:'Burger', pizza:'Pizza',
                coffee:'Coffee', korean:'Korean', seafood:'Seafood',
                chinese:'Chinese', steak:'Steak'
            };
            const distLabels = {
                500:'Walking distance', 1000:'Under 1km',
                2000:'Under 2km', 3000:'Under 3km'
            };
            const timeLabels = {
                now:'Right now', morning:'Morning', lunch:'Lunch',
                afternoon:'Afternoon', evening:'Evening', night:'Night'
            };
            const priceStr = this.filter.price === 0
                ? 'any price'
                : 'up to Rp ' + this.filter.price.toLocaleString('id-ID');

            return `> ${foodLabels[this.filter.food]} · ${priceStr} · ${distLabels[this.filter.distance]} · ${timeLabels[this.filter.visitTime]}`;
        },

        handleSubmit(e) {
            e.preventDefault();

            if (this.mode === 'nlp') {
                const inputBox = e.target.querySelector('input[name="query"]');
                if (!inputBox || inputBox.value.trim().length < 3) return;
            }

            const lat = document.getElementById('global-latitude')?.value  || '-6.2233';
            const lng = document.getElementById('global-longitude')?.value || '106.6491';

            if (this.mode === 'nlp') {
                document.getElementById('nlp-lat').value = lat;
                document.getElementById('nlp-lng').value = lng;
            } else {
                document.getElementById('filter-lat').value = lat;
                document.getElementById('filter-lng').value = lng;
            }

            this.screen = 'processing';
            this.visibleSteps = [];

            const steps = this.mode === 'nlp' ? this.allSteps : this.filterSteps;
            let i = 0;
            const interval = setInterval(() => {
                if (i < steps.length) {
                    this.visibleSteps.push(steps[i]);
                    i++;
                } else {
                    clearInterval(interval);
                }
            }, 900);

            setTimeout(() => { e.target.submit(); }, 900);
        }
    }
}

function locationPicker() {
    return {
        lat: '{{ $lastLat ?? -6.2233 }}',
        lng: '{{ $lastLng ?? 106.6491 }}',
        label: '{{ isset($lastLat) && $lastLat != -6.2233 ? "Last used location" : "Binus Alam Sutera (default)" }}',
        error: '',
        detecting: false,
        showManual: false,
        map: null,
        marker: null,
        searchBox: null,

        detectLocation() {
            if (!navigator.geolocation) {
                this.error = 'Geolocation not supported by your browser.';
                return;
            }
            this.detecting = true;
            this.error = '';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    this.lat   = position.coords.latitude.toString();
                    this.lng   = position.coords.longitude.toString();
                    this.label = 'Current location detected';
                    this.detecting = false;
                    this.saveLocation(this.lat, this.lng);
                },
                () => {
                    this.detecting = false;
                    this.error = 'Could not detect location. Allow access or set manually.';
                },
                { timeout: 10000, enableHighAccuracy: true }
            );
        },

        openMap() {
            this.showManual = true;
            this.$nextTick(() => {
                this.initMap();
            });
        },

        initMap() {
            const lat = parseFloat(this.lat) || -6.2233;
            const lng = parseFloat(this.lng) || 106.6491;
            const center = { lat, lng };

            // Init map
            this.map = new google.maps.Map(document.getElementById('location-map'), {
                center,
                zoom: 15,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false,
            });

            // Draggable marker
            this.marker = new google.maps.Marker({
                position: center,
                map: this.map,
                draggable: true,
                animation: google.maps.Animation.DROP,
            });

            // Update coords when marker is dragged
            this.marker.addListener('dragend', (event) => {
                this.lat = event.latLng.lat().toString();
                this.lng = event.latLng.lng().toString();
                this.label = 'Custom pin location';
            });

            // Search box
            const input = document.getElementById('map-search-input');
            const searchBox = new google.maps.places.SearchBox(input);

            searchBox.addListener('places_changed', () => {
                const places = searchBox.getPlaces();
                if (!places || places.length === 0) return;

                const place = places[0];
                if (!place.geometry || !place.geometry.location) return;

                const location = place.geometry.location;
                this.map.setCenter(location);
                this.map.setZoom(16);
                this.marker.setPosition(location);
                this.lat   = location.lat().toString();
                this.lng   = location.lng().toString();
                this.label = place.name ?? place.formatted_address;
            });
        },

        confirmMapLocation() {
            this.showManual = false;
            this.label = 'Custom pin location';
            this.saveLocation(this.lat, this.lng);
        },

        async saveLocation(lat, lng) {
            await fetch('{{ route("location.save") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ latitude: lat, longitude: lng }),
            });
        },
    }
}
</script>
</x-app-layout>