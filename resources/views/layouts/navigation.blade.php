<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <x-application-logo class="block h-9 w-auto fill-current text-blue-600" />
                        <span class="font-bold text-lg text-gray-900 dark:text-gray-100 hidden sm:inline">Laporan</span>
                    </a>
                </div>

                <div class="hidden space-x-1 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <x-heroicon-o-squares-2x2 class="w-4 h-4 me-1" />
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('imports.index')" :active="request()->routeIs('imports.*')">
                        <x-heroicon-o-arrow-up-tray class="w-4 h-4 me-1" />
                        {{ __('Import') }}
                    </x-nav-link>
                    <x-nav-link :href="route('data.index')" :active="request()->routeIs('data.*')">
                        <x-heroicon-o-table-cells class="w-4 h-4 me-1" />
                        {{ __('Data') }}
                    </x-nav-link>
                    <x-nav-link :href="route('otomasi.bimwin')" :active="request()->routeIs('otomasi.*')">
                        <x-heroicon-o-bolt class="w-4 h-4 me-1" />
                        {{ __('Otomasi') }}
                    </x-nav-link>
                    <x-nav-link :href="route('cetak-nb.index')" :active="request()->routeIs('cetak-nb.*')">
                        <x-heroicon-o-printer class="w-4 h-4 me-1" />
                        {{ __('Cetak NB') }}
                    </x-nav-link>
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                        <x-heroicon-o-document-text class="w-4 h-4 me-1" />
                        {{ __('Laporan') }}
                    </x-nav-link>
                    @if (Auth::user()->isAdmin())
                        <x-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.*')">
                            <x-heroicon-o-cog-6-tooth class="w-4 h-4 me-1" />
                            {{ __('Admin') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-2">
                <x-theme-toggle />
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-700 hover:text-gray-700 dark:hover:text-gray-200 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.info')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" :aria-expanded="open" aria-controls="mobile-menu" aria-label="Buka menu navigasi" class="inline-flex items-center justify-center p-2.5 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-700 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div id="mobile-menu" :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <x-heroicon-o-squares-2x2 class="w-5 h-5 shrink-0" />
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('imports.index')" :active="request()->routeIs('imports.*')">
                <x-heroicon-o-arrow-up-tray class="w-5 h-5 shrink-0" />
                {{ __('Import') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('data.index')" :active="request()->routeIs('data.*')">
                <x-heroicon-o-table-cells class="w-5 h-5 shrink-0" />
                {{ __('Data') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('otomasi.bimwin')" :active="request()->routeIs('otomasi.*')">
                <x-heroicon-o-bolt class="w-5 h-5 shrink-0" />
                {{ __('Otomasi') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('cetak-nb.index')" :active="request()->routeIs('cetak-nb.*')">
                <x-heroicon-o-printer class="w-5 h-5 shrink-0" />
                {{ __('Cetak NB') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                <x-heroicon-o-document-text class="w-5 h-5 shrink-0" />
                {{ __('Laporan') }}
            </x-responsive-nav-link>
            @if (Auth::user()->isAdmin())
                <x-responsive-nav-link :href="route('admin.index')" :active="request()->routeIs('admin.*')">
                    <x-heroicon-o-cog-6-tooth class="w-5 h-5 shrink-0" />
                    {{ __('Admin') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-700">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500 dark:text-gray-400">{{ Auth::user()->email }}</div>
            </div>
            <div class="flex items-center justify-between px-4 py-2">
                <span class="text-sm font-medium text-gray-600 dark:text-gray-400">{{ __('Tema') }}</span>
                <x-theme-toggle />
            </div>
            <div class="mt-1 space-y-1">
                <x-responsive-nav-link :href="route('profile.info')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
