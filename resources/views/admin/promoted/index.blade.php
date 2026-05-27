<x-app-layout>
<div class="min-h-screen px-4 py-10" style="background:#F9F9F8">
<div class="max-w-4xl mx-auto">

    <div class="flex items-center justify-between mb-8">
        <div>
            <p class="text-xs font-bold uppercase tracking-widest text-neutral-400">Admin Panel</p>
            <h1 class="text-2xl font-bold text-neutral-900 mt-1">Promoted Places</h1>
        </div>
        <a href="{{ route('admin.promoted.create') }}"
           class="inline-flex items-center gap-2 text-sm font-semibold text-white px-4 py-2.5 rounded-xl transition-all active:scale-95"
           style="background:#059669">
            + Add New
        </a>
    </div>

    @if(session('status'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium"
         style="background:#F0FDF4; color:#059669; border:1px solid #BBF7D0">
        {{ session('status') }}
    </div>
    @endif

    @if($places->isEmpty())
    <div class="bg-white rounded-2xl border border-neutral-200 p-10 text-center">
        <p class="text-neutral-400 text-sm">No promoted places yet. Add one to get started.</p>
    </div>
    @else
    <div class="space-y-3">
        @foreach($places as $place)
        <div class="bg-white rounded-2xl border border-neutral-200 shadow-sm p-4">
            <div class="flex gap-4 items-start">

                <img src="{{ $place->photo_url }}"
                     alt="{{ $place->name }}"
                     class="w-20 h-20 rounded-xl object-cover shrink-0 bg-neutral-100">

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <h3 class="font-bold text-neutral-800">{{ $place->name }}</h3>
                        @if($place->is_active)
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                  style="background:#F0FDF4; color:#059669; border:1px solid #BBF7D0">
                                ● Active
                            </span>
                        @else
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full"
                                  style="background:#F9F9F8; color:#A3A3A3; border:1px solid #E5E5E5">
                                ○ Inactive
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-neutral-400 mt-0.5 truncate">{{ $place->address }}</p>

                    <div class="flex flex-wrap gap-1 mt-2">
                        @foreach($place->food_types as $type)
                        <span class="text-[10px] px-2 py-0.5 rounded-full capitalize font-medium"
                              style="background:#F0F0EF; color:#525252">
                            {{ $type }}
                        </span>
                        @endforeach
                        <span class="text-[10px] px-2 py-0.5 rounded-full font-medium"
                              style="background:#ECFDF5; color:#059669">
                            {{ $place->price_display }}
                        </span>
                    </div>

                    @if($place->starts_at || $place->ends_at)
                    <p class="text-[10px] text-neutral-400 mt-1">
                        📅
                        {{ $place->starts_at?->format('d M Y') ?? '—' }}
                        →
                        {{ $place->ends_at?->format('d M Y') ?? 'ongoing' }}
                    </p>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="flex flex-col gap-2 shrink-0">
                    <a href="{{ route('admin.promoted.edit', $place) }}"
                       class="text-xs font-semibold px-3 py-1.5 rounded-lg border text-center transition-colors hover:bg-neutral-50"
                       style="color:#525252; border-color:#E5E5E5">
                        Edit
                    </a>

                    <form method="POST" action="{{ route('admin.promoted.toggle', $place) }}">
                        @csrf @method('PATCH')
                        <button type="submit"
                                class="w-full text-xs font-semibold px-3 py-1.5 rounded-lg border text-center transition-colors hover:bg-neutral-50"
                                style="color:{{ $place->is_active ? '#D97706' : '#059669' }}; border-color:#E5E5E5">
                            {{ $place->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>

                    <form method="POST" action="{{ route('admin.promoted.destroy', $place) }}"
                          onsubmit="return confirm('Delete this promotion?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="w-full text-xs font-semibold px-3 py-1.5 rounded-lg border transition-colors hover:bg-red-50"
                                style="color:#EF4444; border-color:#FECACA">
                            Delete
                        </button>
                    </form>
                </div>

            </div>
        </div>
        @endforeach
    </div>
    @endif

</div>
</div>
</x-app-layout>