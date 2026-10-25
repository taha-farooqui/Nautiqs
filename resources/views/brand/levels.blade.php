<x-app-layout :title="__('Price levels')" :header="__('Price levels')">
    @include('brand.partials.flash')
    @php
        $catLabel = fn ($c) => match ($c) {
            'versions' => __('Boats (versions)'), 'options' => __('Options'),
            'engines' => __('Engines'), 'kits' => __('Kits'), 'propellers' => __('Propellers'), default => $c,
        };
        $catIcon = fn ($c) => match ($c) {
            'versions' => 'ri-sailboat-line', 'options' => 'ri-list-check-2', 'engines' => 'ri-settings-3-line',
            'kits' => 'ri-tools-line', 'propellers' => 'ri-loader-2-line', default => 'ri-price-tag-3-line',
        };
        $state = [
            'levels' => collect($levels)->map(fn ($l, $n) => ['n' => $n, 'name' => old("levels.$n.name", $l['name']),
                'pct' => collect($categories)->mapWithKeys(fn ($c) => [$c => (float) old("levels.$n.pct.$c", $l['pct'][$c])])])->values(),
            'categories' => collect($categories)->map(fn ($c) => ['key' => $c, 'label' => $catLabel($c), 'icon' => $catIcon($c)])->values(),
            'sample' => $account->isEngine() ? 15000 : 50000,
        ];
        $tone = [1 => ['bg-amber-500', 'from-amber-400 to-amber-500', 'bg-amber-50 text-amber-700'],
                 2 => ['bg-slate-500', 'from-slate-400 to-slate-500', 'bg-slate-100 text-slate-700'],
                 3 => ['bg-orange-600', 'from-orange-400 to-orange-600', 'bg-orange-50 text-orange-700']];
    @endphp

    <form method="POST" action="{{ route('brand.levels.update') }}" x-data="levelsEditor(@js($state))" class="space-y-5 pb-20">
        @csrf @method('PATCH')

        {{-- Intro + simulator --}}
        <div class="grid grid-cols-1 xl:grid-cols-5 gap-4">
            <section class="rise xl:col-span-2 rounded-2xl bg-gradient-to-br from-primary-900 to-primary-700 text-white p-6">
                <span class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center mb-4"><i class="ri-price-tag-3-line text-xl"></i></span>
                <h2 class="text-xl font-bold">{{ __('How levels work') }}</h2>
                <p class="mt-2 text-sm text-white/80 leading-relaxed">{{ __('Each dealer is on one level. Their purchase price for a product is the public price minus the level\'s discount — unless you typed an exact price for that level on the product. Dealers only ever see their own price.') }}</p>
                <div class="mt-5 flex flex-wrap gap-2">
                    @foreach ($levels as $n => $l)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1 text-sm">
                            <span class="w-2 h-2 rounded-full {{ $tone[$n][0] }}"></span>
                            <span x-text="levels[{{ $n - 1 }}].name || '{{ __('Level :n', ['n' => $n]) }}'"></span>
                            <span class="text-white/60">· {{ trans_choice('{0}no dealer|{1}:count dealer|[2,*]:count dealers', $counts[$n] ?? 0, ['count' => $counts[$n] ?? 0]) }}</span>
                        </span>
                    @endforeach
                </div>
            </section>

            <section class="rise xl:col-span-3 bg-white rounded-2xl border border-gray-200 p-6" style="--d: 1">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
                    <div>
                        <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="ri-calculator-line text-primary-800"></i> {{ __('Price simulator') }}</h3>
                        <p class="text-sm text-gray-500">{{ __('See what each level pays, live as you change the discounts.') }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <input type="number" min="0" step="100" x-model.number="sample" aria-label="{{ __('Public price HT') }}"
                                class="w-36 pr-8 text-right rounded-lg border-gray-300 text-sm font-semibold focus:border-primary-800 focus:ring-primary-800">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">€</span>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap gap-1.5 mb-5">
                    <template x-for="c in categories" :key="c.key">
                        <button type="button" @click="cat = c.key" class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition"
                            :class="cat === c.key ? 'bg-primary-800 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'">
                            <i :class="c.icon"></i><span x-text="c.label"></span>
                        </button>
                    </template>
                </div>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between text-sm mb-1"><span class="text-gray-500">{{ __('Public price') }}</span><span class="font-semibold text-gray-900 tabular-nums" x-text="eur(sample)"></span></div>
                        <div class="h-3 rounded-full bg-gray-200"></div>
                    </div>
                    @foreach ($levels as $n => $l)
                        <div>
                            <div class="flex justify-between items-baseline text-sm mb-1">
                                <span class="inline-flex items-center gap-1.5 font-medium text-gray-900"><span class="w-2 h-2 rounded-full {{ $tone[$n][0] }}"></span><span x-text="levels[{{ $n - 1 }}].name"></span></span>
                                <span class="tabular-nums">
                                    <span class="font-bold text-gray-900" x-text="eur(priceAt({{ $n - 1 }}))"></span>
                                    <span class="ml-1.5 text-xs font-semibold rounded-full px-1.5 py-0.5 {{ $tone[$n][2] }}" x-text="'−' + eur(sample - priceAt({{ $n - 1 }}))"></span>
                                </span>
                            </div>
                            <div class="h-3 rounded-full bg-gray-100 overflow-hidden">
                                <div class="bar-fill h-full rounded-full bg-gradient-to-r {{ $tone[$n][1] }}" :style="`width: ${Math.max(0, 100 - pct({{ $n - 1 }}))}%`"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        {{-- Level cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            @foreach ($levels as $n => $l)
                @php $i = $n - 1; @endphp
                <section class="rise lift bg-white rounded-2xl border border-gray-200 overflow-hidden" style="--d: {{ $n + 1 }}">
                    <div class="p-5">
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <x-brand.level-badge :n="$n" :name="__('Level :n', ['n' => $n])" />
                            <span class="text-xs text-gray-500"><i class="ri-store-2-line"></i> {{ trans_choice('{0}no dealer|{1}:count dealer|[2,*]:count dealers', $counts[$n] ?? 0, ['count' => $counts[$n] ?? 0]) }}</span>
                        </div>
                        <label class="sr-only" for="lv-{{ $n }}">{{ __('Level name') }}</label>
                        <div class="relative group">
                            <input id="lv-{{ $n }}" name="levels[{{ $n }}][name]" x-model="levels[{{ $i }}].name" required maxlength="60"
                                class="w-full -mx-2 px-2 py-1 text-xl font-bold text-gray-900 border-0 border-b-2 border-transparent rounded-none bg-transparent focus:ring-0 focus:border-primary-800 hover:border-gray-200 transition">
                            <i class="ri-pencil-line absolute right-0 top-1/2 -translate-y-1/2 text-gray-300 group-hover:text-gray-500 pointer-events-none"></i>
                        </div>

                        <div class="mt-4 space-y-5">
                            <template x-for="c in categories" :key="c.key">
                                <div>
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="inline-flex items-center gap-1.5 text-sm text-gray-700"><i :class="c.icon" class="text-gray-400"></i><span x-text="c.label"></span></span>
                                        <div class="flex items-center gap-1">
                                            <span class="text-gray-400 text-sm">−</span>
                                            <input type="number" step="0.1" min="0" max="100" :name="`levels[{{ $n }}][pct][${c.key}]`"
                                                x-model.number="levels[{{ $i }}].pct[c.key]" @focus="cat = c.key" :aria-label="levels[{{ $i }}].name + ' · ' + c.label"
                                                class="w-16 py-1 text-right rounded-lg border-gray-300 text-sm font-semibold focus:border-primary-800 focus:ring-primary-800">
                                            <span class="text-gray-400 text-sm">%</span>
                                        </div>
                                    </div>
                                    <input type="range" min="0" max="50" step="0.5" x-model.number="levels[{{ $i }}].pct[c.key]" @input="cat = c.key"
                                        class="range-primary w-full" :style="sliderFill(levels[{{ $i }}].pct[c.key])" tabindex="-1" aria-hidden="true">
                                </div>
                            </template>
                        </div>
                    </div>
                </section>
            @endforeach
        </div>

        {{-- Per-brand overrides --}}
        @if ($brands->count() > 1)
            <section class="rise bg-white rounded-2xl border border-gray-200 p-6" style="--d: 5">
                <div class="flex items-start gap-3 mb-4">
                    <span class="w-10 h-10 shrink-0 rounded-xl bg-primary-50 text-primary-800 flex items-center justify-center"><i class="ri-equalizer-line text-lg"></i></span>
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ __('Different terms for one brand?') }}</h3>
                        <p class="text-sm text-gray-500">{{ __('Importers often buy each brand on different terms. Customise a brand to override the discounts above for it.') }}</p>
                    </div>
                </div>
                <div class="space-y-3">
                    @foreach ($brands as $b)
                        @php $id = (string) $b->_id; $o = is_array($b->level_pcts) ? $b->level_pcts : []; @endphp
                        <div x-data="{ custom: {{ $b->hasOverrides() ? 'true' : 'false' }} }" class="rounded-xl border transition" :class="custom ? 'border-primary-200 bg-primary-50/30' : 'border-gray-200'">
                            <div class="flex items-center justify-between gap-3 px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-sm font-bold text-primary-800 flex items-center justify-center">{{ mb_strtoupper(mb_substr($b->name, 0, 2)) }}</span>
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $b->name }}</div>
                                        <div class="text-xs" :class="custom ? 'text-primary-800' : 'text-gray-500'" x-text="custom ? '{{ __('custom discounts') }}' : '{{ __('uses the default') }}'"></div>
                                    </div>
                                </div>
                                <input type="hidden" name="overrides[{{ $id }}][custom]" :value="custom ? 1 : 0">
                                <button type="button" @click="custom = !custom" role="switch" :aria-checked="custom.toString()" aria-label="{{ __('Custom discounts for :brand', ['brand' => $b->name]) }}"
                                    class="relative w-11 h-6 rounded-full transition" :class="custom ? 'bg-primary-800' : 'bg-gray-300'">
                                    <span class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white shadow transition-transform" :class="custom ? 'translate-x-5' : ''"></span>
                                </button>
                            </div>
                            <div x-show="custom" x-collapse x-cloak class="px-4 pb-4">
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    @foreach ($levels as $n => $lvl)
                                        <div class="rounded-lg bg-white border border-gray-200 p-3">
                                            <div class="text-xs font-semibold text-gray-700 mb-2 flex items-center gap-1.5"><span class="w-2 h-2 rounded-full {{ $tone[$n][0] }}"></span><span x-text="levels[{{ $n - 1 }}].name"></span></div>
                                            @foreach ($categories as $c)
                                                <div class="flex items-center justify-between gap-2 py-0.5">
                                                    <span class="text-xs text-gray-500">{{ $catLabel($c) }}</span>
                                                    <span class="flex items-center gap-1"><span class="text-gray-400 text-xs">−</span>
                                                        <input type="number" step="0.1" min="0" max="100" name="overrides[{{ $id }}][pct][{{ $n }}][{{ $c }}]"
                                                            value="{{ $o[$n][$c] ?? $o[(string) $n][$c] ?? $lvl['pct'][$c] }}" aria-label="{{ $b->name }} · {{ $lvl['name'] }} · {{ $catLabel($c) }}"
                                                            class="w-16 py-0.5 text-right rounded-md border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"><span class="text-gray-400 text-xs">%</span></span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Save bar --}}
        <div class="fixed bottom-0 inset-x-0 xl:left-72 z-30 px-4 sm:px-6 lg:px-8 pb-4 pointer-events-none">
            <div class="pointer-events-auto mx-auto max-w-3xl rounded-2xl shadow-2xl border px-5 py-3 flex items-center justify-between gap-3 transition-all"
                :class="dirty ? 'bg-primary-900 border-primary-900 text-white translate-y-0' : 'bg-white border-gray-200 text-gray-600'">
                <span class="text-sm flex items-center gap-2">
                    <i :class="dirty ? 'ri-error-warning-line text-amber-300' : 'ri-checkbox-circle-line text-emerald-500'"></i>
                    <span x-text="dirty ? @js(__('Unsaved changes — dealers are re-priced when you save.')) : @js(__('All levels saved.'))"></span>
                </span>
                <button class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold rounded-lg transition"
                    :class="dirty ? 'bg-white text-primary-900 hover:bg-primary-50' : 'bg-primary-800 text-white hover:bg-primary-900'">
                    <i class="ri-save-line"></i> {{ __('Save levels') }}
                </button>
            </div>
        </div>
    </form>

    @push('scripts')
        <script>
            function levelsEditor(s) {
                return {
                    levels: s.levels, categories: s.categories, sample: s.sample,
                    cat: s.categories[0].key,
                    initial: '',
                    init() { this.initial = JSON.stringify(this.levels); },
                    get dirty() { return JSON.stringify(this.levels) !== this.initial; },
                    pct(i) { return Number(this.levels[i].pct[this.cat]) || 0; },
                    priceAt(i) { return (Number(this.sample) || 0) * (1 - this.pct(i) / 100); },
                    eur(v) { return new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 }).format(Math.round(v || 0)) + ' €'; },
                    sliderFill(v) { const p = Math.min(100, (Number(v) || 0) / 50 * 100); return `background: linear-gradient(to right, #0e4f79 ${p}%, #e5e7eb ${p}%)`; },
                };
            }
        </script>
    @endpush
</x-app-layout>
