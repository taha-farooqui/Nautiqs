<x-app-layout :title="$model ? $model->name : __('Add model')" :header="$model ? $model->name : __('Add model')">
    @include('brand.partials.flash')

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('brand.catalogue.index', ['brand' => $brand->_id]) }}" class="hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ $brand->name }}</a>
    </div>

    <form method="POST" action="{{ $model ? route('brand.catalogue.update', $model->_id) : route('brand.catalogue.store') }}"
        x-data="brandModelEditor(@js($versions), @js($options), @js($levels))" class="space-y-4">
        @csrf
        @if ($model) @method('PATCH') @else <input type="hidden" name="managed_brand_id" value="{{ $brand->_id }}"> @endif

        {{-- Model --}}
        <section class="bg-white rounded-2xl border border-gray-200 p-6 space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label for="m-name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Model name') }} <span class="text-red-500">*</span></label>
                    <input id="m-name" name="name" required maxlength="160" value="{{ old('name', $model->name ?? '') }}" placeholder="{{ __('e.g. Nordvik 690') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label for="m-code" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Code') }}</label>
                    <input id="m-code" name="code" maxlength="60" value="{{ old('code', $model->code ?? '') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                @foreach ([['year', __('Year'), '1'], ['length_total', __('Length (m)'), '0.01'], ['beam', __('Beam (m)'), '0.01'], ['draft_max', __('Draft (m)'), '0.01'], ['weight', __('Weight (kg)'), '1'], ['passengers', __('Passengers'), '1']] as [$f, $label, $step])
                    <div>
                        <label for="m-{{ $f }}" class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                        <input id="m-{{ $f }}" name="{{ $f }}" type="number" step="{{ $step }}" min="0" value="{{ old($f, $model->$f ?? '') }}"
                            class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Versions --}}
        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 flex flex-wrap items-baseline justify-between gap-2">
                <h3 class="font-semibold text-gray-900">{{ __('Versions') }}</h3>
                <span class="text-xs text-gray-500">{{ __('Leave a level empty to use its % (shown in grey); type a price to set it exactly.') }}</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-4 py-2.5">{{ __('Version') }}</th>
                            <th class="px-3 py-2.5 text-right w-36">{{ __('Public HT') }}</th>
                            <template x-for="lv in levels" :key="lv.n">
                                <th class="px-3 py-2.5 text-right w-36"><span x-text="lv.name"></span> <span class="normal-case font-normal" x-text="'(−' + lv.versions + '%)'"></span></th>
                            </template>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(v, i) in versions" :key="v.key">
                            <tr class="align-top">
                                <td class="px-4 py-2.5">
                                    <input type="hidden" :name="`versions[${i}][id]`" :value="v.id">
                                    <input :name="`versions[${i}][name]`" x-model="v.name" required maxlength="200" :aria-label="'{{ __('Version name') }}'"
                                        placeholder="{{ __('e.g. 690 · 1×200 HP') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    <button type="button" @click="v.open = !v.open" class="mt-1 text-xs text-primary-800 hover:underline"
                                        x-text="(v.open ? '{{ __('Hide equipment') }}' : '{{ __('Standard equipment') }}') + ' (' + countLines(v.equipment) + ')'"></button>
                                    <textarea x-show="v.open" x-cloak :name="`versions[${i}][equipment]`" x-model="v.equipment" rows="4"
                                        placeholder="{{ __('One item per line') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"></textarea>
                                    <template x-if="!v.open"><input type="hidden" :name="`versions[${i}][equipment]`" :value="v.equipment"></template>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="number" step="0.01" min="0" :name="`versions[${i}][price]`" x-model="v.price" required :aria-label="'{{ __('Public price HT') }}'"
                                        class="w-full text-right rounded-lg border-gray-300 text-sm font-semibold focus:border-primary-800 focus:ring-primary-800" />
                                </td>
                                <template x-for="(lv, li) in levels" :key="lv.n">
                                    <td class="px-3 py-2.5">
                                        <input type="number" step="0.01" min="0" :name="`versions[${i}][levels][${li}]`" x-model="v.levels[li]"
                                            :placeholder="calc(v.price, lv.versions)" :aria-label="lv.name"
                                            class="w-full text-right rounded-lg border-gray-300 text-sm placeholder:text-gray-400 focus:border-primary-800 focus:ring-primary-800" />
                                    </td>
                                </template>
                                <td class="px-2 py-2.5 text-right">
                                    <button type="button" @click="versions.splice(i, 1)" class="w-8 h-8 inline-flex items-center justify-center text-gray-400 hover:text-red-600" title="{{ __('Remove') }}"><i class="ri-close-line"></i></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">
                <button type="button" @click="addVersion()" class="text-sm font-semibold text-primary-800 hover:underline"><i class="ri-add-line"></i> {{ __('Add version') }}</button>
            </div>
        </section>

        {{-- Options --}}
        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4"><h3 class="font-semibold text-gray-900">{{ __('Options') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1000px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-4 py-2.5 w-40">{{ __('Category') }}</th>
                            <th class="px-3 py-2.5">{{ __('Option') }}</th>
                            <th class="px-3 py-2.5 text-right w-32">{{ __('Public HT') }}</th>
                            <th class="px-3 py-2.5 text-right w-20">{{ __('VAT') }}</th>
                            <template x-for="lv in levels" :key="lv.n">
                                <th class="px-3 py-2.5 text-right w-32"><span x-text="lv.name"></span> <span class="normal-case font-normal" x-text="'(−' + lv.options + '%)'"></span></th>
                            </template>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(o, i) in options" :key="o.key">
                            <tr class="align-top">
                                <td class="px-4 py-2.5">
                                    <input type="hidden" :name="`options[${i}][id]`" :value="o.id">
                                    <input :name="`options[${i}][category]`" x-model="o.category" maxlength="100" placeholder="{{ __('Comfort') }}" :aria-label="'{{ __('Category') }}'"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </td>
                                <td class="px-3 py-2.5">
                                    <input :name="`options[${i}][label]`" x-model="o.label" required maxlength="300" :aria-label="'{{ __('Option') }}'"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                    <textarea :name="`options[${i}][description]`" x-model="o.description" rows="1" placeholder="{{ __('Description (optional)') }}"
                                        class="mt-1 w-full rounded-lg border-gray-300 text-xs focus:border-primary-800 focus:ring-primary-800"></textarea>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="number" step="0.01" :name="`options[${i}][price]`" x-model="o.price" required :aria-label="'{{ __('Public price HT') }}'"
                                        class="w-full text-right rounded-lg border-gray-300 text-sm font-semibold focus:border-primary-800 focus:ring-primary-800" />
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="number" step="0.1" min="0" max="100" :name="`options[${i}][vat_rate]`" x-model="o.vat_rate" :aria-label="'{{ __('VAT') }}'"
                                        class="w-full text-right rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </td>
                                <template x-for="(lv, li) in levels" :key="lv.n">
                                    <td class="px-3 py-2.5">
                                        <input type="number" step="0.01" min="0" :name="`options[${i}][levels][${li}]`" x-model="o.levels[li]"
                                            :placeholder="calc(o.price, lv.options)" :aria-label="lv.name"
                                            class="w-full text-right rounded-lg border-gray-300 text-sm placeholder:text-gray-400 focus:border-primary-800 focus:ring-primary-800" />
                                    </td>
                                </template>
                                <td class="px-2 py-2.5 text-right">
                                    <button type="button" @click="options.splice(i, 1)" class="w-8 h-8 inline-flex items-center justify-center text-gray-400 hover:text-red-600" title="{{ __('Remove') }}"><i class="ri-close-line"></i></button>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">
                <button type="button" @click="addOption()" class="text-sm font-semibold text-primary-800 hover:underline"><i class="ri-add-line"></i> {{ __('Add option') }}</button>
            </div>
        </section>

        <div class="sticky bottom-0 z-10 bg-primary-900 text-white rounded-xl px-5 py-3 flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm">{{ __('Saving publishes to every connected dealer. Removed rows are archived; quotes already made never change.') }}</span>
            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-bold bg-white text-primary-900 rounded-lg hover:bg-primary-50">
                <i class="ri-save-line"></i> {{ __('Save & publish') }}
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function brandModelEditor(versions, options, levels) {
            let k = 0;
            const lv = r => { const a = Array.isArray(r) ? r.slice(0, 3) : []; while (a.length < 3) a.push(''); return a; };
            return {
                levels,
                versions: versions.map(v => ({ ...v, key: k++, open: false, levels: lv(v.levels), equipment: v.equipment || '' })),
                options: options.map(o => ({ ...o, key: k++, levels: lv(o.levels) })),
                addVersion() { this.versions.push({ key: k++, id: '', name: '', price: '', currency: 'EUR', levels: ['', '', ''], equipment: '', open: false }); },
                addOption() { this.options.push({ key: k++, id: '', category: '', label: '', description: '', price: '', vat_rate: 20, levels: ['', '', ''] }); },
                calc(price, pct) {
                    const p = parseFloat(price);
                    return isNaN(p) ? '' : (p * (1 - (parseFloat(pct) || 0) / 100)).toFixed(2);
                },
                countLines(t) { return (t || '').split(/\n/).filter(l => l.trim() !== '').length; },
            };
        }
    </script>
    @endpush
</x-app-layout>
