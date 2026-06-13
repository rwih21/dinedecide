<x-guest-layout>
<div class="min-h-screen flex flex-col items-center justify-center px-6 py-12" style="background:#F9F9F8">

    {{-- Background blurs --}}
    <div class="pointer-events-none fixed inset-0 overflow-hidden">
        <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full opacity-20"
             style="background:radial-gradient(circle, #059669, transparent 70%)"></div>
        <div class="absolute -bottom-32 -left-32 w-96 h-96 rounded-full opacity-20"
             style="background:radial-gradient(circle, #F59E0B, transparent 70%)"></div>
    </div>

    <div class="relative w-full max-w-sm">

        {{-- Logo / Title --}}
        <div class="mb-8">
            <h1 class="font-bold leading-tight" style="font-size:32px; color:#1A1A1A; letter-spacing:-0.02em">
                Create account
                <em style="font-family: Georgia, serif; font-style: italic; color:#059669">.</em>
            </h1>
            <p class="mt-2 text-sm" style="color:#525252">
                Sign up to find your next meal.
            </p>
        </div>

        <form method="POST" action="{{ route('register') }}">
            @csrf

            {{-- Name --}}
            <div class="mb-4">
                <label for="name" class="block text-xs font-bold uppercase tracking-widest mb-2" style="color:#A3A3A3">
                    Name
                </label>
                <input id="name"
                       type="text"
                       name="name"
                       value="{{ old('name') }}"
                       required
                       autofocus
                       autocomplete="name"
                       class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="color:#1A1A1A">

                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            {{-- Email --}}
            <div class="mb-4">
                <label for="email" class="block text-xs font-bold uppercase tracking-widest mb-2" style="color:#A3A3A3">
                    Email
                </label>
                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       autocomplete="username"
                       class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="color:#1A1A1A">

                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            {{-- Password --}}
            <div class="mb-4">
                <label for="password" class="block text-xs font-bold uppercase tracking-widest mb-2" style="color:#A3A3A3">
                    Password
                </label>
                <input id="password"
                       type="password"
                       name="password"
                       required
                       autocomplete="new-password"
                       class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="color:#1A1A1A">

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            {{-- Confirm Password --}}
            <div class="mb-6">
                <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-widest mb-2" style="color:#A3A3A3">
                    Confirm Password
                </label>
                <input id="password_confirmation"
                       type="password"
                       name="password_confirmation"
                       required
                       autocomplete="new-password"
                       class="w-full bg-neutral-50 border border-neutral-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 transition"
                       style="color:#1A1A1A">

                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>

            {{-- Submit --}}
            <button type="submit"
                    class="w-full flex items-center justify-center gap-2 text-sm font-semibold text-white py-3.5 rounded-xl transition-all duration-200 active:scale-95"
                    style="background:#059669">
                Create account
                <svg xmlns="http://www.w3.org/2000/svg"
                     class="h-4 w-4"
                     fill="none"
                     viewBox="0 0 24 24"
                     stroke="currentColor">
                    <path stroke-linecap="round"
                          stroke-linejoin="round"
                          stroke-width="2"
                          d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </button>
        </form>

        {{-- Login link --}}
        <p class="mt-6 text-center text-xs" style="color:#A3A3A3">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold transition-colors" style="color:#059669">
                Sign in
            </a>
        </p>

    </div>
</div>
</x-guest-layout>