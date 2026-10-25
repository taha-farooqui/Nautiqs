<x-app-layout :title="__('Dealers')" :header="__('Dealers')">
    @include('brand.partials.flash')
    @php
        $brandName = $brands->mapWithKeys(fn ($b) => [(string) $b->_id => $b->name]);
        $allBrandIds = $brands->map(fn ($b) => (string) $b->_id)->values()->all();
        $tabs = [
            'all'     => [__('All'), 'ri-team-line', 'text-primary-800 bg-primary-50'],
            'active'  => [__('Active'), 'ri-links-line', 'text-emerald-700 bg-emerald-50'],
            'waiting' => [__('Waiting for answer'), 'ri-time-line', 'text-amber-700 bg-amber-50'],
            'invited' => [__('Not on Nautiqs yet'), 'ri-mail-send-line', 'text-sky-700 bg-sky-50'],
            'ended'   => [__('Declined / ended'), 'ri-link-unlink', 'text-gray-600 bg-gray-100'],
        ];
        $rows = $accesses->map(function ($a) use ($companies) {
            $co = $a->company_id ? ($companies[(string) $a->company_id] ?? null) : null;
            return ['id' => (string) $a->_id, 'name' => $co?->name ?? $a->email, 'email' => $a->email,
                'level' => (int) $a->level, 'brand_ids' => $a->brandIds()];
        })->keyBy('id');
        $oldEmails = array_values(array_filter((array) old('emails', []))) ?: [''];
    @endphp

    <div x-data="dealersPage(@js([
            'invite' => ['open' => $openInvite || $errors->has('emails') || $errors->has('brand_ids'), 'emails' => $oldEmails,
                         'level' => (int) old('level', 2), 'brands' => old('brand_ids', $allBrandIds)],
            'rows' => $rows, 'updateUrl' => route('brand.dealers.update', '__ID__'),
        ]))">

        {{-- Header --}}
        <div class="mb-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <p class="text-sm text-gray-600 max-w-2xl">{{ __('Only the dealers you invite get your catalogue. Everyone else can still add your brand by hand.') }}</p>
            <button type="button" @click="openInvite()" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-xl shadow-sm self-start transition">
                <i class="ri-user-add-line"></i> {{ __('Invite dealers') }}
            </button>
        </div>

        {{-- Status tiles = filters --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 mb-5">
            @foreach ($tabs as $t => [$label, $icon, $tone])
                <a href="{{ route('brand.dealers.index', ['tab' => $t]) }}" class="rise lift rounded-2xl border p-4 bg-white {{ $tab === $t ? 'border-primary-800 ring-1 ring-primary-800' : 'border-gray-200' }}" style="--d: {{ $loop->index }}">
                    <div class="flex items-center justify-between">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center {{ $tone }}"><i class="{{ $icon }} text-lg"></i></span>
                        <span class="text-2xl font-bold text-gray-900 tabular-nums">{{ $counts[$t] }}</span>
                    </div>
                    <div class="mt-2 text-sm font-medium {{ $tab === $t ? 'text-primary-800' : 'text-gray-600' }}">{{ $label }}</div>
                </a>
            @endforeach
        </div>

        @if ($accesses->isEmpty())
            <div class="bg-white rounded-2xl border border-gray-200">
                <x-app.empty-state icon="ri-store-2-line" :title="__('No dealers here yet')" size="lg"
                    :message="__('Invite your dealers by email. Each one gets your catalogue at the price level you choose.')" />
                <div class="flex justify-center -mt-10 pb-12">
                    <button type="button" @click="openInvite()" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-user-add-line"></i> {{ __('Invite dealers') }}</button>
                </div>
            </div>
        @else
            <div class="rise bg-white rounded-2xl border border-gray-200" style="--d: 5">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <div class="relative max-w-xs">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="search" x-model="search" placeholder="{{ __('Search a dealer…') }}" class="w-full pl-9 rounded-lg border-gray-200 bg-gray-50 text-sm focus:bg-white focus:border-primary-800 focus:ring-primary-800">
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                            <tr>
                                <th class="px-5 py-3 font-semibold">{{ __('Dealer') }}</th>
                                <th class="px-4 py-3 font-semibold">{{ __('Status') }}</th>
                                <th class="px-4 py-3 font-semibold">{{ __('Level') }}</th>
                                @if ($brands->count() > 1) <th class="px-4 py-3 font-semibold">{{ __('Brands') }}</th> @endif
                                <th class="px-4 py-3 font-semibold text-right">{{ __('Quotes') }}</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($accesses as $a)
                                @php
                                    $r = $rows[(string) $a->_id];
                                    $ended = in_array($a->status, [\App\Models\BrandAccess::STATUS_DECLINED, \App\Models\BrandAccess::STATUS_REVOKED, \App\Models\BrandAccess::STATUS_DISCONNECTED], true);
                                    $when = match (true) {
                                        $a->isActive() && $a->responded_at => __('since :date', ['date' => $a->responded_at->format('d/m/Y')]),
                                        $ended && $a->ended_at => __('ended :date', ['date' => $a->ended_at->format('d/m/Y')]),
                                        $a->status === \App\Models\BrandAccess::STATUS_DECLINED && $a->responded_at => __('declined :date', ['date' => $a->responded_at->format('d/m/Y')]),
                                        default => __('invited :date', ['date' => $a->invited_at?->format('d/m/Y')]),
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/70 transition {{ $ended ? 'opacity-60' : '' }}"
                                    x-show="matches(@js(mb_strtolower($r['name'] . ' ' . $r['email'])))">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-9 h-9 rounded-full text-xs font-bold flex items-center justify-center shrink-0 {{ $a->company_id ? 'bg-primary-50 text-primary-800' : 'bg-sky-50 text-sky-700' }}">
                                                @if ($a->company_id) {{ mb_strtoupper(mb_substr($r['name'], 0, 2)) }} @else <i class="ri-mail-line text-base"></i> @endif
                                            </span>
                                            <div class="min-w-0">
                                                <div class="font-semibold text-gray-900 truncate">{{ $r['name'] }}</div>
                                                <div class="text-xs text-gray-500 truncate">{{ $a->company_id ? $a->email . ' · ' : '' }}{{ $when }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><x-brand.access-status :status="$a->status" /></td>
                                    <td class="px-4 py-3">@if ($ended) <span class="text-gray-400">—</span> @else <x-brand.level-badge :n="$a->level" :name="$account->levelName($a->level)" /> @endif</td>
                                    @if ($brands->count() > 1)
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($a->brandIds() as $bid)
                                                    @isset($brandName[$bid]) <span class="text-xs rounded-md bg-gray-100 text-gray-700 px-1.5 py-0.5">{{ $brandName[$bid] }}</span> @endisset
                                                @endforeach
                                            </div>
                                        </td>
                                    @endif
                                    <td class="px-4 py-3 text-right tabular-nums font-medium">{{ $a->company_id ? ($quoteCounts[(string) $a->company_id] ?? 0) : '—' }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-1">
                                            @unless ($ended)
                                                <button type="button" @click="openEdit(@js((string) $a->_id))" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold text-gray-700 border border-gray-200 hover:border-primary-300 hover:text-primary-800 rounded-lg transition">
                                                    <i class="ri-pencil-line"></i> {{ __('Edit') }}</button>
                                            @endunless
                                            @if ($a->isActive() || $a->isOpen())
                                                <div x-data="{ menu: false }" class="relative">
                                                    <button type="button" @click="menu = !menu" class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg" aria-label="{{ __('More') }}"><i class="ri-more-2-fill"></i></button>
                                                    <div x-show="menu" x-cloak x-transition.origin.top.right @click.outside="menu = false"
                                                        class="absolute right-0 mt-1 w-52 bg-white border border-gray-200 rounded-xl shadow-lg z-20 py-1 text-sm">
                                                        @if ($a->isOpen())
                                                            <form method="POST" action="{{ route('brand.dealers.resend', $a->_id) }}">@csrf
                                                                <button class="w-full text-left flex items-center gap-2 px-3 py-2 hover:bg-gray-50"><i class="ri-mail-send-line text-gray-500"></i> {{ __('Resend invitation') }}</button>
                                                            </form>
                                                        @endif
                                                        <form method="POST" action="{{ route('brand.dealers.revoke', $a->_id) }}" data-confirm-danger="1"
                                                            data-confirm="{{ $a->isActive() ? __('Revoke this dealer? Your catalogue is hidden from them straight away. Their quotes stay theirs.') : __('Cancel this invitation?') }}">@csrf
                                                            <button class="w-full text-left flex items-center gap-2 px-3 py-2 text-red-600 hover:bg-red-50"><i class="ri-link-unlink"></i> {{ $a->isActive() ? __('Revoke access') : __('Cancel invitation') }}</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Invite modal --}}
        <div x-show="invite.open" x-cloak x-transition.opacity @keydown.escape.window="invite.open = false"
            class="fixed inset-0 z-50 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <form method="POST" action="{{ route('brand.dealers.invite') }}" @click.outside="invite.open = false" x-show="invite.open" x-transition.scale.95
                class="bg-white rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl">
                @csrf
                <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-800 flex items-center justify-center"><i class="ri-user-add-line text-xl"></i></span>
                        <div>
                            <h3 class="font-semibold text-gray-900 text-lg">{{ __('Invite dealers') }}</h3>
                            <p class="text-sm text-gray-500">{{ __('Someone not on Nautiqs yet receives an invitation to request an account.') }}</p>
                        </div>
                    </div>
                    <button type="button" @click="invite.open = false" class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg" aria-label="{{ __('Close') }}"><i class="ri-close-line text-xl"></i></button>
                </div>

                <div class="px-6 py-5 space-y-6 max-h-[70vh] overflow-y-auto">
                    <div>
                        <div class="flex items-center gap-2 mb-2"><span class="w-5 h-5 rounded-full bg-primary-800 text-white text-[11px] font-bold flex items-center justify-center">1</span><span class="text-sm font-semibold text-gray-900">{{ __('Dealer emails') }}</span></div>
                        <div class="space-y-2">
                            <template x-for="(e, i) in invite.emails" :key="i">
                                <div class="pop flex items-center gap-2">
                                    <div class="relative flex-1">
                                        <i class="ri-mail-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                                        <input type="email" name="emails[]" x-model="invite.emails[i]" :required="i === 0" placeholder="contact@dealer.fr" maxlength="200"
                                            @keydown.enter.prevent="addEmail()"
                                            class="w-full pl-9 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                    </div>
                                    <button type="button" x-show="invite.emails.length > 1" @click="invite.emails.splice(i, 1)" class="w-9 h-9 inline-flex items-center justify-center text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg" aria-label="{{ __('Remove') }}"><i class="ri-close-line text-lg"></i></button>
                                </div>
                            </template>
                        </div>
                        <button type="button" @click="addEmail()" class="mt-2 inline-flex items-center gap-1 text-sm font-semibold text-primary-800 hover:text-primary-900 px-2 py-1 rounded-lg hover:bg-primary-50">
                            <i class="ri-add-circle-line text-base"></i> {{ __('Add another dealer') }}</button>
                    </div>

                    <div>
                        <div class="flex items-center gap-2 mb-2"><span class="w-5 h-5 rounded-full bg-primary-800 text-white text-[11px] font-bold flex items-center justify-center">2</span><span class="text-sm font-semibold text-gray-900">{{ __('Price level') }}</span></div>
                        @include('brand.partials.level-picker', ['model' => 'invite.level'])
                    </div>

                    @if ($brands->count() > 1)
                        <div>
                            <div class="flex items-center gap-2 mb-2"><span class="w-5 h-5 rounded-full bg-primary-800 text-white text-[11px] font-bold flex items-center justify-center">3</span><span class="text-sm font-semibold text-gray-900">{{ __('Brands to share') }}</span></div>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($brands as $b)
                                    <label class="cursor-pointer select-none inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                                        :class="invite.brands.includes('{{ $b->_id }}') ? 'border-primary-800 bg-primary-800 text-white' : 'border-gray-300 text-gray-700 hover:border-gray-400'">
                                        <input type="checkbox" name="brand_ids[]" value="{{ $b->_id }}" x-model="invite.brands" class="sr-only">
                                        <i :class="invite.brands.includes('{{ $b->_id }}') ? 'ri-check-line' : 'ri-add-line'"></i> {{ $b->name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @else
                        @foreach ($brands as $b) <input type="hidden" name="brand_ids[]" value="{{ $b->_id }}"> @endforeach
                    @endif

                    <div x-data="{ msg: {{ old('message') ? 'true' : 'false' }} }">
                        <button type="button" x-show="!msg" @click="msg = true" class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 hover:text-gray-900"><i class="ri-chat-1-line"></i> {{ __('Add a personal message') }}</button>
                        <div x-show="msg" x-cloak>
                            <label for="message" class="block text-sm font-semibold text-gray-900 mb-1.5">{{ __('Personal message') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                            <textarea id="message" name="message" rows="2" maxlength="1000" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">{{ old('message') }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-2">
                    <span class="text-xs text-gray-500" x-text="validCount() + ' ' + (validCount() === 1 ? @js(__('dealer')) : @js(__('dealers')))"></span>
                    <div class="flex gap-2">
                        <button type="button" @click="invite.open = false" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200/60 rounded-lg">{{ __('Cancel') }}</button>
                        <button :disabled="validCount() === 0" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg transition">
                            <i class="ri-send-plane-fill"></i> {{ __('Send invitations') }}</button>
                    </div>
                </div>
            </form>
        </div>

        {{-- Edit modal --}}
        <div x-show="edit.open" x-cloak x-transition.opacity @keydown.escape.window="edit.open = false"
            class="fixed inset-0 z-50 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <form method="POST" :action="updateUrl.replace('__ID__', edit.id)" @click.outside="edit.open = false" x-show="edit.open" x-transition.scale.95
                class="bg-white rounded-2xl w-full max-w-2xl overflow-hidden shadow-2xl">
                @csrf @method('PATCH')
                <div class="px-6 py-5 border-b border-gray-100 flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="w-11 h-11 rounded-full bg-primary-50 text-primary-800 font-bold flex items-center justify-center" x-text="(edit.name || '').slice(0, 2).toUpperCase()"></span>
                        <div>
                            <h3 class="font-semibold text-gray-900 text-lg" x-text="edit.name"></h3>
                            <p class="text-sm text-gray-500">{{ __('Change their level or the brands they receive. Their purchase prices update straight away.') }}</p>
                        </div>
                    </div>
                    <button type="button" @click="edit.open = false" class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg" aria-label="{{ __('Close') }}"><i class="ri-close-line text-xl"></i></button>
                </div>
                <div class="px-6 py-5 space-y-6">
                    <div>
                        <span class="block text-sm font-semibold text-gray-900 mb-2">{{ __('Price level') }}</span>
                        @include('brand.partials.level-picker', ['model' => 'edit.level'])
                    </div>
                    @if ($brands->count() > 1)
                        <div>
                            <span class="block text-sm font-semibold text-gray-900 mb-2">{{ __('Brands to share') }}</span>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($brands as $b)
                                    <label class="cursor-pointer select-none inline-flex items-center gap-1.5 rounded-full border px-3.5 py-1.5 text-sm font-medium transition"
                                        :class="edit.brands.includes('{{ $b->_id }}') ? 'border-primary-800 bg-primary-800 text-white' : 'border-gray-300 text-gray-700 hover:border-gray-400'">
                                        <input type="checkbox" name="brand_ids[]" value="{{ $b->_id }}" x-model="edit.brands" class="sr-only">
                                        <i :class="edit.brands.includes('{{ $b->_id }}') ? 'ri-check-line' : 'ri-add-line'"></i> {{ $b->name }}
                                    </label>
                                @endforeach
                            </div>
                            <p x-show="edit.brands.length === 0" x-cloak class="mt-2 text-xs text-red-600">{{ __('Share at least one brand, or revoke the dealer.') }}</p>
                        </div>
                    @endif
                </div>
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" @click="edit.open = false" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200/60 rounded-lg">{{ __('Cancel') }}</button>
                    <button :disabled="edit.brands.length === 0" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 disabled:opacity-40 text-white rounded-lg"><i class="ri-check-line"></i> {{ __('Save changes') }}</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            function dealersPage(cfg) {
                return {
                    search: '',
                    rows: cfg.rows,
                    updateUrl: cfg.updateUrl,
                    invite: cfg.invite,
                    edit: { open: false, id: '', name: '', level: 1, brands: [] },
                    matches(text) { return this.search === '' || text.includes(this.search.toLowerCase().trim()); },
                    openInvite() { this.invite.open = true; this.$nextTick(() => this.$root.querySelector('input[name="emails[]"]')?.focus()); },
                    addEmail() {
                        this.invite.emails.push('');
                        this.$nextTick(() => { const all = this.$root.querySelectorAll('input[name="emails[]"]'); all[all.length - 1]?.focus(); });
                    },
                    validCount() { return this.invite.emails.filter(e => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test((e || '').trim())).length; },
                    openEdit(id) {
                        const r = this.rows[id];
                        this.edit = { open: true, id, name: r.name, level: r.level, brands: [...r.brand_ids] };
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
