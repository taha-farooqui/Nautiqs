<x-admin-layout :title="__('Brand accounts')" :header="__('Brand accounts')">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <p class="text-sm text-gray-600 max-w-2xl">{{ __('Manufacturers and importers who manage their own catalogue for the dealers they invite.') }}</p>
        <a href="{{ route('admin.brand-accounts.create') }}"
            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg shrink-0">
            <i class="ri-add-line"></i> {{ __('New brand account') }}
        </a>
    </div>

    @if ($accounts->isEmpty())
        <x-app.empty-state icon="ri-building-4-line" :title="__('No brand accounts yet')"
            :message="__('Create one for a manufacturer or an importer, and invite its manager.')" size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('Account') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Type') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Brands managed') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Managers') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Dealers') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($accounts as $a)
                            @php
                                $id = (string) $a->_id;
                                $acc = $accesses[$id] ?? collect();
                                $active = $acc->where('status', \App\Models\BrandAccess::STATUS_ACTIVE)->count();
                                $open = $acc->whereIn('status', \App\Models\BrandAccess::OPEN_STATUSES)->count();
                                $mgr = ($managers[$id] ?? collect())->count();
                                $inv = ($invites[$id] ?? collect())->count();
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('admin.brand-accounts.show', $id) }}" class="font-semibold text-gray-900 hover:text-primary-800">{{ $a->name }}</a>
                                </td>
                                <td class="px-5 py-3 text-gray-700">{{ $a->isEngine() ? __('Engines') : __('Boats') }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ ($brands[$id] ?? collect())->pluck('name')->implode(', ') ?: '—' }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $mgr }}@if ($inv) <span class="text-xs text-amber-700">+{{ $inv }} {{ __('invited') }}</span>@endif</td>
                                <td class="px-5 py-3 text-gray-700">{{ $active }} {{ __('active') }}@if ($open) · {{ $open }} {{ __('waiting') }}@endif</td>
                                <td class="px-5 py-3">
                                    @if (! $a->is_active)
                                        <span class="text-xs font-semibold text-red-700 bg-red-50 rounded-full px-2 py-0.5">{{ __('Suspended') }}</span>
                                    @elseif ($mgr === 0)
                                        <span class="text-xs font-semibold text-amber-700 bg-amber-50 rounded-full px-2 py-0.5">{{ __('Manager invited') }}</span>
                                    @else
                                        <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5">{{ __('Active') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-admin-layout>
