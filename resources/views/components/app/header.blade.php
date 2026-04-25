@props([
    'title' => 'Dashboard',
])

@php
    $user = auth()->user();
    $initials = collect(explode(' ', $user?->name ?? 'U'))
        ->map(fn ($p) => mb_substr($p, 0, 1))
        ->take(2)
        ->join('');
@endphp

<header class="sticky top-0 z-20 bg-white border-b border-gray-200">
    <div class="h-16 px-4 sm:px-6 lg:px-8 flex items-center gap-4">
        {{-- Mobile sidebar toggle --}}
        <button @click="sidebarOpen = true"
            class="lg:hidden text-gray-600 hover:text-gray-900">
            <i class="ri-menu-line text-2xl"></i>
        </button>

        {{-- Title --}}
        <div class="min-w-0">
            <h1 class="text-lg font-semibold text-gray-900 truncate">{{ $title }}</h1>
        </div>

        {{-- Search --}}
        <div class="hidden md:flex flex-1 max-w-md mx-auto">
            <div class="relative w-full">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" placeholder="Search quotes, clients, models..."
                    class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800 focus:bg-white" />
                <kbd class="hidden lg:inline-flex absolute right-2 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 bg-white border border-gray-200 rounded">⌘K</kbd>
            </div>
        </div>

        {{-- Actions --}}
        <div class="ml-auto flex items-center gap-1.5">
            {{-- Help --}}
            <button class="hidden sm:inline-flex w-10 h-10 items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition"
                title="Help">
                <i class="ri-question-line text-xl"></i>
            </button>

            {{-- Notifications --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open"
                    class="w-10 h-10 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition relative"
                    title="Notifications">
                    <i class="ri-notification-3-line text-xl"></i>
                    <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
                </button>
                <div x-show="open" @click.outside="open = false" x-transition
                    class="absolute right-0 mt-2 w-80 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                    style="display: none;">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <span class="font-semibold text-sm">Notifications</span>
                        <button class="text-xs text-primary-800 hover:underline">Mark all read</button>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
                        <div class="px-4 py-3 hover:bg-gray-50">
                            <p class="text-sm text-gray-900">No new notifications</p>
                            <p class="text-xs text-gray-500 mt-0.5">You're all caught up.</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- User menu --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open"
                    class="flex items-center gap-2 pl-1 pr-2 py-1 hover:bg-gray-100 rounded-lg transition">
                    <span class="w-8 h-8 rounded-full bg-primary-800 text-white font-semibold text-sm flex items-center justify-center">
                        {{ strtoupper($initials) }}
                    </span>
                    <div class="hidden sm:block text-left">
                        <div class="text-sm font-semibold text-gray-900 leading-tight">{{ $user?->name }}</div>
                        <div class="text-xs text-gray-500 leading-tight">
                            {{ match ($user?->role) {
                                \App\Models\User::ROLE_SUPERADMIN   => 'Superadmin',
                                \App\Models\User::ROLE_TENANT_ADMIN => 'Admin',
                                \App\Models\User::ROLE_TENANT_USER  => 'Salesperson',
                                default                             => 'User',
                            } }}
                        </div>
                    </div>
                    <i class="ri-arrow-down-s-line text-gray-400"></i>
                </button>
                <div x-show="open" @click.outside="open = false" x-transition
                    class="absolute right-0 mt-2 w-56 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                    style="display: none;">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <p class="text-sm font-semibold text-gray-900 truncate">{{ $user?->name }}</p>
                        <p class="text-xs text-gray-500 truncate">{{ $user?->email }}</p>
                    </div>
                    <div class="py-1">
                        <a href="{{ route('profile.edit') }}"
                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="ri-user-settings-line"></i> Profile
                        </a>
                        <a href="#"
                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="ri-settings-3-line"></i> Settings
                        </a>
                    </div>
                    <div class="py-1 border-t border-gray-100">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit"
                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 text-left">
                                <i class="ri-logout-box-r-line"></i> Log out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
