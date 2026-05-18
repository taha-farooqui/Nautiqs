<x-admin-layout :title="$dealer->name" :header="$dealer->name">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif

    @php $isSuspended = ($dealer->status ?? 'active') === 'suspended'; @endphp

    <div class="mb-4 flex items-center gap-2 text-sm">
        <a href="{{ route('admin.dealers.index') }}" class="text-gray-500 hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to dealers') }}
        </a>
    </div>

    {{-- Header card --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6 mb-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-start gap-4">
                <div class="w-14 h-14 rounded-xl bg-primary-50 text-primary-800 font-bold text-lg flex items-center justify-center shrink-0">
                    {{ strtoupper(mb_substr($dealer->name ?? '?', 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <h2 class="text-xl font-bold text-gray-900">{{ $dealer->name }}</h2>
                    <div class="text-sm text-gray-500 mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1">
                        @if ($dealer->legal_form) <span>{{ $dealer->legal_form }}</span> @endif
                        @if ($dealer->siren) <span>{{ __('SIREN') }} {{ $dealer->siren }}</span> @endif
                        @if ($dealer->vat_number) <span>{{ __('VAT') }} {{ $dealer->vat_number }}</span> @endif
                    </div>
                </div>
                <div>
                    @if ($isSuspended)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-red-50 text-red-700">
                            <i class="ri-pause-circle-line"></i> {{ __('Suspended') }}
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">
                            <i class="ri-checkbox-circle-line"></i> {{ __('Active') }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @if ($isSuspended)
                    <form method="POST" action="{{ route('admin.dealers.reactivate', $dealer->_id) }}"
                        data-confirm="{{ __('Reactivate :name?', ['name' => $dealer->name]) }}"
                        data-confirm-text="{{ __('Users will regain access to their workspace.') }}">
                        @csrf
                        <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">
                            <i class="ri-play-circle-line"></i> {{ __('Reactivate') }}
                        </button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.dealers.suspend', $dealer->_id) }}"
                        data-confirm="{{ __('Suspend :name?', ['name' => $dealer->name]) }}"
                        data-confirm-text="{{ __('Users will see a "subscription suspended" page on login. Existing data is preserved.') }}"
                        data-confirm-danger="1">
                        @csrf
                        <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-amber-600 hover:bg-amber-700 text-white rounded-lg">
                            <i class="ri-pause-circle-line"></i> {{ __('Suspend') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    {{-- Stat grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Quotes this month') }}</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['quotes_month'] }}</div>
            <div class="text-[11px] text-gray-500 mt-0.5">{{ $stats['quotes_total'] }} {{ __('total') }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Clients') }}</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">{{ $stats['clients_total'] }}</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Revenue won YTD') }}</div>
            <div class="text-2xl font-bold text-gray-900 mt-1">€{{ number_format($stats['revenue_won_ytd'] / 1000, 0, ',', ' ') }}k</div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Last activity') }}</div>
            <div class="text-sm font-medium text-gray-900 mt-1">
                {{ $stats['last_activity'] ? \Carbon\Carbon::parse($stats['last_activity'])->diffForHumans() : __('Never') }}
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Profile --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h3 class="font-semibold text-gray-900">{{ __('Profile') }}</h3>
            </div>
            <dl class="divide-y divide-gray-100 text-sm">
                @foreach ([
                    __('Salesperson')       => $dealer->salesperson_name,
                    __('Salesperson email') => $dealer->salesperson_email,
                    __('Salesperson phone') => $dealer->salesperson_phone,
                    __('Address')           => $dealer->address,
                    __('Default VAT')       => $dealer->default_vat_rate ? $dealer->default_vat_rate . '%' : null,
                    __('Default margin')    => $dealer->default_margin_pct ? $dealer->default_margin_pct . '%' : null,
                    __('Timezone')          => $dealer->timezone,
                ] as $label => $value)
                    <div class="px-5 py-2.5 flex items-start justify-between gap-3">
                        <dt class="text-gray-500 text-xs uppercase tracking-wider shrink-0">{{ $label }}</dt>
                        <dd class="text-gray-900 text-right break-words min-w-0">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Users --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900">{{ __('Users') }}</h3>
                <span class="text-xs text-gray-500">{{ $users->count() }}</span>
            </div>
            @if ($users->isEmpty())
                <div class="px-5 py-6 text-center text-sm text-gray-500">{{ __('No users.') }}</div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($users as $u)
                        <li class="px-5 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate">{{ $u->name }}</div>
                                <div class="text-[11px] text-gray-500 truncate">{{ $u->email }}</div>
                            </div>
                            <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded
                                {{ $u->role === 'tenant_admin' ? 'bg-primary-50 text-primary-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $u->role === 'tenant_admin' ? __('Admin') : __('User') }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-admin-layout>
