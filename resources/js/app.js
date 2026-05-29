import './bootstrap';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// --- 1. GOOGLE MAP PICKER COMPONENT ---
Alpine.data('googleMapPicker', (config) => ({
    mode: config.mode || 'user',
    lat: config.lat || null,
    lng: config.lng || null,
    status: 'idle', 
    label: 'Binus Alam Sutera (default)',
    map: null,
    marker: null,

    init() {
        if (this.mode === 'admin') {
            if (!this.lat) this.lat = localStorage.getItem('adminLat') || '-6.2233';
            if (!this.lng) this.lng = localStorage.getItem('adminLng') || '106.6491';
            this.renderMap();
        } else {
            if (!this.lat) this.lat = '-6.2233';
            if (!this.lng) this.lng = '106.6491';
        }
    },

    detectLocation() {
        this.status = 'loading';
        if (!navigator.geolocation) {
            this.handleGpsFailure('Geolocation not supported by your browser.');
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                this.lat = pos.coords.latitude.toString();
                this.lng = pos.coords.longitude.toString();
                this.label = '📍 Current location detected';
                this.status = 'ready';
                this.renderMap();
            },
            (error) => {
                this.handleGpsFailure('Location access denied. Please set manually on the map.');
            },
            { timeout: 5000, enableHighAccuracy: true }
        );
    },

    handleGpsFailure(msg) {
        this.status = 'denied';
        this.label = msg;
        this.lat = '-6.2233'; 
        this.lng = '106.6491';
        this.renderMap();
    },

    renderMap() {
        if (typeof google === 'undefined') {
            setTimeout(() => this.renderMap(), 250);
            return;
        }
        
        if (!this.$refs.mapDiv) return;

        const position = { lat: parseFloat(this.lat), lng: parseFloat(this.lng) };

        if (!this.map) {
            this.map = new google.maps.Map(this.$refs.mapDiv, {
                center: position,
                zoom: 15,
                mapTypeControl: false,
                streetViewControl: false,
            });

            this.marker = new google.maps.Marker({
                position: position,
                map: this.map,
                draggable: true,
                animation: google.maps.Animation.DROP,
            });

            this.marker.addListener('dragend', () => {
                const pos = this.marker.getPosition();
                this.updateCoords(pos.lat().toFixed(6), pos.lng().toFixed(6));
            });

            if (this.$refs.searchBox) {
                const autocomplete = new google.maps.places.Autocomplete(this.$refs.searchBox);
                autocomplete.bindTo('bounds', this.map);

                autocomplete.addListener('place_changed', () => {
                    const place = autocomplete.getPlace();
                    if (!place.geometry || !place.geometry.location) return;

                    this.map.panTo(place.geometry.location);
                    this.map.setZoom(17);
                    this.marker.setPosition(place.geometry.location);
                    
                    this.updateCoords(
                        place.geometry.location.lat().toFixed(6),
                        place.geometry.location.lng().toFixed(6)
                    );
                });
            }
        } else {
            this.map.panTo(position);
            this.marker.setPosition(position);
        }
        
        this.$refs.mapDiv.classList.remove('hidden');
    },

    updateCoords(newLat, newLng) {
        this.lat = newLat;
        this.lng = newLng;
        if (this.mode === 'admin') {
            localStorage.setItem('adminLat', this.lat);
            localStorage.setItem('adminLng', this.lng);
        }
    }
}));

// --- 2. SEARCH FORM COMPONENT ---
Alpine.data('searchForm', () => ({
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
        if (this.mode === 'nlp') {
            const inputBox = e.target.querySelector('input[name="query"]');
            if (!inputBox || inputBox.value.trim().length < 3) return;
        }

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
}));

Alpine.start();