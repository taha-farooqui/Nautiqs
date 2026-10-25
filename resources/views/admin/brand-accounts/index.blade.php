<x-admin-layout :title="__('Brand accounts')" :header="__('Brand accounts')">
    @if (session('status'))
        <div class="pop mb-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2"><i class="ri-checkbox-circle-line text-lg"></i> {{ session('status') }}</div>
    @endif
    @php
        $allAccesses = $accesses->flatten(1);
        $totals = [
            [__('Brand accounts'), $accounts->count(), 'ri-award-line', 'bg-primary-50 text-primary-800'],
            [__('Brands managed'), $brands->flatten(1)->count(), 'ri-building-4-line', 'bg-sky-50 text-sky-700'],
            [__('Connected dealers'), $allAccesses->where('status', \App\Models\BrandAccess::STATUS_ACTIVE)->count(), 'ri-links-line', 'bg-emerald-50 text-emerald-700'],
            [__('Pending invitations'), $allAccesses->whereIn('status', \App\Models\BrandAccess::OPEN_STATUSES)->count(), 'ri-time-line', 'bg-amber-50 text-amber-700'],
        ];
    @endphp

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
        <p class="text-sm text-gray-600 max-w-2xl">{{ __('Manufacturers and importers who manage their own catalogue for the dealers they invite.') }}</p>
        <a href="{{ route('admin.brand-accounts.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-xl shadow-sm shrink-0"><i class="ri-add-line"></i> {{ __('New brand account') }}</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach ($totals as $i => [$label, $value, $icon, $tone])
            <div class="rise bg-white rounded-2xl border border-gray-200 p-5 flex items-center gap-4" style="--d: {{ $i }}">
                <span class="w-11 h-11 rounded-xl {{ $tone }} flex items-center justify-center shrink-0"><i class="{{ $icon }} text-xl"></i></span>
                <div><div class="text-2xl font-bold text-gray-900 tabular-nums" x-data="countUp({{ $value }})" x-text="display">{{ $value }}</div><div class="text-xs text-gray-500">{{ $label }}</div></div>
            </div>
        @endforeach
    </div>

    @if ($accounts->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200">
            <x-app.empty-state icon="ri-award-line" :title="__('No brand accounts yet')" size="lg"
                :message="__('Create one for a manufacturer or an importer, and invite its manager.')"
                :ctaLabel="__('New brand account')" :ctaHref="route('admin.brand-accounts.create')" />
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($accounts as $a)
                @php
                    $id = (string) $a->_id;
                    $acc = $accesses[$id] ?? collect();
                    $active = $acc->where('status', \App\Models\BrandAccess::STATUS_ACTIVE)->count();
                    $open = $acc->whereIn('status', \App\Models\BrandAccess::OPEN_STATUSES)->count();
                    $mgr = ($managers[$id] ?? collect())->count();
                    $inv = ($invites[$id] ?? collect())->count();
                    $initials = collect(preg_split('/\s+/', trim($a->name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                @endphp
                <a href="{{ route('admin.brand-accounts.show', $id) }}" class="rise lift group bg-white rounded-2xl border border-gray-200 p-5 flex flex-col {{ $a->is_active ? '' : 'opacity-70' }}" style="--d: {{ min($loop->index + 4, 10) }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-800 to-primary-600 text-white font-bold flex items-center justify-center shrink-0">{{ mb_strtoupper($initials) }}</span>
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 truncate group-hover:text-primary-800">{{ $a->name }}</div>
                                <div class="text-xs text-gray-500"><i class="{{ $a->isEngine() ? 'ri-settings-3-line' : 'ri-sailboat-line' }}"></i> {{ $a->isEngine() ? __('Engine brand') : __('Boat brands') }}</div>
                            </div>
                        </div>
                        @if (! $a->is_active)
                            <span class="text-xs font-semibold text-red-700 bg-red-50 rounded-full px-2 py-0.5 shrink-0">{{ __('Suspended') }}</span>
                        @elseif ($mgr === 0)
                            <span class="text-xs font-semibold text-amber-700 bg-amber-50 rounded-full px-2 py-0.5 shrink-0">{{ __('Manager invited') }}</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5 shrink-0"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ __('Active') }}</span>
                        @endif
                    </div>
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @forelse ($brands[$id] ?? collect() as $b)
                            <span class="text-xs rounded-md bg-gray-100 text-gray-700 px-2 py-0.5">{{ $b->name }}</span>
                        @empty
                            <span class="text-xs text-gray-400">—</span>
                        @endforelse
                    </div>
                    <div class="mt-auto pt-4 grid grid-cols-3 gap-2 text-center border-t border-gray-100 mt-4">
                        <div class="pt-3"><div class="font-bold text-gray-900">{{ $mgr }}@if ($inv)<span class="text-xs font-semibold text-amber-600">+{{ $inv }}</span>@endif</div><div class="text-[11px] text-gray-500">{{ __('Managers') }}</div></div>
                        <div class="pt-3"><div class="font-bold text-emerald-700">{{ $active }}</div><div class="text-[11px] text-gray-500">{{ __('Connected') }}</div></div>
                        <div class="pt-3"><div class="font-bold text-amber-700">{{ $open }}</div><div class="text-[11px] text-gray-500">{{ __('Waiting') }}</div></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-admin-layout>
