@props([
    'title' => 'Dashboard',
])

@php
    $user = auth()->user();
    $initials = collect(explode(' ', $user?->name ?? 'U'))
        ->map(fn ($p) => mb_substr($p, 0, 1))
        ->take(2)
        ->join('');
    // $notifications and $unreadNotificationsCount are injected via the
    // View::composer in AppServiceProvider.
    $notifications = $notifications ?? collect();
    $unreadCount = $unreadNotificationsCount ?? 0;

    $colorMap = [
        'primary' => 'bg-primary-50 text-primary-800',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'amber'   => 'bg-amber-50 text-amber-700',
        'red'     => 'bg-red-50 text-red-700',
        'gray'    => 'bg-gray-100 text-gray-700',
    ];
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

        {{-- Search trigger (Cmd/Ctrl+K) --}}
        <div class="hidden md:flex flex-1 max-w-md mx-auto">
            <button type="button"
                @click="$dispatch('open-search')"
                class="group relative w-full text-left">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <div class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-500 hover:bg-white hover:border-primary-800 transition">
                    Search quotes, clients, models...
                </div>
                <kbd class="hidden lg:inline-flex absolute right-2 top-1/2 -translate-y-1/2 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 bg-white border border-gray-200 rounded">⌘K</kbd>
            </button>
        </div>

        {{-- Actions --}}
        <div class="ml-auto flex items-center gap-1.5">
            {{-- Notifications --}}
            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open"
                    class="w-10 h-10 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition relative"
                    title="Notifications">
                    <i class="ri-notification-3-line text-xl"></i>
                    @if ($unreadCount > 0)
                        <span class="absolute top-1.5 right-1.5 min-w-[18px] h-[18px] px-1 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                            {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                        </span>
                    @endif
                </button>
                <div x-show="open" @click.outside="open = false" x-transition
                    class="absolute right-0 mt-2 w-96 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                    style="display: none;">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="font-semibold text-sm">Notifications</p>
                            @if ($unreadCount > 0)
                                <p class="text-xs text-gray-500">{{ $unreadCount }} unread</p>
                            @endif
                        </div>
                        @if ($unreadCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}">
                                @csrf
                                <button class="text-xs font-medium text-primary-800 hover:underline">Mark all read</button>
                            </form>
                        @endif
                    </div>
                    <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                        @forelse ($notifications as $n)
                            @php $iconBg = $colorMap[$n->color ?? 'primary'] ?? $colorMap['primary']; @endphp
                            <form method="POST" action="{{ route('notifications.read', $n->_id) }}" class="block">
                                @csrf
                                <button type="submit"
                                    class="w-full text-left px-4 py-3 flex items-start gap-3 hover:bg-gray-50 transition {{ $n->read_at ? '' : 'bg-primary-50/40' }}">
                                    <span class="w-9 h-9 shrink-0 rounded-lg {{ $iconBg }} flex items-center justify-center">
                                        <i class="{{ $n->icon ?? 'ri-notification-3-line' }} text-base"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <p class="text-sm font-medium text-gray-900 truncate">{{ $n->title }}</p>
                                            @if (! $n->read_at)
                                                <span class="w-2 h-2 rounded-full bg-primary-800 shrink-0"></span>
                                            @endif
                                        </div>
                                        <p class="text-xs text-gray-600 mt-0.5 line-clamp-2">{{ $n->message }}</p>
                                        <p class="text-[11px] text-gray-400 mt-1">{{ $n->created_at?->diffForHumans() }}</p>
                                    </div>
                                </button>
                            </form>
                        @empty
                            <div class="px-4 py-8 text-center">
                                <i class="ri-notification-off-line text-3xl text-gray-300"></i>
                                <p class="text-sm text-gray-600 mt-2">No notifications yet</p>
                                <p class="text-xs text-gray-400 mt-0.5">You're all caught up.</p>
                            </div>
                        @endforelse
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
                                \App\Models\User::ROLE_TENANT_ADMIN => 'Dealer',
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
                        <a href="{{ route('company.settings') }}"
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
