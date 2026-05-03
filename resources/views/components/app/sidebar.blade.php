@php
    $user = auth()->user();
    $isSuperadmin = $user?->role === \App\Models\User::ROLE_SUPERADMIN;
    $unreadCount = $unreadNotificationsCount ?? 0;

    $userInitials = collect(explode(' ', $user?->name ?? 'U'))
        ->map(fn ($p) => mb_substr($p, 0, 1))
        ->take(2)
        ->join('');

    /*
     * Each item supports two extra shapes:
     *  - 'badge' string  → small pill on the right
     *  - 'children' array → collapsible group; activeAny is the array of
     *    parent route patterns that should keep the group open.
     */
    $tenantNav = [
        [
            'section' => 'Workspace',
            'items' => [
                ['label' => 'Dashboard',         'icon' => 'ri-dashboard-3-line',        'route' => 'dashboard',       'active' => 'dashboard'],
                ['label' => 'Quotes',            'icon' => 'ri-file-list-3-line',        'route' => 'quotes.index',    'active' => 'quotes.*'],
                ['label' => 'Clients',           'icon' => 'ri-user-smile-line',         'route' => 'clients.index',   'active' => 'clients.*'],
            ],
        ],
        [
            'section' => 'Catalogue',
            'items' => [
                ['label' => 'Models & variants', 'icon' => 'ri-sailboat-line',           'route' => 'catalogue.models',  'active' => 'catalogue.models'],
                ['label' => 'Brands',            'icon' => 'ri-building-4-line',         'route' => 'catalogue.brands',  'active' => 'catalogue.brands'],
                ['label' => 'Catalogue updates', 'icon' => 'ri-notification-badge-line', 'route' => 'catalogue.updates', 'active' => 'catalogue.updates'],
            ],
        ],
        [
            'section' => 'Settings',
            'items' => [
                ['label' => 'Company settings',  'icon' => 'ri-building-line',           'route' => 'company.settings', 'active' => 'company.settings'],
                [
                    'label'    => 'Email settings',
                    'icon'     => 'ri-mail-settings-line',
                    'activeAny' => ['email-templates.*', 'email-log.*'],
                    'children' => [
                        ['label' => 'Email templates', 'icon' => 'ri-mail-line',       'route' => 'email-templates.index', 'active' => 'email-templates.*'],
                        ['label' => 'Email log',       'icon' => 'ri-mail-check-line', 'route' => 'email-log.index',       'active' => 'email-log.*'],
                    ],
                ],
            ],
        ],
        [
            'section' => 'Activity',
            'items' => [
                ['label' => 'Notifications', 'icon' => 'ri-notification-3-line', 'route' => 'notifications.index', 'active' => 'notifications.*', 'badge' => $unreadCount > 0 ? ($unreadCount > 99 ? '99+' : (string) $unreadCount) : null],
            ],
        ],
    ];

    $superadminNav = [
        [
            'section' => 'Platform',
            'items' => [
                ['label' => 'Overview',        'icon' => 'ri-line-chart-line',      'route' => null, 'active' => 'admin.dashboard'],
                ['label' => 'Tenants',         'icon' => 'ri-store-2-line',         'route' => null, 'active' => 'admin.tenants.*'],
            ],
        ],
        [
            'section' => 'Global catalogue',
            'items' => [
                ['label' => 'Brands',          'icon' => 'ri-building-4-line',      'route' => null, 'active' => 'admin.brands.*'],
                ['label' => 'Models',          'icon' => 'ri-sailboat-line',        'route' => null, 'active' => 'admin.models.*'],
                ['label' => 'Variants',        'icon' => 'ri-layout-grid-line',     'route' => null, 'active' => 'admin.variants.*'],
                ['label' => 'Equipment',       'icon' => 'ri-tools-line',           'route' => null, 'active' => 'admin.equipment.*'],
                ['label' => 'Options',         'icon' => 'ri-list-check-2',         'route' => null, 'active' => 'admin.options.*'],
                ['label' => 'Bulk import',     'icon' => 'ri-upload-cloud-2-line',  'route' => null, 'active' => 'admin.import.*'],
            ],
        ],
        [
            'section' => 'Activity',
            'items' => [
                ['label' => 'Update log',      'icon' => 'ri-history-line',         'route' => null, 'active' => 'admin.log'],
            ],
        ],
    ];

    $nav = $isSuperadmin ? $superadminNav : $tenantNav;
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 w-72 bg-primary-900 text-white flex flex-col transition-transform duration-200 ease-out lg:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">

    {{-- Brand --}}
    <div class="h-16 flex items-center gap-3 px-5 border-b border-white/10">
        <x-application-logo class="w-9 h-9 shrink-0" />
        <div class="leading-tight">
            <div class="font-bold text-lg">Nautiqs</div>
            <div class="text-[11px] text-white/60 uppercase tracking-wider">
                {{ $isSuperadmin ? 'Superadmin' : 'Dealership' }}
            </div>
        </div>
        <button @click="sidebarOpen = false" class="ml-auto lg:hidden text-white/70 hover:text-white">
            <i class="ri-close-line text-2xl"></i>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-6">
        @foreach ($nav as $group)
            <div>
                <div class="px-3 mb-2 text-[11px] font-semibold uppercase tracking-wider text-white/40">
                    {{ $group['section'] }}
                </div>
                <ul class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        @if (! empty($item['children']))
                            {{-- Collapsible group (e.g. Email settings) --}}
                            @php
                                $groupActive = collect($item['activeAny'] ?? [])->contains(fn ($p) => request()->routeIs($p));
                            @endphp
                            <li x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }">
                                <button type="button" @click="open = !open"
                                    class="group w-full flex items-center gap-3 px-3 py-2 rounded-lg text-base font-medium transition
                                        {{ $groupActive ? 'bg-white/10 text-white' : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                    <i class="{{ $item['icon'] }} text-xl"></i>
                                    <span class="flex-1 text-left">{{ $item['label'] }}</span>
                                    <i class="ri-arrow-down-s-line text-base transition-transform"
                                        :class="open ? 'rotate-180' : ''"></i>
                                </button>
                                <ul x-show="open" x-cloak x-transition.opacity class="mt-0.5 ml-4 pl-3 border-l border-white/10 space-y-0.5">
                                    @foreach ($item['children'] as $child)
                                        @php
                                            $childActive = $child['active'] && request()->routeIs($child['active']);
                                            $childHref = $child['route'] ? route($child['route']) : '#';
                                        @endphp
                                        <li>
                                            <a href="{{ $childHref }}"
                                                class="flex items-center gap-3 px-3 py-1.5 rounded-lg text-base font-medium transition
                                                    {{ $childActive
                                                        ? 'bg-white/15 text-white'
                                                        : 'text-white/65 hover:bg-white/10 hover:text-white' }}">
                                                <i class="{{ $child['icon'] }} text-lg"></i>
                                                <span>{{ $child['label'] }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            @php
                                $active = $item['active'] && request()->routeIs($item['active']);
                                $href = $item['route'] ? route($item['route']) : '#';
                            @endphp
                            <li>
                                <a href="{{ $href }}"
                                    class="group flex items-center gap-3 px-3 py-2 rounded-lg text-base font-medium transition
                                        {{ $active
                                            ? 'bg-white/15 text-white'
                                            : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                    <i class="{{ $item['icon'] }} text-xl"></i>
                                    <span class="flex-1">{{ $item['label'] }}</span>
                                    @if (!empty($item['badge']))
                                        <span class="ml-auto min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-[11px] font-bold flex items-center justify-center">
                                            {{ $item['badge'] }}
                                        </span>
                                    @endif
                                    @if (empty($item['route']))
                                        <i class="ri-lock-line text-xs text-white/30" title="Coming soon"></i>
                                    @endif
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- User card with dropup menu --}}
    @if ($user)
        <div class="border-t border-white/10 p-3" x-data="{ open: false }">
            <div x-show="open" x-cloak x-transition.opacity
                @click.outside="open = false"
                class="mb-2 bg-white text-gray-900 rounded-lg shadow-lg overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ $user->email }}</p>
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

            <button type="button" @click="open = !open"
                class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-white/10 transition">
                <span class="w-9 h-9 rounded-full bg-white/15 text-white font-semibold text-sm flex items-center justify-center shrink-0">
                    {{ strtoupper($userInitials) }}
                </span>
                <div class="flex-1 min-w-0 text-left">
                    <p class="text-sm font-semibold text-white truncate">{{ $user->name }}</p>
                    <p class="text-xs text-white/60 truncate">{{ $user->email }}</p>
                </div>
                <i class="ri-arrow-up-s-line text-white/70 text-lg transition-transform"
                    :class="open ? 'rotate-180' : ''"></i>
            </button>
        </div>
    @endif
</aside>
