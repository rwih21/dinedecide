<nav x-data="{ open: false }" class="bg-white/80 backdrop-blur-md border-b border-neutral-200 sticky top-0 z-50">
    <div class="max-w-2xl mx-auto px-4">
        <div class="flex justify-between h-16">
            
            {{-- Left Side: Logo Only --}}
            <div class="flex items-center">
                <a href="{{ route('restaurants.index') }}" class="shrink-0 flex items-center gap-2 group">
                    <div class="w-8 h-8 rounded-xl bg-emerald-600 flex items-center justify-center text-white font-black group-hover:scale-105 transition-transform">
                        D
                    </div>
                    <span class="text-lg font-black tracking-tight text-neutral-900">
                        Dine<span class="text-emerald-600">Decide</span>
                    </span>
                </a>
            </div>

            {{-- Right Side: User Controls & Admin --}}
            <div class="hidden sm:flex sm:items-center gap-3">
                @auth
                    {{-- 🛡️ ADMIN BUTTON --}}
                    @if(auth()->user()->is_admin)
                        <a href="{{ route('admin.promoted.index') }}" 
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white text-xs font-bold rounded-lg transition-colors shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            Admin Panel
                        </a>
                        <span class="w-px h-5 bg-neutral-200 mx-1"></span>
                    @endif

                    {{-- User Dropdown --}}
                    <div class="relative" x-data="{ dropdownOpen: false }">
                        <button @click="dropdownOpen = !dropdownOpen" @click.away="dropdownOpen = false"
                                class="flex items-center gap-2 text-sm font-semibold text-neutral-600 hover:text-neutral-900 transition-colors">
                            {{ auth()->user()->name }}
                            <svg class="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        {{-- Dropdown Menu --}}
                        <div x-show="dropdownOpen" 
                             x-transition.opacity.duration.200ms
                             class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-neutral-100 py-1"
                             style="display: none;">
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 hover:text-emerald-600">
                                Profile
                            </a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-neutral-600 hover:bg-neutral-50 hover:text-rose-600">
                                    Log Out
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-semibold text-neutral-600 hover:text-neutral-900">Log in</a>
                    <a href="{{ route('register') }}" class="text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 px-4 py-2 rounded-xl transition-colors">Register</a>
                @endauth
            </div>

            {{-- Mobile Menu Hamburger --}}
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-lg text-neutral-400 hover:text-neutral-500 hover:bg-neutral-100 transition-colors">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Mobile Menu Dropdown (Only Account Controls Now) --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden border-t border-neutral-200 bg-white">
        @auth
        <div class="pt-4 pb-1">
            <div class="px-4 border-b border-neutral-100 pb-3 mb-2">
                <div class="font-bold text-base text-neutral-800">{{ auth()->user()->name }}</div>
                <div class="font-medium text-sm text-neutral-500">{{ auth()->user()->email }}</div>
            </div>

            <div class="space-y-1">
                @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.promoted.index') }}" class="block pl-3 pr-4 py-2 text-base font-bold text-neutral-900 bg-neutral-50 hover:bg-neutral-100 transition-colors">
                        🛡️ Admin Panel
                    </a>
                @endif
                <a href="{{ route('profile.edit') }}" class="block pl-3 pr-4 py-2 text-base font-medium text-neutral-600 hover:text-neutral-800 hover:bg-neutral-50 transition-colors">
                    Profile
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full text-left pl-3 pr-4 py-2 text-base font-medium text-rose-600 hover:bg-rose-50 transition-colors">
                        Log Out
                    </button>
                </form>
            </div>
        </div>
        @else
        <div class="pt-2 pb-3 space-y-1 px-4">
            <a href="{{ route('login') }}" class="block text-center py-2 text-base font-semibold text-neutral-600 border border-neutral-200 rounded-xl mb-2">Log in</a>
            <a href="{{ route('register') }}" class="block text-center py-2 text-base font-semibold text-white bg-emerald-600 rounded-xl">Register</a>
        </div>
        @endauth
    </div>
</nav>