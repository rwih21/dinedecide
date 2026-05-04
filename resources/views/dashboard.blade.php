<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Dashboard
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 text-center">
                <p class="text-gray-600 mb-4">Ready to find your next meal?</p>
                <a href="{{ route('restaurants.index') }}"
                   class="inline-block bg-[#059669] hover:bg-emerald-500 text-white font-medium px-6 py-3 rounded-xl transition-colors">
                    Start searching →
                </a>
            </div>
        </div>
    </div>
</x-app-layout>