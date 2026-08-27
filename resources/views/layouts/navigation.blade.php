@php
    $unreadCount = auth()->user()?->unreadNotificationCount() ?? 0;
@endphp

<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-6 sm:-my-px sm:ms-8 lg:flex">
                    <x-nav-link icon="home" :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('app.dashboard') }}
                    </x-nav-link>
                    <x-nav-link icon="library" :href="route('papers.index')" :active="request()->routeIs('papers.*')">
                        {{ __('app.library') }}
                    </x-nav-link>
                    <x-nav-link icon="collection" :href="route('collections.index')" :active="request()->routeIs('collections.*')">
                        {{ __('app.collections') }}
                    </x-nav-link>
                    <x-nav-link icon="tag" :href="route('tags.index')" :active="request()->routeIs('tags.*')">
                        {{ __('app.tags') }}
                    </x-nav-link>
                    <x-nav-link icon="search" :href="route('search')" :active="request()->routeIs('search')">
                        {{ __('app.search') }}
                    </x-nav-link>
                    <x-nav-link icon="chart" :href="route('analytics')" :active="request()->routeIs('analytics')">
                        {{ __('app.analytics') }}
                    </x-nav-link>
                    @if (auth()->user()?->isAdmin())
                        <x-nav-link icon="shield" :href="route('admin.index')" :active="request()->routeIs('admin.*')">
                            {{ __('app.admin') }}
                        </x-nav-link>
                    @endif
                </div>
            </div>

            <div class="hidden lg:flex lg:items-center lg:ms-6 gap-2">
                {{-- Notification bell. The badge is server-rendered on load and
                     refreshed by a light poll (see the script below). --}}
                <a href="{{ route('notifications.index') }}"
                   class="relative p-2 rounded-md text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                   aria-label="{{ __('app.notifications') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span id="notif-badge"
                          class="absolute top-1 right-1 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center {{ $unreadCount > 0 ? '' : 'hidden' }}">
                        {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                    </span>
                </a>

                <!-- Settings Dropdown -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::user()->name }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('app.profile') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('settings.edit')">
                            {{ __('app.settings') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('app.log_out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center lg:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden lg:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link icon="home" :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('app.dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link icon="library" :href="route('papers.index')" :active="request()->routeIs('papers.*')">
                {{ __('app.library') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link icon="collection" :href="route('collections.index')" :active="request()->routeIs('collections.*')">
                {{ __('app.collections') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link icon="tag" :href="route('tags.index')" :active="request()->routeIs('tags.*')">
                {{ __('app.tags') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link icon="search" :href="route('search')" :active="request()->routeIs('search')">
                {{ __('app.search') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link icon="chart" :href="route('analytics')" :active="request()->routeIs('analytics')">
                {{ __('app.analytics') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link icon="bell" :href="route('notifications.index')" :active="request()->routeIs('notifications.*')">
                {{ __('app.notifications') }} @if ($unreadCount > 0) ({{ $unreadCount }}) @endif
            </x-responsive-nav-link>
            @if (auth()->user()?->isAdmin())
                <x-responsive-nav-link icon="shield" :href="route('admin.index')" :active="request()->routeIs('admin.*')">
                    {{ __('app.admin') }}
                </x-responsive-nav-link>
            @endif
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('app.profile') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('settings.edit')">
                    {{ __('app.settings') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('app.log_out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>

<script>
    // Refresh the unread badge periodically. Polling (not websockets) is the
    // documented approach; a failed poll is ignored so a blip never spams the
    // console or breaks the page.
    (function () {
        const badge = document.getElementById('notif-badge');
        if (!badge) return;

        async function refresh() {
            try {
                const res = await fetch(@json(route('notifications.count')), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!res.ok) return;
                const { count } = await res.json();
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.toggle('hidden', !count);
            } catch (_) { /* offline or navigating away — ignore */ }
        }

        setInterval(refresh, 60000);
    })();
</script>
