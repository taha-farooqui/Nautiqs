<x-admin-layout :title="$account->name" :header="$account->name">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
        <a href="{{ route('admin.brand-accounts.index') }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Brand accounts') }}</a>
        <form method="POST" action="{{ route('admin.brand-accounts.toggle', $account->_id) }}"
            data-confirm="{{ $account->is_active ? __('Suspend this brand account? Its managers lose access to the portal; dealers keep their catalogue copies.') : __('Reactivate this brand account?') }}"
            @if ($account->is_active) data-confirm-danger="1" @endif>
            @csrf
            <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium rounded-lg border {{ $account->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                <i class="{{ $account->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i>
                {{ $account->is_active ? __('Suspend account') : __('Reactivate account') }}
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Account --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-5 space-y-4">
            <h3 class="font-semibold text-gray-900">{{ __('Account') }}</h3>
            <form method="POST" action="{{ route('admin.brand-accounts.update', $account->_id) }}" class="flex flex-wrap items-end gap-2">
                @csrf @method('PATCH')
                <div class="flex-1 min-w-[200px]">
                    <label for="acc-name" class="block text-xs font-medium text-gray-600 mb-1">{{ __('Name') }}</label>
                    <input id="acc-name" name="name" value="{{ $account->name }}" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <button class="px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">{{ __('Save') }}</button>
            </form>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-gray-500">{{ __('Type') }}</dt><dd class="font-medium">{{ $account->isEngine() ? __('Engine brand') : __('Boat brands') }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Status') }}</dt><dd class="font-medium">{{ $account->is_active ? __('Active') : __('Suspended') }}</dd></div>
            </dl>
        </section>

        {{-- Brands --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
            <h3 class="font-semibold text-gray-900">{{ __('Brands managed') }}</h3>
            <ul class="space-y-2">
                @foreach ($brands as $b)
                    <li x-data="{ edit: false }" class="flex flex-wrap items-center gap-2 text-sm">
                        <span x-show="!edit" class="font-medium text-gray-900 flex-1">{{ $b->name }}</span>
                        <button type="button" x-show="!edit" @click="edit = true" class="text-xs text-primary-800 hover:underline">{{ __('Rename') }}</button>
                        <form x-show="edit" x-cloak method="POST" action="{{ route('admin.brand-accounts.brands.rename', [$account->_id, $b->_id]) }}" class="flex flex-1 gap-2">
                            @csrf @method('PATCH')
                            <input name="name" value="{{ $b->name }}" aria-label="{{ __('Brand name') }}" class="flex-1 rounded-lg border-gray-300 text-sm py-1.5 focus:border-primary-800 focus:ring-primary-800" />
                            <button class="px-3 py-1.5 text-xs font-semibold bg-primary-800 text-white rounded-lg">{{ __('Save') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('admin.brand-accounts.brands.add', $account->_id) }}" class="flex gap-2 pt-2 border-t border-gray-100">
                @csrf
                <input name="brand" required maxlength="120" placeholder="{{ __('Add a brand…') }}" aria-label="{{ __('Brand name') }}"
                    class="flex-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                <button class="px-3 py-2 text-sm font-semibold border border-gray-300 hover:bg-gray-50 rounded-lg">{{ __('Add') }}</button>
            </form>
        </section>

        {{-- Managers --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3 lg:col-span-2">
            <h3 class="font-semibold text-gray-900">{{ __('Brand managers') }}</h3>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse ($managers as $m)
                    <li class="py-2 flex flex-wrap justify-between gap-2"><span class="font-medium text-gray-900">{{ $m->name }}</span><span class="text-gray-500">{{ $m->email }}</span></li>
                @empty
                    <li class="py-2 text-gray-500">{{ __('No manager has accepted yet.') }}</li>
                @endforelse
                @foreach ($invites as $inv)
                    <li class="py-2 flex flex-wrap items-center justify-between gap-2">
                        <span><span class="font-medium text-gray-900">{{ $inv->name }}</span> <span class="text-gray-500">{{ $inv->email }}</span>
                            <span class="ml-1 text-xs font-semibold text-amber-700 bg-amber-50 rounded-full px-2 py-0.5">{{ __('Invited') }}</span></span>
                        <form method="POST" action="{{ route('admin.brand-accounts.invites.resend', [$account->_id, $inv->_id]) }}">
                            @csrf
                            <button class="text-xs text-primary-800 hover:underline">{{ __('Resend') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('admin.brand-accounts.managers.invite', $account->_id) }}" class="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 pt-3 border-t border-gray-100">
                @csrf
                <input name="name" required maxlength="120" placeholder="{{ __('Name') }}" aria-label="{{ __('Name') }}" class="rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                <input name="email" type="email" required maxlength="200" placeholder="{{ __('Email') }}" aria-label="{{ __('Email') }}" class="rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                <button class="px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">{{ __('Invite manager') }}</button>
            </form>
        </section>

        {{-- Dealers --}}
        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden lg:col-span-2">
            <div class="px-5 py-4"><h3 class="font-semibold text-gray-900">{{ __('Dealers') }}</h3></div>
            @if ($accesses->isEmpty())
                <p class="px-5 pb-5 text-sm text-gray-500">{{ __('This brand has not invited any dealer yet.') }}</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                            <tr><th class="px-5 py-2.5">{{ __('Dealer') }}</th><th class="px-5 py-2.5">{{ __('Status') }}</th><th class="px-5 py-2.5">{{ __('Level') }}</th><th class="px-5 py-2.5">{{ __('Invited') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($accesses as $ac)
                                <tr>
                                    <td class="px-5 py-2.5">{{ $companies[(string) $ac->company_id]->name ?? $ac->email }}</td>
                                    <td class="px-5 py-2.5"><x-brand.access-status :status="$ac->status" /></td>
                                    <td class="px-5 py-2.5">{{ $account->levelName($ac->level) }}</td>
                                    <td class="px-5 py-2.5 text-gray-500">{{ $ac->invited_at?->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-admin-layout>
