@php
    $user = auth()->user();
    $isSuperadmin = $user?->role === \App\Models\User::ROLE_SUPERADMIN;
    $isTenantAdmin = $user?->role === \App\Models\User::ROLE_TENANT_ADMIN;
    $isBrand = $user?->isBrandManager() ?? false;
    $brandAccount = $isBrand ? $user->brandAccount : null;
    $unreadCount = $unreadNotificationsCount ?? 0;
    $brandInvites = $pendingBrandInvites ?? 0;

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
                ['label' => 'Clients',           'icon' => 'ri-account-box-line',        'route' => 'clients.index',   'active' => 'clients.*'],
            ],
        ],
        [
            'section' => 'Catalogue',
            'items' => [
                ['label' => 'Catalogue',         'icon' => 'ri-sailboat-line',           'route' => 'catalogue.models',  'active' => 'catalogue.models'],
                [
                    'label'     => 'Engines',
                    'icon'      => 'ri-settings-3-line',
                    'activeAny' => ['engines.*', 'engine-accessories.*'],
                    'children'  => [
                        ['label' => 'Engines',     'icon' => 'ri-settings-3-line', 'route' => 'engines.index',            'active' => 'engines.*'],
                        ['label' => 'Kits & propellers', 'icon' => 'ri-tools-line', 'route' => 'engine-accessories.index', 'active' => 'engine-accessories.*'],
                    ],
                ],
                // Third-party add-ons (GPS, audio, fishing gear) sold on any quote.
                ['label' => 'Accessories',       'icon' => 'ri-shopping-bag-3-line',     'route' => 'accessories.index', 'active' => 'accessories.*'],
                // Brands that publish their catalogue to this dealership.
                ['label' => 'Partner brands',    'icon' => 'ri-links-line',              'route' => 'partner-brands.index', 'active' => 'partner-brands.*', 'badge' => $brandInvites > 0 ? (string) $brandInvites : null],
            ],
        ],
        [
            'section' => 'Settings',
            'items' => array_filter([
                // Also in the avatar menu, but nobody looks for their own
                // profile up there when every other setting is down here.
                ['label' => 'Profile',           'icon' => 'ri-user-settings-line',      'route' => 'profile.edit',     'active' => 'profile.*'],
                ['label' => 'Company settings',  'icon' => 'ri-building-line',           'route' => 'company.settings', 'active' => 'company.settings'],
                $isTenantAdmin
                    ? ['label' => 'Team',        'icon' => 'ri-team-line',               'route' => 'team.index',       'active' => 'team.*']
                    : null,
                [
                    'label'    => 'Email settings',
                    'icon'     => 'ri-mail-settings-line',
                    'activeAny' => ['email-templates.*', 'email-log.*'],
                    'children' => [
                        ['label' => 'Email templates', 'icon' => 'ri-mail-line',       'route' => 'email-templates.index', 'active' => 'email-templates.*'],
                        ['label' => 'Email log',       'icon' => 'ri-mail-check-line', 'route' => 'email-log.index',       'active' => 'email-log.*'],
                    ],
                ],
            ]),
        ],
        [
            'section' => 'Activity',
            'items' => [
                ['label' => 'Notifications', 'icon' => 'ri-notification-3-line', 'route' => 'notifications.index', 'active' => 'notifications.*', 'badge' => $unreadCount > 0 ? ($unreadCount > 99 ? '99+' : (string) $unreadCount) : null],
            ],
        ],
    ];

    // Pending registration requests — red pill on the superadmin nav entry.
    $pendingAccountRequests = $isSuperadmin
        ? \App\Models\AccountRequest::where('status', \App\Models\AccountRequest::STATUS_PENDING)->count()
        : 0;

    $superadminNav = [
        [
            'section' => 'Platform',
            'items' => [
                ['label' => 'Overview',        'icon' => 'ri-line-chart-line',      'route' => 'admin.dashboard',  'active' => 'admin.dashboard'],
                ['label' => 'Dealers',         'icon' => 'ri-store-2-line',         'route' => 'admin.dealers.index', 'active' => 'admin.dealers.*'],
                ['label' => 'Account requests', 'icon' => 'ri-user-received-line',  'route' => 'admin.account-requests.index', 'active' => 'admin.account-requests.*', 'badge' => $pendingAccountRequests > 0 ? (string) $pendingAccountRequests : null],
                ['label' => 'Brand accounts',  'icon' => 'ri-award-line',           'route' => 'admin.brand-accounts.index', 'active' => 'admin.brand-accounts.*'],
            ],
        ],
        [
            'section' => 'Global catalogue',
            'items' => [
                ['label' => 'Brands',          'icon' => 'ri-building-4-line',      'route' => 'admin.brands.index',    'active' => 'admin.brands.*'],
                ['label' => 'Models',          'icon' => 'ri-sailboat-line',        'route' => 'admin.models.index',    'active' => 'admin.models.*'],
                ['label' => 'Variants',        'icon' => 'ri-layout-grid-line',     'route' => 'admin.variants.index',  'active' => 'admin.variants.*'],
                ['label' => 'Engines',         'icon' => 'ri-settings-3-line',      'route' => 'admin.engines.index',   'active' => 'admin.engines.*'],
                ['label' => 'Equipment',       'icon' => 'ri-tools-line',           'route' => 'admin.equipment.index', 'active' => 'admin.equipment.*'],
                ['label' => 'Options',         'icon' => 'ri-list-check-2',         'route' => 'admin.options.index',   'active' => 'admin.options.*'],
            ],
        ],
        [
            'section' => 'Customisation',
            'items' => [
                ['label' => 'Settings',        'icon' => 'ri-settings-3-line',      'route' => 'admin.settings.edit',    'active' => 'admin.settings.*'],
                ['label' => 'Dictionary',      'icon' => 'ri-translate-2',          'route' => 'admin.dictionary.index', 'active' => 'admin.dictionary.*'],
            ],
        ],
        [
            'section' => 'Activity',
            'items' => [
                ['label' => 'Activity log',    'icon' => 'ri-shield-check-line',    'route' => 'admin.audit.index',   'active' => 'admin.audit.*'],
                ['label' => 'Backups',         'icon' => 'ri-database-2-line',      'route' => 'admin.backups.index', 'active' => 'admin.backups.*'],
            ],
        ],
    ];

    // Brand portal — a brand manager sees only these screens.
    $brandNav = [
        [
            'section' => null,
            'items' => [
                ['label' => 'Dashboard',       'icon' => 'ri-dashboard-3-line',     'route' => 'brand.dashboard', 'active' => 'brand.dashboard'],
                $brandAccount?->isEngine()
                    ? ['label' => 'Catalogue', 'icon' => 'ri-settings-3-line',      'route' => 'brand.engines.index',   'active' => ['brand.engines.*', 'brand.transfer.*']]
                    : ['label' => 'Catalogue', 'icon' => 'ri-sailboat-line',        'route' => 'brand.catalogue.index', 'active' => ['brand.catalogue.*', 'brand.transfer.*']],
                ['label' => 'Brands',          'icon' => 'ri-building-4-line',      'route' => 'brand.brands.index',  'active' => 'brand.brands.*'],
                ['label' => 'Price levels',    'icon' => 'ri-price-tag-3-line',     'route' => 'brand.levels.edit',   'active' => 'brand.levels.*'],
                ['label' => 'Dealers',         'icon' => 'ri-store-2-line',         'route' => 'brand.dealers.index', 'active' => 'brand.dealers.*'],
                ['label' => 'Quotes',          'icon' => 'ri-file-list-3-line',     'route' => 'brand.quotes.index',  'active' => 'brand.quotes.*'],
            ],
        ],
        [
            'section' => 'Settings',
            'collapsible' => true,
            'items' => [
                ['label' => 'Team',            'icon' => 'ri-team-line',            'route' => 'brand.team.index', 'active' => 'brand.team.*'],
                ['label' => 'Profile',         'icon' => 'ri-user-settings-line',   'route' => 'profile.edit',     'active' => 'profile.*'],
                ['label' => 'Notifications',   'icon' => 'ri-notification-3-line',  'route' => 'notifications.index', 'active' => 'notifications.*', 'badge' => $unreadCount > 0 ? ($unreadCount > 99 ? '99+' : (string) $unreadCount) : null],
            ],
        ],
    ];

    $nav = $isSuperadmin ? $superadminNav : ($isBrand ? $brandNav : $tenantNav);

    // Brand portal: the account's brands, each a shortcut to its catalogue.
    $myBrands = $isBrand ? \App\Models\ManagedBrand::orderBy('position')->orderBy('name')->get() : collect();
    $brandTones = ['bg-primary-800', 'bg-amber-500', 'bg-emerald-600', 'bg-sky-600', 'bg-rose-500', 'bg-violet-600'];
    $supportEmail = config('mail.from.address');
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 w-60 bg-white border-r border-gray-200 text-gray-700 flex flex-col transition-transform duration-200 ease-out xl:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full xl:translate-x-0'">

    {{-- Logo --}}
    <div class="h-16 flex items-center gap-2 px-5 shrink-0">
        <a href="{{ route($isSuperadmin ? 'admin.dashboard' : ($isBrand ? 'brand.dashboard' : 'dashboard')) }}" class="block">
            <x-application-logo class="h-8 w-auto" />
        </a>
        @if ($isSuperadmin)
            <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mt-1">{{ __('Admin') }}</span>
        @endif
        <button @click="sidebarOpen = false" class="ml-auto xl:hidden text-gray-400 hover:text-gray-700" aria-label="{{ __('Close') }}">
            <i class="ri-close-line text-2xl"></i>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto px-3 pt-2 pb-4 space-y-5">
        @foreach ($nav as $gi => $group)
            @php $collapsible = ! empty($group['collapsible']) && ! empty($group['section']); @endphp
            <div @if ($collapsible) x-data="{ open: (() => { try { return localStorage.getItem('nav-{{ $gi }}') !== '0' } catch (e) { return true } })() }" @endif>
                @if (! empty($group['section']))
                    @if ($collapsible)
                        <button type="button" @click="open = !open; try { localStorage.setItem('nav-{{ $gi }}', open ? '1' : '0') } catch (e) {}"
                            class="w-full flex items-center justify-between px-3 mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-400 hover:text-gray-600">
                            <span>{{ __($group['section']) }}</span>
                            <i class="ri-arrow-right-s-line text-sm transition-transform" :class="open ? 'rotate-90' : ''"></i>
                        </button>
                    @else
                        <div class="px-3 mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-400">{{ __($group['section']) }}</div>
                    @endif
                @endif
                <ul class="space-y-0.5" @if ($collapsible) x-show="open" x-collapse @endif>
                    @foreach ($group['items'] as $item)
                        @if (! empty($item['children']))
                            {{-- Collapsible item (e.g. Email settings) --}}
                            @php $groupActive = collect($item['activeAny'] ?? [])->contains(fn ($p) => request()->routeIs($p)); @endphp
                            <li x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }">
                                <button type="button" @click="open = !open"
                                    class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                                        {{ $groupActive ? 'text-gray-900 bg-gray-100' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                                    <i class="{{ $item['icon'] }} text-lg text-gray-500"></i>
                                    <span class="flex-1 text-left">{{ __($item['label']) }}</span>
                                    <i class="ri-arrow-down-s-line text-base text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                </button>
                                <ul x-show="open" x-cloak x-collapse class="mt-0.5 ml-5 pl-3 border-l border-gray-200 space-y-0.5">
                                    @foreach ($item['children'] as $child)
                                        @php $childActive = $child['active'] && request()->routeIs($child['active']); @endphp
                                        <li>
                                            <a href="{{ $child['route'] ? route($child['route']) : '#' }}"
                                                class="flex items-center gap-2.5 px-3 py-1.5 rounded-lg text-sm font-medium transition
                                                    {{ $childActive ? 'bg-primary-800 text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                                                <i class="{{ $child['icon'] }} text-base {{ $childActive ? '' : 'text-gray-500' }}"></i>
                                                <span>{{ __($child['label']) }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            @php $active = $item['active'] && request()->routeIs($item['active']); @endphp
                            <li>
                                <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                                    class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                                        {{ $active ? 'bg-primary-800 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                                    <i class="{{ $item['icon'] }} text-lg {{ $active ? '' : 'text-gray-500' }}"></i>
                                    <span class="flex-1 truncate">{{ __($item['label']) }}</span>
                                    @if (! empty($item['badge']))
                                        <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-[11px] font-bold flex items-center justify-center">{{ $item['badge'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endif
                    @endforeach
                </ul>
            </div>
        @endforeach

        {{-- Brand portal: "My brands" --}}
        @if ($myBrands->isNotEmpty())
            <div x-data="{ open: (() => { try { return localStorage.getItem('nav-brands') !== '0' } catch (e) { return true } })() }">
                <button type="button" @click="open = !open; try { localStorage.setItem('nav-brands', open ? '1' : '0') } catch (e) {}"
                    class="w-full flex items-center justify-between px-3 mb-1.5 text-[11px] font-semibold uppercase tracking-wider text-gray-400 hover:text-gray-600">
                    <span>{{ __('My brands') }}</span>
                    <i class="ri-arrow-right-s-line text-sm transition-transform" :class="open ? 'rotate-90' : ''"></i>
                </button>
                <ul class="space-y-0.5" x-show="open" x-collapse>
                    @foreach ($myBrands as $b)
                        @php $on = request()->routeIs('brand.catalogue.*') && (string) request('brand') === (string) $b->_id; @endphp
                        <li>
                            <a href="{{ $brandAccount?->isEngine() ? route('brand.engines.index') : route('brand.catalogue.index', ['brand' => $b->_id]) }}"
                                class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition {{ $on ? 'bg-gray-100 text-gray-900' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                                <span class="w-5 h-5 rounded {{ $brandTones[$loop->index % count($brandTones)] }} text-white text-[10px] font-bold flex items-center justify-center shrink-0">{{ mb_strtoupper(mb_substr($b->name, 0, 1)) }}</span>
                                <span class="truncate">{{ $b->name }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </nav>

    {{-- Help --}}
    @if ($user && ! $isSuperadmin && $supportEmail)
        <div class="p-3 shrink-0">
            <div class="rounded-xl border border-gray-200 p-3">
                <p class="text-xs text-gray-500 leading-relaxed">{{ $isBrand
                    ? __('Need help inviting your network? Your account manager replies within 24 hours.')
                    : __('Need help? Our team replies within 24 hours.') }}</p>
                <a href="mailto:{{ $supportEmail }}"
                    class="mt-2.5 w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-800 hover:bg-gray-50">
                    <i class="ri-customer-service-2-line"></i> {{ __('Contact support') }}
                </a>
            </div>
        </div>
    @endif
</aside>
