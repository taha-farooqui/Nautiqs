@php
    $user = auth()->user();
    $isSuperadmin = $user?->role === \App\Models\User::ROLE_SUPERADMIN;

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
                ['label' => 'Models & variants', 'icon' => 'ri-sailboat-line',           'route' => null, 'active' => 'catalogue.*'],
                ['label' => 'Brands',            'icon' => 'ri-building-4-line',         'route' => null, 'active' => 'brands.*'],
                ['label' => 'Catalogue updates', 'icon' => 'ri-notification-badge-line', 'route' => null, 'active' => 'updates.*', 'badge' => 0],
            ],
        ],
        [
            'section' => 'Settings',
            'items' => [
                ['label' => 'Company settings',  'icon' => 'ri-building-line',           'route' => 'company.settings', 'active' => 'company.settings'],
                ['label' => 'Email templates',   'icon' => 'ri-mail-settings-line',      'route' => null,               'active' => 'company.emails'],
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
    class="fixed inset-y-0 left-0 z-40 w-64 bg-primary-900 text-white flex flex-col transition-transform duration-200 ease-out lg:translate-x-0"
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
                        @php
                            $active = $item['active'] && request()->routeIs($item['active']);
                            $href = $item['route'] ? route($item['route']) : '#';
                        @endphp
                        <li>
                            <a href="{{ $href }}"
                                class="group flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium transition
                                    {{ $active
                                        ? 'bg-white/15 text-white'
                                        : 'text-white/75 hover:bg-white/10 hover:text-white' }}">
                                <i class="{{ $item['icon'] }} text-lg"></i>
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if (!empty($item['badge']))
                                    <span class="ml-auto min-w-[1.25rem] h-5 px-1.5 rounded-full bg-amber-400 text-amber-900 text-[11px] font-bold flex items-center justify-center">
                                        {{ $item['badge'] }}
                                    </span>
                                @endif
                                @if (empty($item['route']))
                                    <i class="ri-lock-line text-xs text-white/30" title="Coming soon"></i>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>

    {{-- Footer --}}
    <div class="px-4 py-3 border-t border-white/10 text-[11px] text-white/50">
        <div class="flex items-center gap-2">
            <i class="ri-shield-check-line"></i>
            <span>Secured workspace</span>
        </div>
        <div class="mt-1">v0.1 · © {{ date('Y') }} Nautiqs</div>
    </div>
</aside>
