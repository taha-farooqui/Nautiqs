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
                ['label' => 'Dashboard',         'icon' => 'squares-four',        'route' => 'dashboard',       'active' => 'dashboard'],
                ['label' => 'Quotes',            'icon' => 'file-text',        'route' => 'quotes.index',    'active' => 'quotes.*'],
                ['label' => 'Clients',           'icon' => 'address-book',        'route' => 'clients.index',   'active' => 'clients.*'],
            ],
        ],
        [
            'section' => 'Catalogue',
            'items' => [
                ['label' => 'Catalogue',         'icon' => 'sailboat',           'route' => 'catalogue.models',  'active' => 'catalogue.models'],
                [
                    'label'     => 'Engines',
                    'icon'      => 'engine',
                    'activeAny' => ['engines.*', 'engine-accessories.*'],
                    'children'  => [
                        ['label' => 'Engines',     'icon' => 'engine', 'route' => 'engines.index',            'active' => 'engines.*'],
                        ['label' => 'Kits & propellers', 'icon' => 'wrench', 'route' => 'engine-accessories.index', 'active' => 'engine-accessories.*'],
                    ],
                ],
                // Third-party add-ons (GPS, audio, fishing gear) sold on any quote.
                ['label' => 'Accessories',       'icon' => 'shopping-bag',     'route' => 'accessories.index', 'active' => 'accessories.*'],
                // Brands that publish their catalogue to this dealership.
                ['label' => 'Partner brands',    'icon' => 'handshake',              'route' => 'partner-brands.index', 'active' => 'partner-brands.*', 'badge' => $brandInvites > 0 ? (string) $brandInvites : null],
            ],
        ],
        [
            'section' => 'Settings',
            'items' => array_filter([
                // Also in the avatar menu, but nobody looks for their own
                // profile up there when every other setting is down here.
                ['label' => 'Profile',           'icon' => 'user',      'route' => 'profile.edit',     'active' => 'profile.*'],
                ['label' => 'Company settings',  'icon' => 'buildings',           'route' => 'company.settings', 'active' => 'company.settings'],
                $isTenantAdmin
                    ? ['label' => 'Team',        'icon' => 'users-three',               'route' => 'team.index',       'active' => 'team.*']
                    : null,
                [
                    'label'    => 'Email settings',
                    'icon'     => 'envelope-simple',
                    'activeAny' => ['email-templates.*', 'email-log.*'],
                    'children' => [
                        ['label' => 'Email templates', 'icon' => 'envelope-open',       'route' => 'email-templates.index', 'active' => 'email-templates.*'],
                        ['label' => 'Email log',       'icon' => 'tray', 'route' => 'email-log.index',       'active' => 'email-log.*'],
                    ],
                ],
            ]),
        ],
        [
            'section' => 'Activity',
            'items' => [
                ['label' => 'Notifications', 'icon' => 'bell', 'route' => 'notifications.index', 'active' => 'notifications.*', 'badge' => $unreadCount > 0 ? ($unreadCount > 99 ? '99+' : (string) $unreadCount) : null],
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
                ['label' => 'Overview',        'icon' => 'chart-line-up',      'route' => 'admin.dashboard',  'active' => 'admin.dashboard'],
                ['label' => 'Dealers',         'icon' => 'storefront',         'route' => 'admin.dealers.index', 'active' => 'admin.dealers.*'],
                ['label' => 'Account requests', 'icon' => 'user-plus',  'route' => 'admin.account-requests.index', 'active' => 'admin.account-requests.*', 'badge' => $pendingAccountRequests > 0 ? (string) $pendingAccountRequests : null],
                ['label' => 'Brand accounts',  'icon' => 'seal-check',           'route' => 'admin.brand-accounts.index', 'active' => 'admin.brand-accounts.*'],
            ],
        ],
        [
            'section' => 'Global catalogue',
            'items' => [
                ['label' => 'Brands',          'icon' => 'buildings',      'route' => 'admin.brands.index',    'active' => 'admin.brands.*'],
                ['label' => 'Models',          'icon' => 'sailboat',        'route' => 'admin.models.index',    'active' => 'admin.models.*'],
                ['label' => 'Variants',        'icon' => 'stack',     'route' => 'admin.variants.index',  'active' => 'admin.variants.*'],
                ['label' => 'Engines',         'icon' => 'engine',      'route' => 'admin.engines.index',   'active' => 'admin.engines.*'],
                ['label' => 'Equipment',       'icon' => 'wrench',           'route' => 'admin.equipment.index', 'active' => 'admin.equipment.*'],
                ['label' => 'Options',         'icon' => 'list-checks',         'route' => 'admin.options.index',   'active' => 'admin.options.*'],
            ],
        ],
        [
            'section' => 'Customisation',
            'items' => [
                ['label' => 'Settings',        'icon' => 'gear-six',      'route' => 'admin.settings.edit',    'active' => 'admin.settings.*'],
                ['label' => 'Dictionary',      'icon' => 'translate',          'route' => 'admin.dictionary.index', 'active' => 'admin.dictionary.*'],
            ],
        ],
        [
            'section' => 'Activity',
            'items' => [
                ['label' => 'Activity log',    'icon' => 'shield-check',    'route' => 'admin.audit.index',   'active' => 'admin.audit.*'],
                ['label' => 'Backups',         'icon' => 'database',      'route' => 'admin.backups.index', 'active' => 'admin.backups.*'],
            ],
        ],
    ];

    // Brand portal — a brand manager sees only these screens.
    $brandNav = [
        [
            'section' => null,
            'items' => [
                ['label' => 'Dashboard',       'icon' => 'squares-four',     'route' => 'brand.dashboard', 'active' => 'brand.dashboard'],
                $brandAccount?->isEngine()
                    ? ['label' => 'Catalogue', 'icon' => 'engine',      'route' => 'brand.engines.index',   'active' => ['brand.engines.*', 'brand.transfer.*']]
                    : ['label' => 'Catalogue', 'icon' => 'sailboat',        'route' => 'brand.catalogue.index', 'active' => ['brand.catalogue.*', 'brand.transfer.*']],
                ['label' => 'Brands',          'icon' => 'tag',      'route' => 'brand.brands.index',  'active' => 'brand.brands.*'],
                ['label' => 'Price levels',    'icon' => 'sliders',     'route' => 'brand.levels.edit',   'active' => 'brand.levels.*'],
                ['label' => 'Dealers',         'icon' => 'storefront',         'route' => 'brand.dealers.index', 'active' => 'brand.dealers.*'],
                ['label' => 'Quotes',          'icon' => 'file-text',     'route' => 'brand.quotes.index',  'active' => 'brand.quotes.*'],
            ],
        ],
        [
            'section' => 'Settings',
            'collapsible' => true,
            'items' => [
                ['label' => 'Team',            'icon' => 'users-three',            'route' => 'brand.team.index', 'active' => 'brand.team.*'],
                ['label' => 'Profile',         'icon' => 'user',   'route' => 'profile.edit',     'active' => 'profile.*'],
                ['label' => 'Notifications',   'icon' => 'bell',  'route' => 'notifications.index', 'active' => 'notifications.*', 'badge' => $unreadCount > 0 ? ($unreadCount > 99 ? '99+' : (string) $unreadCount) : null],
            ],
        ],
    ];

    $nav = $isSuperadmin ? $superadminNav : ($isBrand ? $brandNav : $tenantNav);

    // Brand portal: the account's brands, each a shortcut to its catalogue.
    $myBrands = $isBrand ? \App\Models\ManagedBrand::orderBy('position')->orderBy('name')->get() : collect();
    // Brand chips: navy, then gold (Figma), then the chart colours.
    $brandTones = ['bg-primary-800', 'bg-nq-accent', 'bg-nq-chart2', 'bg-nq-chart3', 'bg-nq-success', 'bg-nq-text-2'];
    $supportEmail = config('mail.from.address');

    // Figma "Sider" (node 19:4): 240 px, #fbfbfb, 36 px rows, 18 px Phosphor bold icons.
    $row = 'flex items-center gap-2.5 h-9 px-2.5 rounded-lg text-sm leading-[22px] transition-colors';
    $rowIdle = 'text-nq-text-2 hover:bg-nq-muted hover:text-nq-text';
    $rowOn = 'bg-nq-active text-white font-medium';
    $label = 'w-full flex items-center gap-1 pt-5 pb-1.5 pl-2.5 pr-2 text-[11px] font-semibold uppercase tracking-[0.66px] text-nq-text-3';
@endphp

<aside
    class="fixed inset-y-0 left-0 z-40 w-60 bg-nq-subtle border-r border-nq-border-2 flex flex-col pt-4 px-3 pb-3 transition-transform duration-200 ease-out xl:translate-x-0"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full xl:translate-x-0'">

    {{-- Logo --}}
    <div class="flex items-center gap-2 pl-2 pb-5 shrink-0">
        <a href="{{ route($isSuperadmin ? 'admin.dashboard' : ($isBrand ? 'brand.dashboard' : 'dashboard')) }}" class="block">
            <x-application-logo class="h-9 w-auto" />
        </a>
        @if ($isSuperadmin)
            <span class="text-[11px] font-semibold uppercase tracking-[0.66px] text-nq-text-3 mt-1">{{ __('Admin') }}</span>
        @endif
        <button @click="sidebarOpen = false" class="ml-auto xl:hidden text-nq-text-3 hover:text-nq-text" aria-label="{{ __('Close') }}">
            <i class="ph-bold ph-x text-lg"></i>
        </button>
    </div>

    {{-- Nav --}}
    <nav class="flex-1 overflow-y-auto -mx-3 px-3">
        @foreach ($nav as $gi => $group)
            @php $collapsible = ! empty($group['collapsible']) && ! empty($group['section']); @endphp
            <div @if ($collapsible) x-data="{ open: (() => { try { return localStorage.getItem('nav-{{ $gi }}') !== '0' } catch (e) { return true } })() }" @endif>
                @if (! empty($group['section']))
                    @if ($collapsible)
                        <button type="button" @click="open = !open; try { localStorage.setItem('nav-{{ $gi }}', open ? '1' : '0') } catch (e) {}"
                            class="{{ $label }} hover:text-nq-text-2">
                            <span class="flex-1 text-left">{{ __($group['section']) }}</span>
                            <i class="ph-bold ph-caret-right text-xs transition-transform" :class="open ? 'rotate-90' : ''"></i>
                        </button>
                    @else
                        <div class="{{ $label }}">{{ __($group['section']) }}</div>
                    @endif
                @endif
                <ul class="flex flex-col gap-0.5" @if ($collapsible) x-show="open" x-collapse @endif>
                    @foreach ($group['items'] as $item)
                        @if (! empty($item['children']))
                            @php $groupActive = collect($item['activeAny'] ?? [])->contains(fn ($p) => request()->routeIs($p)); @endphp
                            <li x-data="{ open: {{ $groupActive ? 'true' : 'false' }} }">
                                <button type="button" @click="open = !open"
                                    class="w-full {{ $row }} {{ $groupActive ? 'text-nq-text font-medium' : $rowIdle }}">
                                    <i class="ph-bold ph-{{ $item['icon'] }} text-[18px] shrink-0"></i>
                                    <span class="flex-1 text-left truncate">{{ __($item['label']) }}</span>
                                    <i class="ph-bold ph-caret-down text-xs text-nq-text-3 transition-transform" :class="open ? 'rotate-180' : ''"></i>
                                </button>
                                <ul x-show="open" x-cloak x-collapse class="mt-0.5 ml-[19px] pl-2 border-l border-nq-border flex flex-col gap-0.5">
                                    @foreach ($item['children'] as $child)
                                        @php $childActive = $child['active'] && request()->routeIs($child['active']); @endphp
                                        <li>
                                            <a href="{{ $child['route'] ? route($child['route']) : '#' }}"
                                                class="{{ $row }} {{ $childActive ? $rowOn : $rowIdle }}">
                                                <i class="ph-bold ph-{{ $child['icon'] }} text-base shrink-0"></i>
                                                <span class="truncate">{{ __($child['label']) }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </li>
                        @else
                            @php $active = $item['active'] && request()->routeIs($item['active']); @endphp
                            <li>
                                <a href="{{ $item['route'] ? route($item['route']) : '#' }}"
                                    class="{{ $row }} {{ $active ? $rowOn : $rowIdle }}">
                                    <i class="ph-bold ph-{{ $item['icon'] }} text-[18px] shrink-0"></i>
                                    <span class="flex-1 truncate">{{ __($item['label']) }}</span>
                                    @if (! empty($item['badge']))
                                        <span class="min-w-[18px] h-[18px] px-1.5 rounded-full bg-nq-error text-white text-[11px] font-medium flex items-center justify-center">{{ $item['badge'] }}</span>
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
                    class="{{ $label }} hover:text-nq-text-2">
                    <span class="flex-1 text-left">{{ __('My brands') }}</span>
                    <i class="ph-bold ph-caret-right text-xs transition-transform" :class="open ? 'rotate-90' : ''"></i>
                </button>
                <ul class="flex flex-col gap-0.5" x-show="open" x-collapse>
                    @foreach ($myBrands as $b)
                        @php $on = request()->routeIs('brand.catalogue.*') && (string) request('brand') === (string) $b->_id; @endphp
                        <li>
                            <a href="{{ $brandAccount?->isEngine() ? route('brand.engines.index') : route('brand.catalogue.index', ['brand' => $b->_id]) }}"
                                class="{{ $row }} {{ $on ? 'bg-nq-muted text-nq-text font-medium' : $rowIdle }}">
                                <span class="w-[18px] h-[18px] rounded-[5px] {{ $brandTones[$loop->index % count($brandTones)] }} text-white text-[10px] font-medium flex items-center justify-center shrink-0">{{ mb_strtoupper(mb_substr($b->name, 0, 1)) }}</span>
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
        <div class="pt-3 shrink-0">
            <div class="bg-white border border-nq-border rounded-xl p-3 flex flex-col gap-2.5">
                <p class="text-xs leading-5 text-nq-text-2">{{ $isBrand
                    ? __('Need help inviting your network? Your account manager replies within 24 hours.')
                    : __('Need help? Our team replies within 24 hours.') }}</p>
                <a href="mailto:{{ $supportEmail }}"
                    class="h-7 w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-nq-border bg-white px-3 text-sm font-medium text-nq-text hover:bg-nq-subtle">
                    <i class="ph-bold ph-compass text-base"></i> {{ __('Contact support') }}
                </a>
            </div>
        </div>
    @endif
</aside>
