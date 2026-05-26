<x-app-layout>
<div class="min-h-screen relative overflow-hidden flex flex-col items-center px-6 py-8" style="background:#F9F9F8">

    {{-- Background blurs --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full opacity-20"
             style="background:radial-gradient(circle, #059669, transparent 70%)"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full opacity-20"
             style="background:radial-gradient(circle, #F59E0B, transparent 70%)"></div>
    </div>

    <div class="relative w-full max-w-md" x-data="searchForm()" x-cloak>

        {{-- Header --}}
        <div class="flex items-center justify-between mb-10 fade-up">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:#059669">
                    <span class="text-white font-bold text-sm">DD</span>
                </div>
                <span class="font-bold text-lg" style="color:#1A1A1A">DineDecide</span>
            </div>
            <div class="text-right">
                <p class="font-bold uppercase tracking-widest mt-0.5" style="color:#525252; font-size:10px"
                   x-data="{ time: '' }"
                   x-init="const u=()=>{time=new Date().toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})};u();setInterval(u,1000)"
                   x-text="time">
                </p>
            </div>
        </div>

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
            <div class="mb-6 fade-up-delay-2" x-data="locationPicker()">
                <input type="hidden" id="global-latitude"  x-model="lat">
                <input type="hidden" id="global-longitude" x-model="lng">

                <div class="bg-white rounded-2xl p-4"
                     style="box-shadow:0 4px 24px rgba(0,0,0,0.08); border:1.5px solid #F0F0EF">

                    <p class="font-bold uppercase tracking-widest mb-3" style="font-size:10px; color:#A3A3A3">
                        📍 Your Location
                    </p>

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex-1">
                            <p class="text-sm font-medium" style="color:#1A1A1A" x-text="label"></p>
                            <p class="text-xs font-mono mt-0.5" style="color:#A3A3A3"
                               x-show="lat && lng"
                               x-text="`${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`">
                            </p>
                            <p class="text-xs mt-0.5" style="color:#EF4444"
                               x-show="error" x-text="error">
                            </p>
                        </div>
                        <button type="button"
                                @click="detectLocation()"
                                :disabled="detecting"
                                class="flex items-center gap-2 text-xs font-semibold px-3 py-2 rounded-xl transition-all duration-200 active:scale-95"
                                style="background:#F0FDF4; color:#059669; border:1px solid #BBF7D0">
                            <span x-show="!detecting">📡 Detect</span>
                            <span x-show="detecting">Detecting...</span>
                        </button>
                    </div>

                    <div class="flex items-center gap-2 my-3">
                        <div class="flex-1 h-px" style="background:#F0F0EF"></div>
                        <p class="text-xs" style="color:#A3A3A3">or set manually</p>
                        <div class="flex-1 h-px" style="background:#F0F0EF"></div>
                    </div>

                    <div x-show="!showManual">
                        <button type="button"
                                @click="showManual = true"
                                class="w-full text-xs font-medium py-2 rounded-xl border transition-colors"
                                style="color:#525252; border-color:#E5E5E5">
                            🔍 Set a different location
                        </button>
                    </div>

                    <div x-show="showManual" class="space-y-2">
                        <input type="text"
                               x-model="manualSearch"
                               placeholder="e.g. Hotel Tentrem Semarang"
                               class="w-full text-sm px-3 py-2 rounded-xl border focus:outline-none"
                               style="border-color:#E5E5E5; color:#1A1A1A"
                               @keydown.enter.prevent="geocodeLocation()">
                        <div class="flex gap-2">
                            <button type="button"
                                    @click="geocodeLocation()"
                                    :disabled="geocoding"
                                    class="flex-1 text-xs font-semibold py-2 rounded-xl text-white transition-all active:scale-95"
                                    style="background:#059669">
                                <span x-show="!geocoding">Search</span>
                                <span x-show="geocoding">Searching...</span>
                            </button>
                            <button type="button"
                                    @click="showManual = false"
                                    class="text-xs px-3 py-2 rounded-xl border"
                                    style="color:#525252; border-color:#E5E5E5">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
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

            {{-- MODE A: NLP --}}
            <div x-show="mode === 'nlp'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-x-2"
                 x-transition:enter-end="opacity-100 translate-x-0">

                <form action="{{ route('restaurants.search') }}" method="POST" @submit="handleSubmit">
                    @csrf
                    <input type="hidden" name="mode" value="nlp">
                    <input type="hidden" name="latitude"  id="nlp-lat">
                    <input type="hidden" name="longitude" id="nlp-lng">

                    <div class="rounded-2xl transition-all duration-200"
                         style="box-shadow: 0 4px 24px rgba(0,0,0,0.08); border: 1.5px solid #F0F0EF"
                         :style="focused ? 'border-color:#059669' : ''">

                        <input
                            type="text"
                            name="query"
                            {{-- rows="1" --}}
                            @focus="focused = true"
                            @blur="focused = false"
                            placeholder="e.g. I want spicy ramen near Binus under 50k..."
                            class="w-full bg-white border border-neutral-200 rounded-2xl py-5 pl-6 pr-14 shadow-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition duration-300 ease-in-out text-sm resize-none"
                            style="color:#1A1A1A"
                        />

                        @error('query')
                            <p class="text-xs mt-1" style="color:#EF4444">{{ $message }}</p>
                        @enderror

                        {{-- Quick tags --}}
                        {{-- <div class="flex flex-wrap gap-2 mt-3">
                            @foreach(['Quick lunch', 'Date night', 'Budget meal', 'Ramen nearby'] as $tag)
                            <button type="button"
                                    @click="$el.closest('form').querySelector('textarea').value = '{{ $tag }}'"
                                    class="text-xs px-3 py-1 rounded-full border transition-colors duration-150"
                                    style="border-color:#E5E5E5; color:#525252">
                                {{ $tag }}
                            </button>
                            @endforeach
                        </div> --}}

                        <div class="flex items-center justify-between pt-3">
                            <p class="text-xs" style="color:#D4D4D4"></p>
                            <button type="submit"
                                    class="flex items-center gap-2 text-sm font-semibold text-white px-5 py-2.5 rounded-xl transition-all duration-200 active:scale-95"
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

            {{-- MODE B: Filter chips --}}
            <div x-show="mode === 'filter'"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 translate-x-2"
                 x-transition:enter-end="opacity-100 translate-x-0">

                <form action="{{ route('restaurants.search') }}" method="POST" @submit="handleSubmit">
                    @csrf
                    <input type="hidden" name="mode" value="filter">
                    <input type="hidden" name="latitude"  id="filter-lat">
                    <input type="hidden" name="longitude" id="filter-lng">
                    <input type="hidden" name="food_type" :value="filter.food">
                    <input type="hidden" name="max_price" :value="filter.price">
                    <input type="hidden" name="max_distance" :value="filter.distance">

                    <div class="bg-white rounded-2xl p-5 space-y-5"
                         style="box-shadow: 0 4px 24px rgba(0,0,0,0.08); border:1.5px solid #F0F0EF">

                        {{-- Food type --}}
                        <div>
                            <p class="font-bold uppercase tracking-widest mb-3" style="font-size:10px; color:#A3A3A3">
                                What are you craving?
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @foreach([
                                    'any'        => '🍽️ Anything',
                                    'ramen'      => '🍜 Ramen',
                                    'sushi'      => '🍣 Sushi',
                                    'indonesian' => '🍛 Indonesian',
                                    'burger'     => '🍔 Burger',
                                    'pizza'      => '🍕 Pizza',
                                    'chicken'    => '🍗 Chicken',
                                    'coffee'     => '☕ Coffee',
                                ] as $value => $label)
                                <button type="button"
                                        @click="filter.food = '{{ $value }}'"
                                        class="text-sm px-3 py-1.5 rounded-full border transition-all duration-150 font-medium"
                                        :style="filter.food === '{{ $value }}'
                                            ? 'background:#059669; color:white; border-color:#059669'
                                            : 'background:white; color:#525252; border-color:#E5E5E5'">
                                    {{ $label }}
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Price --}}
                        <div>
                            <p class="font-bold uppercase tracking-widest mb-3" style="font-size:10px; color:#A3A3A3">
                                Budget?
                            </p>
                            <div class="flex gap-2">
                                @foreach([1 => '$', 2 => '$$', 3 => '$$$', 4 => '$$$$'] as $level => $label)
                                <button type="button"
                                        @click="filter.price = {{ $level }}"
                                        class="flex-1 py-2 rounded-xl border text-sm font-mono font-semibold transition-all duration-150"
                                        :style="filter.price === {{ $level }}
                                            ? 'background:#059669; color:white; border-color:#059669'
                                            : 'background:white; color:#525252; border-color:#E5E5E5'">
                                    {{ $label }}
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Distance --}}
                        <div>
                            <p class="font-bold uppercase tracking-widest mb-3" style="font-size:10px; color:#A3A3A3">
                                How far?
                            </p>
                            <div class="flex gap-2">
                                @foreach([500 => 'Walking', 1000 => '< 1km', 2000 => '< 2km', 3000 => '< 3km'] as $meters => $label)
                                <button type="button"
                                        @click="filter.distance = {{ $meters }}"
                                        class="flex-1 py-2 rounded-xl border text-xs font-semibold transition-all duration-150"
                                        :style="filter.distance === {{ $meters }}
                                            ? 'background:#059669; color:white; border-color:#059669'
                                            : 'background:white; color:#525252; border-color:#E5E5E5'">
                                    {{ $label }}
                                </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Summary + Submit --}}
                        <div class="pt-3" style="border-top:1px solid #F5F5F5">
                            <p class="text-xs mb-3 font-mono" style="color:#A3A3A3">
                                <span x-text="filterSummary()"></span>
                            </p>
                            <button type="submit"
                                    class="w-full flex items-center justify-center gap-2 text-sm font-semibold text-white py-3 rounded-xl transition-all duration-200 active:scale-95"
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
            price: 4,
            distance: 3000,
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
        filterSummary() {
            const foodLabels = {
                any:'Anything', ramen:'Ramen', sushi:'Sushi',
                indonesian:'Indonesian', burger:'Burger',
                pizza:'Pizza', chicken:'Chicken', coffee:'Coffee'
            };
            const distLabels = {500:'Walking distance', 1000:'Under 1km', 2000:'Under 2km', 3000:'Under 3km'};
            const price = '$'.repeat(this.filter.price);
            return `${foodLabels[this.filter.food]} · ${price} · ${distLabels[this.filter.distance]}`;
        },
        handleSubmit(e) {
            // Submit NLP
            if (this.mode === 'nlp') {
                const inputBox = e.target.querySelector('input[name="query"]');
                if (!inputBox || inputBox.value.trim().length < 3) return;
            }

            // Sync location into whichever form is being submitted
            const lat = document.getElementById('global-latitude')?.value || '-6.2233';
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
        lat: '-6.2233',
        lng: '106.6491',
        label: 'Binus Alam Sutera (default)',
        error: '',
        detecting: false,
        geocoding: false,
        showManual: false,
        manualSearch: '',

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
                },
                () => {
                    this.detecting = false;
                    this.error = 'Could not detect location. Allow access or set manually.';
                },
                { timeout: 10000, enableHighAccuracy: true }
            );
        },

        async geocodeLocation() {
            if (!this.manualSearch.trim()) return;
            this.geocoding = true;
            this.error = '';

            try {
                const apiKey = '{{ config("services.google_places.key") }}';
                const query  = encodeURIComponent(this.manualSearch);
                const url    = `https://maps.googleapis.com/maps/api/geocode/json?address=${query}&key=${apiKey}`;
                const res    = await fetch(url);
                const data   = await res.json();

                if (data.status === 'OK' && data.results.length > 0) {
                    const loc      = data.results[0].geometry.location;
                    this.lat       = loc.lat.toString();
                    this.lng       = loc.lng.toString();
                    this.label     = data.results[0].formatted_address;
                    this.showManual   = false;
                    this.manualSearch = '';
                } else {
                    this.error = 'Location not found. Try a more specific name.';
                }
            } catch (e) {
                this.error = 'Search failed. Check your connection.';
            }

            this.geocoding = false;
        }
    }
}
</script>
</x-app-layout>