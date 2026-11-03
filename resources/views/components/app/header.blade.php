@props([
    'title' => 'Dashboard',
])

@php
    $user = auth()->user();
    // $notifications and $unreadNotificationsCount are injected via the
    // View::composer in AppServiceProvider.
    $notifications = $notifications ?? collect();
    $unreadCount = $unreadNotificationsCount ?? 0;
    $currentLocale = app()->getLocale();

    $colorMap = [
        'primary' => 'bg-primary-50 text-primary-800',
        'emerald' => 'bg-emerald-50 text-emerald-700',
        'amber'   => 'bg-amber-50 text-amber-700',
        'red'     => 'bg-red-50 text-red-700',
        'gray'    => 'bg-gray-100 text-gray-700',
    ];
@endphp

{{-- Figma "Header" (node 19:94): 56 px high, 24 px sides, 36 px controls. --}}
<header class="sticky top-0 z-20 bg-white border-b border-nq-border-2">
    <div class="h-14 px-4 sm:px-6 flex items-center gap-2.5">
        {{-- Mobile sidebar toggle --}}
        <button @click="sidebarOpen = true" class="xl:hidden text-nq-text-2 hover:text-nq-text" aria-label="{{ __('Menu') }}">
            <i class="ph-bold ph-list text-xl"></i>
        </button>

        {{-- Title --}}
        <div class="min-w-0 flex-1 flex items-center gap-2.5">
            <i class="ph-bold ph-squares-four text-base text-nq-text-2 hidden sm:inline"></i>
            <h1 class="text-sm leading-[22px] font-medium text-nq-text truncate">{{ $title }}</h1>
        </div>

        {{-- Search trigger (Cmd/Ctrl+K) — dealer data only, so not for brand managers --}}
        @unless ($user?->isBrandManager())
            <button type="button" @click="$dispatch('open-search')"
                class="hidden md:flex w-[300px] h-9 items-center gap-2 px-3 bg-white border border-nq-border rounded-lg text-left hover:border-primary-800 transition-colors">
                <i class="ph-bold ph-magnifying-glass text-base text-nq-text-3"></i>
                <span class="flex-1 truncate text-sm leading-[22px] text-nq-text-3">{{ __('Search…') }}</span>
                <kbd class="px-1.5 py-0.5 bg-nq-muted border border-nq-border rounded-[5px] font-mono text-[11px] leading-4 font-medium text-nq-text-2">⌘K</kbd>
            </button>
        @endunless

        {{-- Native locale switcher — hits /locale/{lang}, sets a cookie, bounces back. --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open"
                class="h-9 px-[9px] inline-flex items-center gap-1.5 bg-white border border-nq-border rounded-lg hover:bg-nq-subtle transition-colors"
                :title="'{{ __('Language') }}'">
                <i class="ph-bold ph-translate text-[18px] text-nq-text-2"></i>
                <span class="text-sm leading-[22px] font-medium text-nq-text-2">{{ strtoupper($currentLocale) }}</span>
                <i class="ph-bold ph-caret-down text-xs text-nq-text-3"></i>
            </button>
            <div x-show="open" @click.outside="open = false" x-cloak x-transition
                class="absolute right-0 mt-2 w-44 bg-white border border-nq-border rounded-xl shadow-lg overflow-hidden">
                <a href="{{ route('locale.switch', 'fr') }}"
                    class="w-full text-left px-4 py-2 text-sm flex items-center justify-between hover:bg-nq-subtle {{ $currentLocale === 'fr' ? 'text-primary-800 font-medium' : 'text-nq-text' }}">
                    <span>Français</span>
                    @if ($currentLocale === 'fr') <i class="ph-bold ph-check"></i> @endif
                </a>
                <a href="{{ route('locale.switch', 'en') }}"
                    class="w-full text-left px-4 py-2 text-sm flex items-center justify-between hover:bg-nq-subtle {{ $currentLocale === 'en' ? 'text-primary-800 font-medium' : 'text-nq-text' }}">
                    <span>English</span>
                    @if ($currentLocale === 'en') <i class="ph-bold ph-check"></i> @endif
                </a>
            </div>
        </div>

        {{-- Notifications --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open"
                class="relative w-[38px] h-9 inline-flex items-center justify-center bg-white border border-nq-border rounded-lg text-nq-text-2 hover:bg-nq-subtle hover:text-nq-text transition-colors"
                :title="'{{ __('Notifications') }}'">
                <i class="ph-bold ph-bell text-[18px]"></i>
                @if ($unreadCount > 0)
                    <span class="absolute top-[7px] right-[9px] w-[7px] h-[7px] rounded-full bg-nq-error ring-2 ring-white"></span>
                @endif
            </button>
            <div x-show="open" @click.outside="open = false" x-transition
                class="absolute right-0 mt-2 w-96 max-w-[calc(100vw-2rem)] bg-white border border-nq-border rounded-xl shadow-lg overflow-hidden"
                style="display: none;">
                <div class="px-4 py-3 border-b border-nq-border-2 flex items-center justify-between">
                    <div>
                        <p class="font-medium text-sm text-nq-text">{{ __('Notifications') }}</p>
                        @if ($unreadCount > 0)
                            <p class="text-xs text-nq-text-3">{{ $unreadCount }} {{ __('unread') }}</p>
                        @endif
                    </div>
                    @if ($unreadCount > 0)
                        <form method="POST" action="{{ route('notifications.read-all') }}">
                            @csrf
                            <button class="text-xs font-medium text-primary-800 hover:underline">{{ __('Mark all read') }}</button>
                        </form>
                    @endif
                </div>
                <div class="divide-y divide-nq-border-2 max-h-96 overflow-y-auto">
                    @forelse ($notifications as $n)
                        @php $iconBg = $colorMap[$n->color ?? 'primary'] ?? $colorMap['primary']; @endphp
                        <form method="POST" action="{{ route('notifications.read', $n->_id) }}" class="block">
                            @csrf
                            <button type="submit"
                                class="w-full text-left px-4 py-3 flex items-start gap-3 hover:bg-nq-subtle transition {{ $n->read_at ? '' : 'bg-nq-primary-bg/50' }}">
                                <span class="w-8 h-8 shrink-0 rounded-lg {{ $iconBg }} flex items-center justify-center">
                                    <i class="{{ $n->icon ?? 'ri-notification-3-line' }} text-base"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="text-sm font-medium text-nq-text truncate">{{ $n->displayTitle() }}</p>
                                        @if (! $n->read_at)
                                            <span class="w-2 h-2 rounded-full bg-primary-800 shrink-0"></span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-nq-text-2 mt-0.5 line-clamp-2">{{ $n->message }}</p>
                                    <p class="text-[11px] text-nq-text-3 mt-1">{{ $n->created_at?->diffForHumans() }}</p>
                                </div>
                            </button>
                        </form>
                    @empty
                        <div class="px-4 py-8 text-center">
                            <i class="ph-bold ph-bell-slash text-3xl text-nq-border"></i>
                            <p class="text-sm text-nq-text-2 mt-2">{{ __('No notifications') }}</p>
                            <p class="text-xs text-nq-text-3 mt-0.5">{{ __("You're all caught up.") }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <span class="hidden sm:block w-px h-6 bg-nq-border"></span>

        {{-- User menu --}}
        <div x-data="{ open: false }" class="relative">
            <button @click="open = !open"
                class="flex items-center gap-2.5 pl-1 pr-2 py-1 rounded-lg hover:bg-nq-subtle transition-colors">
                <span class="w-8 h-8 rounded-full bg-nq-primary-bg text-primary-800 font-semibold text-xs flex items-center justify-center">
                    {{ mb_strtoupper(mb_substr($user?->name ?? 'U', 0, 1)) }}
                </span>
                <div class="hidden sm:block text-left">
                    <div class="text-sm leading-[22px] font-medium text-nq-text">{{ explode(' ', trim($user?->name ?? ''))[0] }}</div>
                    <div class="text-xs leading-5 text-nq-text-3">
                        {{ match ($user?->role) {
                            \App\Models\User::ROLE_SUPERADMIN    => __('Superadmin'),
                            \App\Models\User::ROLE_TENANT_ADMIN  => __('Dealer'),
                            \App\Models\User::ROLE_TENANT_USER   => __('Salesperson'),
                            \App\Models\User::ROLE_BRAND_MANAGER => __('Brand manager'),
                            default                              => __('User'),
                        } }}
                    </div>
                </div>
                <i class="ph-bold ph-caret-down text-sm text-nq-text-3"></i>
            </button>
            <div x-show="open" @click.outside="open = false" x-transition
                class="absolute right-0 mt-2 w-56 bg-white border border-nq-border rounded-xl shadow-lg overflow-hidden"
                style="display: none;">
                <div class="px-4 py-3 border-b border-nq-border-2">
                    <p class="text-sm font-medium text-nq-text truncate">{{ $user?->name }}</p>
                    <p class="text-xs text-nq-text-3 truncate">{{ $user?->email }}</p>
                </div>
                <div class="py-1">
                    <a href="{{ route('profile.edit') }}"
                        class="flex items-center gap-3 px-4 py-2 text-sm text-nq-text hover:bg-nq-subtle">
                        <i class="ph-bold ph-user text-base text-nq-text-2"></i> {{ __('Profile') }}
                    </a>
                    @unless ($user?->isBrandManager())
                        <a href="{{ route('company.settings') }}"
                            class="flex items-center gap-3 px-4 py-2 text-sm text-nq-text hover:bg-nq-subtle">
                            <i class="ph-bold ph-gear-six text-base text-nq-text-2"></i> {{ __('Settings') }}
                        </a>
                    @endunless
                </div>
                <div class="py-1 border-t border-nq-border-2">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="w-full flex items-center gap-3 px-4 py-2 text-sm text-nq-error-text hover:bg-nq-error-bg text-left">
                            <i class="ph-bold ph-sign-out text-base"></i> {{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
