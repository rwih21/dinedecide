<x-app-layout>
<div class="min-h-screen px-4 py-10" style="background:#F9F9F8">
<div class="max-w-2xl mx-auto">

    <div class="mb-8">
        <a href="{{ route('admin.promoted.index') }}"
           class="inline-flex items-center gap-2 text-sm font-medium text-neutral-400 hover:text-emerald-600 transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            Back
        </a>
        <h1 class="text-2xl font-bold text-neutral-900 mt-4">
            {{ $place->exists ? 'Edit Promotion' : 'New Promotion' }}
        </h1>
    </div>

    @if($errors->any())
    <div class="mb-6 px-4 py-3 rounded-xl text-sm" style="background:#FEF2F2; color:#DC2626; border:1px solid #FECACA">
        <ul class="space-y-1">
            @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST"
          action="{{ $place->exists ? route('admin.promoted.update', $place) : route('admin.promoted.store') }}"
          enctype="multipart/form-data"
          class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-6 space-y-5">
        @csrf
        @if($place->exists) @method('PUT') @endif

        {{-- Name --}}
        <div>
            <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                Place Name *
            </label>
            <input type="text" name="name"
                   value="{{ old('name', $place->name) }}"
                   class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                   style="border-color:#E5E5E5; color:#1A1A1A"
                   placeholder="e.g. Warung Ayam Pak Budi">
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                Description
            </label>
            <textarea name="description" rows="3"
                      class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition resize-none"
                      style="border-color:#E5E5E5; color:#1A1A1A"
                      placeholder="Short description of the place...">{{ old('description', $place->description) }}</textarea>
        </div>

        {{-- Address --}}
        <div>
            <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                Address
            </label>
            <input type="text" name="address"
                   value="{{ old('address', $place->address) }}"
                   class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                   style="border-color:#E5E5E5; color:#1A1A1A"
                   placeholder="e.g. Jl. Pahlawan No. 12, Alam Sutera">
        </div>

        {{-- Lat / Lng --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                    Latitude
                </label>
                <input type="text" name="latitude"
                       value="{{ old('latitude', $place->latitude) }}"
                       class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition font-mono"
                       style="border-color:#E5E5E5; color:#1A1A1A"
                       placeholder="-6.2233">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                    Longitude
                </label>
                <input type="text" name="longitude"
                       value="{{ old('longitude', $place->longitude) }}"
                       class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition font-mono"
                       style="border-color:#E5E5E5; color:#1A1A1A"
                       placeholder="106.6491">
            </div>
        </div>

        {{-- Food Types --}}
        <div>
            <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                Food Types * <span class="normal-case font-normal">(comma-separated)</span>
            </label>
            <input type="text" name="food_types_raw"
                   value="{{ old('food_types_raw', $place->exists ? implode(', ', $place->food_types) : '') }}"
                   class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                   style="border-color:#E5E5E5; color:#1A1A1A"
                   placeholder="e.g. chicken, indonesian">
            <p class="text-[10px] text-neutral-400 mt-1">
                Use: any, ramen, sushi, japanese, indonesian, burger, pizza, chicken, coffee
            </p>
        </div>

        {{-- Price --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                    Price Display *
                </label>
                <input type="text" name="price_display"
                       value="{{ old('price_display', $place->price_display) }}"
                       class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="border-color:#E5E5E5; color:#1A1A1A"
                       placeholder="e.g. Rp 25k - 50k">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                    Min Price (IDR) *
                </label>
                <input type="number" name="min_price"
                       value="{{ old('min_price', $place->min_price) }}"
                       class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition font-mono"
                       style="border-color:#E5E5E5; color:#1A1A1A"
                       placeholder="25000">
                <p class="text-[10px] text-neutral-400 mt-1">Used for budget matching</p>
            </div>
        </div>

        {{-- Photo --}}
        <div>
            <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                Photo
            </label>
            @if($place->exists && $place->photo_path)
            <img src="{{ $place->photo_url }}" class="w-32 h-20 rounded-xl object-cover mb-2">
            @endif
            <input type="file" name="photo" accept="image/*"
                   class="w-full text-sm text-neutral-500 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-neutral-100 file:text-neutral-700 hover:file:bg-neutral-200">
            <p class="text-[10px] text-neutral-400 mt-1">Max 2MB. Leave blank to keep existing.</p>
        </div>

        {{-- Contact --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                    WhatsApp
                </label>
                <input type="text" name="whatsapp"
                       value="{{ old('whatsapp', $place->whatsapp) }}"
                       class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="border-color:#E5E5E5; color:#1A1A1A"
                       placeholder="e.g. 6281234567890">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                    Google Maps URL
                </label>
                <input type="url" name="gmaps_url"
                       value="{{ old('gmaps_url', $place->gmaps_url) }}"
                       class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="border-color:#E5E5E5; color:#1A1A1A"
                       placeholder="https://maps.google.com/...">
            </div>
        </div>

        {{-- Schedule --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                    Start Date
                </label>
                <input type="date" name="starts_at"
                       value="{{ old('starts_at', $place->starts_at?->format('Y-m-d')) }}"
                       class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="border-color:#E5E5E5; color:#1A1A1A">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-neutral-400 mb-1.5">
                    End Date
                </label>
                <input type="date" name="ends_at"
                       value="{{ old('ends_at', $place->ends_at?->format('Y-m-d')) }}"
                       class="w-full text-sm px-4 py-2.5 rounded-xl border focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="border-color:#E5E5E5; color:#1A1A1A">
            </div>
        </div>

        {{-- Active toggle --}}
        <div class="flex items-center gap-3 pt-2">
            <input type="checkbox" name="is_active" id="is_active" value="1"
                   {{ old('is_active', $place->is_active) ? 'checked' : '' }}
                   class="w-4 h-4 rounded accent-emerald-600">
            <label for="is_active" class="text-sm font-medium text-neutral-700">
                Active immediately
            </label>
        </div>

        {{-- Submit --}}
        <div class="pt-2" style="border-top:1px solid #F0F0EF">
            <button type="submit"
                    class="w-full text-sm font-semibold text-white py-3 rounded-xl transition-all active:scale-95"
                    style="background:#059669">
                {{ $place->exists ? 'Save Changes' : 'Create Promotion' }}
            </button>
        </div>

    </form>
</div>
</div>
</x-app-layout>