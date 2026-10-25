<x-admin-layout :title="$account->name" :header="$account->name">
    @if (session('status'))
        <div class="pop mb-4 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm flex items-center gap-2">
            <i class="ri-checkbox-circle-line text-lg"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="pop mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm flex items-center gap-2"><i class="ri-error-warning-line text-lg"></i> {{ $errors->first() }}</div>
    @endif
    @php
        $active = $accesses->where('status', \App\Models\BrandAccess::STATUS_ACTIVE)->count();
        $items = $itemCounts->sum();
        $initials = collect(preg_split('/\s+/', trim($account->name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
    @endphp

    <div class="mb-4 text-sm"><a href="{{ route('admin.brand-accounts.index') }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Brand accounts') }}</a></div>

    {{-- Hero --}}
    <section class="rise bg-white rounded-2xl border border-gray-200 overflow-hidden mb-5">
        <div class="h-24 bg-gradient-to-r from-primary-900 via-primary-800 to-primary-600 relative">
            <svg class="absolute right-6 bottom-0 h-20 text-white/10" viewBox="0 0 200 100" fill="currentColor" aria-hidden="true"><path d="M90 5 L90 80 L40 80 Z"/><path d="M98 20 L98 80 L140 80 Z"/><path d="M20 86 Q100 104 180 86 L172 96 Q100 110 28 96 Z"/></svg>
        </div>
        {{-- Only the avatar overlaps the banner; the name sits below it. --}}
        <div class="px-6 pb-5 flex flex-col md:flex-row md:items-start md:justify-between gap-4">
            <div class="flex items-start gap-4" x-data="{ edit: false }">
                <span class="relative z-10 -mt-10 w-20 h-20 rounded-2xl bg-white p-1 shadow-md shrink-0">
                    <span class="w-full h-full rounded-xl bg-primary-800 text-white text-2xl font-bold flex items-center justify-center">{{ mb_strtoupper($initials) }}</span>
                </span>
                <div class="pt-3 min-w-0">
                    <div x-show="!edit" class="flex items-center gap-2">
                        <h2 class="text-2xl font-bold text-gray-900 truncate">{{ $account->name }}</h2>
                        <button type="button" @click="edit = true; $nextTick(() => $refs.name.focus())" class="w-8 h-8 inline-flex items-center justify-center text-gray-400 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Rename') }}"><i class="ri-pencil-line"></i></button>
                    </div>
                    <form x-show="edit" x-cloak method="POST" action="{{ route('admin.brand-accounts.update', $account->_id) }}" class="flex items-center gap-2">
                        @csrf @method('PATCH')
                        <input x-ref="name" name="name" value="{{ $account->name }}" required maxlength="160" @keydown.escape="edit = false" aria-label="{{ __('Account name') }}"
                            class="rounded-lg border-gray-300 text-lg font-semibold focus:border-primary-800 focus:ring-primary-800">
                        <button class="w-9 h-9 inline-flex items-center justify-center bg-primary-800 text-white rounded-lg" aria-label="{{ __('Save') }}"><i class="ri-check-line"></i></button>
                        <button type="button" @click="edit = false" class="w-9 h-9 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg" aria-label="{{ __('Cancel') }}"><i class="ri-close-line"></i></button>
                    </form>
                    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-sm">
                        <span class="inline-flex items-center gap-1 rounded-full bg-primary-50 text-primary-800 px-2.5 py-0.5 font-medium">
                            <i class="{{ $account->isEngine() ? 'ri-settings-3-line' : 'ri-sailboat-line' }}"></i> {{ $account->isEngine() ? __('Engine brand') : __('Boat brands') }}</span>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-medium {{ $account->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $account->is_active ? 'bg-emerald-500' : 'bg-red-500' }}"></span>{{ $account->is_active ? __('Active') : __('Suspended') }}</span>
                        @if ($account->created_at) <span class="text-gray-500"><i class="ri-calendar-line"></i> {{ __('since :date', ['date' => $account->created_at->format('d/m/Y')]) }}</span> @endif
                    </div>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.brand-accounts.toggle', $account->_id) }}" class="md:pt-4"
                data-confirm="{{ $account->is_active ? __('Suspend this brand account? Its managers lose access to the portal; dealers keep their catalogue copies.') : __('Reactivate this brand account?') }}"
                @if ($account->is_active) data-confirm-danger="1" @endif>
                @csrf
                <button class="inline-flex items-center gap-1.5 px-3.5 py-2 text-sm font-semibold rounded-lg border transition {{ $account->is_active ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' }}">
                    <i class="{{ $account->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i>
                    {{ $account->is_active ? __('Suspend account') : __('Reactivate account') }}
                </button>
            </form>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-4 border-t border-gray-100 divide-x divide-gray-100">
            @foreach ([
                [__('Brands'), $brands->count(), 'ri-building-4-line'],
                [$account->isEngine() ? __('Engines') : __('Models'), $items, $account->isEngine() ? 'ri-settings-3-line' : 'ri-sailboat-line'],
                [__('Connected dealers'), $active, 'ri-links-line'],
                [__('Quotes made'), $quoteCount, 'ri-file-list-3-line'],
            ] as [$label, $value, $icon])
                <div class="px-6 py-4 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl bg-gray-50 text-gray-500 flex items-center justify-center"><i class="{{ $icon }} text-lg"></i></span>
                    <div><div class="text-xl font-bold text-gray-900 tabular-nums" x-data="countUp({{ (int) $value }})" x-text="display">{{ $value }}</div><div class="text-xs text-gray-500">{{ $label }}</div></div>
                </div>
            @endforeach
        </div>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-5">
        {{-- Dealers --}}
        <section class="rise xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden self-start" style="--d: 1">
            <div class="px-5 py-4 flex items-center justify-between">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="ri-store-2-line text-gray-400"></i> {{ __('Dealers') }}</h3>
                <span class="text-sm text-gray-500">{{ $accesses->count() }}</span>
            </div>
            @if ($accesses->isEmpty())
                <x-app.empty-state icon="ri-store-2-line" size="sm" :title="__('No dealers yet')" :message="__('This brand has not invited any dealer yet.')" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                            <tr><th class="px-5 py-2.5">{{ __('Dealer') }}</th><th class="px-4 py-2.5">{{ __('Status') }}</th><th class="px-4 py-2.5">{{ __('Level') }}</th><th class="px-5 py-2.5 text-right">{{ __('Invited') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($accesses as $ac)
                                @php $co = $companies[(string) $ac->company_id] ?? null; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-8 h-8 rounded-full text-xs font-bold flex items-center justify-center shrink-0 {{ $co ? 'bg-primary-50 text-primary-800' : 'bg-sky-50 text-sky-700' }}">
                                                @if ($co) {{ mb_strtoupper(mb_substr($co->name, 0, 2)) }} @else <i class="ri-mail-line"></i> @endif</span>
                                            <div class="min-w-0">
                                                @if ($co) <a href="{{ route('admin.dealers.show', $co->_id) }}" class="block font-medium text-gray-900 hover:text-primary-800 truncate">{{ $co->name }}</a> @else <span class="block font-medium text-gray-900 truncate">{{ $ac->email }}</span> @endif
                                                @if ($co) <span class="block text-xs text-gray-500 truncate">{{ $ac->email }}</span> @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><x-brand.access-status :status="$ac->status" /></td>
                                    <td class="px-4 py-3"><x-brand.level-badge :n="$ac->level" :name="$account->levelName($ac->level)" /></td>
                                    <td class="px-5 py-3 text-right text-gray-500 tabular-nums">{{ $ac->invited_at?->format('d/m/Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="space-y-5">
            {{-- Brands --}}
            <section class="rise bg-white rounded-2xl border border-gray-200 p-5" style="--d: 2">
                <h3 class="font-semibold text-gray-900 flex items-center gap-2 mb-3"><i class="ri-building-4-line text-gray-400"></i> {{ __('Brands managed') }}</h3>
                <ul class="space-y-2">
                    @foreach ($brands as $b)
                        <li x-data="{ edit: false }" class="group rounded-xl border border-gray-200 px-3 py-2.5 hover:border-gray-300 transition">
                            <div x-show="!edit" class="flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg bg-primary-50 text-primary-800 text-xs font-bold flex items-center justify-center shrink-0">{{ mb_strtoupper(mb_substr($b->name, 0, 2)) }}</span>
                                <span class="flex-1 min-w-0">
                                    <span class="block font-medium text-gray-900 truncate">{{ $b->name }}</span>
                                    <span class="block text-xs text-gray-500">{{ trans_choice($account->isEngine() ? '{0}No engine|{1}:count engine|[2,*]:count engines' : '{0}No model|{1}:count model|[2,*]:count models', $itemCounts[(string) $b->_id] ?? 0, ['count' => $itemCounts[(string) $b->_id] ?? 0]) }}</span>
                                </span>
                                <button type="button" @click="edit = true" class="w-8 h-8 inline-flex items-center justify-center text-gray-400 opacity-0 group-hover:opacity-100 hover:text-primary-800 hover:bg-gray-100 rounded-lg transition" title="{{ __('Rename') }}"><i class="ri-pencil-line"></i></button>
                            </div>
                            <form x-show="edit" x-cloak method="POST" action="{{ route('admin.brand-accounts.brands.rename', [$account->_id, $b->_id]) }}" class="flex gap-2">
                                @csrf @method('PATCH')
                                <input name="name" value="{{ $b->name }}" aria-label="{{ __('Brand name') }}" @keydown.escape="edit = false" class="flex-1 min-w-0 rounded-lg border-gray-300 text-sm py-1.5 focus:border-primary-800 focus:ring-primary-800" />
                                <button class="w-9 h-9 inline-flex items-center justify-center bg-primary-800 text-white rounded-lg" aria-label="{{ __('Save') }}"><i class="ri-check-line"></i></button>
                            </form>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('admin.brand-accounts.brands.add', $account->_id) }}" class="mt-3 flex gap-2">
                    @csrf
                    <div class="relative flex-1">
                        <i class="ri-add-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input name="brand" required maxlength="120" placeholder="{{ __('Add a brand…') }}" aria-label="{{ __('Brand name') }}"
                            class="w-full pl-9 rounded-lg border-dashed border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                    </div>
                    <button class="px-3 py-2 text-sm font-semibold border border-gray-300 hover:bg-gray-50 rounded-lg">{{ __('Add') }}</button>
                </form>
            </section>

            {{-- Managers --}}
            <section class="rise bg-white rounded-2xl border border-gray-200 p-5" style="--d: 3" x-data="{ invite: {{ $errors->has('email') ? 'true' : 'false' }} }">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="ri-user-star-line text-gray-400"></i> {{ __('Brand managers') }}</h3>
                    <button type="button" x-show="!invite" @click="invite = true" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-800 hover:bg-primary-50 px-2 py-1 rounded-lg"><i class="ri-user-add-line"></i> {{ __('Invite') }}</button>
                </div>
                <ul class="space-y-2.5">
                    @foreach ($managers as $m)
                        <li class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center shrink-0">{{ mb_strtoupper(collect(explode(' ', $m->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) }}</span>
                            <span class="min-w-0"><span class="block font-medium text-gray-900 truncate">{{ $m->name }}</span><span class="block text-xs text-gray-500 truncate">{{ $m->email }}</span></span>
                        </li>
                    @endforeach
                    @foreach ($invites as $inv)
                        <li class="flex items-center gap-3">
                            <span class="w-9 h-9 rounded-full border-2 border-dashed border-gray-300 text-gray-400 flex items-center justify-center shrink-0"><i class="ri-mail-send-line"></i></span>
                            <span class="flex-1 min-w-0"><span class="block font-medium text-gray-900 truncate">{{ $inv->name }}</span>
                                <span class="block text-xs text-amber-700"><i class="ri-time-line"></i> {{ __('Invited') }} · {{ $inv->email }}</span></span>
                            <form method="POST" action="{{ route('admin.brand-accounts.invites.resend', [$account->_id, $inv->_id]) }}">@csrf
                                <button class="w-8 h-8 inline-flex items-center justify-center text-gray-400 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Resend') }}"><i class="ri-refresh-line"></i></button>
                            </form>
                        </li>
                    @endforeach
                    @if ($managers->isEmpty() && $invites->isEmpty())
                        <li class="text-sm text-gray-500">{{ __('No manager has accepted yet.') }}</li>
                    @endif
                </ul>
                <form x-show="invite" x-collapse x-cloak method="POST" action="{{ route('admin.brand-accounts.managers.invite', $account->_id) }}" class="mt-4 pt-4 border-t border-gray-100 space-y-2">
                    @csrf
                    <input name="name" required maxlength="120" placeholder="{{ __('Name') }}" aria-label="{{ __('Name') }}" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                    <input name="email" type="email" required maxlength="200" placeholder="{{ __('Email') }}" aria-label="{{ __('Email') }}" value="{{ old('email') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="invite = false" class="px-3 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</button>
                        <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-send-plane-line"></i> {{ __('Invite manager') }}</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-admin-layout>
