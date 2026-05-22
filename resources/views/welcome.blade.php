<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DineDecide</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 min-h-screen flex flex-col items-center justify-center px-4">

    {{-- Hero --}}
    <div class="text-center max-w-lg">
        <h1 class="header text-5xl font-bold text-emerald-600 tracking-tight">DineDecide</h1>
        <p class="text-gray-400 mt-3 text-lg leading-relaxed">
            Stop overthinking where to eat.<br>
            Tell us what you want, we'll rank the rest.
        </p>

        <div class="flex flex-col sm:flex-row gap-3 justify-center mt-8">
            @auth
                <a href="{{ route('restaurants.index') }}"
                   class="bg-[#059669] hover:bg-emerald-500 text-white font-medium px-6 py-3 rounded-xl transition-colors">
                    Start searching
                </a>
            @else
                <a href="{{ route('register') }}"
                   class="bg-[#059669] hover:bg-emerald-500 text-white font-medium px-6 py-3 rounded-xl transition-colors">
                    Get started
                </a>
                <a href="{{ route('login') }}"
                   class="bg-white hover:bg-gray-100 text-gray-700 font-medium px-6 py-3 rounded-xl border border-gray-200 transition-colors">
                    Sign in
                </a>
            @endauth
        </div>
    </div>
</body>
</html>

<style>
    .bg-emerald-600 {
        background-color: var(--color-emerald-600);
    }
</style>