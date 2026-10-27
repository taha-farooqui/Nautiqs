<x-app-layout :title="$model ? $model->name : __('Add model')" :header="$model ? $model->name : __('Add model')">
    @include('brand.partials.flash')

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 text-sm">
        <a href="{{ route('brand.catalogue.index', ['brand' => $brand->_id]) }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ $brand->name }}</a>
        <div class="flex gap-2">
            <a href="{{ route('brand.transfer.form') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg"><i class="ri-upload-2-line"></i> {{ __('Import from Excel') }}</a>
            <a href="{{ route('brand.transfer.export') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg"><i class="ri-download-2-line"></i> {{ __('Export') }}</a>
        </div>
    </div>

    {{-- novalidate: fields on a hidden tab cannot show the browser's bubble; the server validates. --}}
    <form method="POST" action="{{ $model ? route('brand.catalogue.update', $model->_id) : route('brand.catalogue.store') }}" novalidate
        x-data="brandModelEditor(@js($versions), @js($options), @js($levels), @js($account->isExactPricing()))">
        @csrf
        @if ($model) @method('PATCH') @else <input type="hidden" name="managed_brand_id" value="{{ $brand->_id }}"> @endif

        {{-- Tabs, as on the dealer side --}}
        <div class="mb-4 border-b border-gray-200 flex items-center gap-1 overflow-x-auto">
            <button type="button" @click="tab = 'boat'" :class="tab === 'boat' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap"><i class="ri-sailboat-line"></i> {{ __('Boat') }}</button>
            <button type="button" @click="tab = 'versions'" :class="tab === 'versions' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap"><i class="ri-list-check-2"></i> {{ __('Versions & equipment') }} <span class="text-xs text-gray-400" x-text="'(' + versions.length + ')'"></span></button>
            <button type="button" @click="tab = 'options'" :class="tab === 'options' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                class="px-4 py-2.5 text-sm font-semibold border-b-2 transition whitespace-nowrap"><i class="ri-add-circle-line"></i> {{ __('Options') }} <span class="text-xs text-gray-400" x-text="'(' + options.length + ')'"></span></button>
        </div>

        {{-- Boat --}}
        <section x-show="tab === 'boat'" class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label for="m-name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Model name') }} <span class="text-red-500">*</span></label>
                    <input id="m-name" name="name" required maxlength="160" value="{{ old('name', $model->name ?? '') }}"
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
            <div>
                <label for="m-desc" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Description') }}</label>
                <textarea id="m-desc" name="description" rows="3" maxlength="5000" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">{{ old('description', $model->description ?? '') }}</textarea>
            </div>
            <div class="flex justify-end">
                <button type="button" @click="tab = 'versions'" class="inline-flex items-center gap-1 text-sm font-semibold text-primary-800 hover:underline">{{ __('Next: versions & equipment') }} <i class="ri-arrow-right-line"></i></button>
            </div>
        </section>

        {{-- Versions & included equipment --}}
        <section x-show="tab === 'versions'" x-cloak class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ __('Versions') }}</h2>
                    <p class="text-xs text-gray-500">{{ __('Each version with its public price, its purchase price per level and the equipment included as standard.') }}</p>
                </div>
                <span class="text-xs text-gray-500 max-w-md text-right">{{ $account->isExactPricing()
                    ? __('Exact prices: type each level\'s purchase price. An empty level is sold at the public price.')
                    : __('Leave a level empty to use its % (shown in grey); type a price to set it exactly.') }}</span>
            </div>

            <template x-for="(v, i) in versions" :key="v.key">
                <div class="pop border border-gray-200 rounded-xl p-4 mb-3">
                    <input type="hidden" :name="`versions[${i}][id]`" :value="v.id">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <div class="md:col-span-6">
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Version name') }} *</label>
                            <input :name="`versions[${i}][name]`" x-model="v.name" required maxlength="200"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Public HT') }} *</label>
                            <input type="number" step="0.01" min="0" :name="`versions[${i}][price]`" x-model="v.price" required
                                class="w-full text-right rounded-lg border-gray-300 text-sm font-semibold focus:border-primary-800 focus:ring-primary-800" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('Currency') }}</label>
                            <select :name="`versions[${i}][currency]`" x-model="v.currency" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                <option value="EUR">EUR</option><option value="USD">USD</option>
                            </select>
                        </div>
                        <div class="md:col-span-1">
                            <button type="button" @click="versions.splice(i, 1)" class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg" title="{{ __('Remove') }}"><i class="ri-delete-bin-line"></i></button>
                        </div>
                    </div>

                    {{-- Purchase price per level --}}
                    <div class="mt-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
                        <template x-for="(lv, li) in levels" :key="lv.n">
                            <div>
                                <label class="block text-[11px] font-medium text-gray-500 mb-1 truncate"><span x-text="lv.name"></span> <span x-text="hint(lv.versions)"></span></label>
                                <input type="number" step="0.01" min="0" :name="`versions[${i}][levels][${li}]`" x-model="v.levels[li]"
                                    :placeholder="ph(v.price, lv.versions)" :aria-label="lv.name"
                                    :class="missing(v.levels[li]) ? 'border-amber-300 bg-amber-50' : 'border-gray-300'"
                                    class="w-full text-right rounded-lg text-sm placeholder:text-gray-400 focus:border-primary-800 focus:ring-primary-800" />
                            </div>
                        </template>
                    </div>

                    {{-- Included equipment --}}
                    <div class="mt-3 pt-3 border-t border-gray-100">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Included equipment') }} <span class="text-gray-400 normal-case font-normal" x-text="'(' + v.equipment.length + ')'"></span></p>
                            <button type="button" @click="openModal(i)" class="inline-flex items-center gap-1 px-2.5 py-1 text-xs font-medium bg-primary-50 hover:bg-primary-100 text-primary-800 rounded-lg"><i class="ri-add-line"></i> {{ __('Add equipment') }}</button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(eq, ei) in v.equipment" :key="ei + '-' + eq">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs border border-emerald-200">
                                    <i class="ri-check-line text-emerald-600"></i><span x-text="eq"></span>
                                    <input type="hidden" :name="`versions[${i}][equipment][]`" :value="eq">
                                    <button type="button" @click="v.equipment.splice(ei, 1)" class="opacity-50 hover:opacity-100 hover:text-red-600 ml-1"><i class="ri-close-line"></i></button>
                                </span>
                            </template>
                            <span x-show="v.equipment.length === 0" class="text-xs text-gray-400 italic">{{ __('No equipment yet.') }}</span>
                        </div>
                    </div>
                </div>
            </template>
            <p x-show="versions.length === 0" class="text-sm text-gray-500 italic mb-3">{{ __('No versions yet — add the first one below.') }}</p>
            <button type="button" @click="addVersion()" class="text-sm font-semibold text-primary-800 hover:underline"><i class="ri-add-line"></i> {{ __('Add version') }}</button>

            {{-- Equipment window: tick to keep, untick to drop, paste one per line to add. --}}
            <div x-show="modal.open" x-cloak x-transition.opacity @keydown.escape.window="modal.open = false"
                class="fixed inset-0 z-50 bg-gray-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                <div @click.outside="modal.open = false" class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl flex flex-col max-h-[90vh]">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0"><i class="ri-tools-line"></i></span>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900">{{ __('Included equipment') }}</h3>
                            <p class="text-xs text-gray-500">{{ __('Untick to remove · paste below to add more.') }}</p>
                        </div>
                        <button type="button" @click="modal.open = false" class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg"><i class="ri-close-line text-xl"></i></button>
                    </div>
                    <div class="p-5 space-y-4 overflow-y-auto">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ __('Current items') }} <span class="text-gray-400 font-normal normal-case" x-text="'(' + modal.list.length + ')'"></span></p>
                            <p x-show="modal.list.length === 0" class="text-xs text-gray-400 italic">{{ __('No items yet — paste some below.') }}</p>
                            <ul class="space-y-1.5">
                                <template x-for="(item, ii) in modal.list" :key="ii + item.label">
                                    <li class="flex items-center gap-2 px-2 py-1.5 rounded border border-gray-200">
                                        <input type="checkbox" x-model="item.checked" class="rounded border-gray-300 text-primary-800 focus:ring-primary-800">
                                        <span class="text-sm text-gray-800 flex-1 truncate" :class="item.checked ? '' : 'line-through text-gray-400'" x-text="item.label"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ __('Add more') }}</p>
                            <textarea x-model="modal.paste" @input="promote()" rows="4" placeholder="{{ __('Bathing platform') }}&#10;{{ __('Bimini top') }}&#10;{{ __('Bow rail') }}"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"></textarea>
                            <p class="text-xs text-gray-500 mt-1">{{ __('One item per line — pasting a whole list works too.') }}</p>
                        </div>
                    </div>
                    <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex justify-end gap-2">
                        <button type="button" @click="modal.open = false" class="px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</button>
                        <button type="button" @click="commit()" class="inline-flex items-center gap-1 px-4 py-1.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-check-line"></i> {{ __('OK') }}</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- Options --}}
        <section x-show="tab === 'options'" x-cloak class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4">
                <h2 class="text-base font-semibold text-gray-900">{{ __('Options') }}</h2>
                <p class="text-xs text-gray-500">{{ __('Optional extras for this boat, grouped by category. Many at once? Use Import from Excel.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm" :style="`min-width: ${760 + levels.length * 110}px`">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-4 py-2.5 w-36">{{ __('Category') }}</th>
                            <th class="px-3 py-2.5">{{ __('Option') }}</th>
                            <th class="px-3 py-2.5 text-right w-28">{{ __('Public HT') }}</th>
                            <th class="px-3 py-2.5 w-24">{{ __('Currency') }}</th>
                            <th class="px-3 py-2.5 text-right w-20">{{ __('VAT') }}</th>
                            <template x-for="lv in levels" :key="lv.n">
                                <th class="px-2 py-2.5 text-right w-28"><span x-text="lv.name"></span> <span class="normal-case font-normal" x-text="hint(lv.options)"></span></th>
                            </template>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <template x-for="(o, i) in options" :key="o.key">
                            <tr class="align-top">
                                <td class="px-4 py-2.5">
                                    <input type="hidden" :name="`options[${i}][id]`" :value="o.id">
                                    <input :name="`options[${i}][category]`" x-model="o.category" maxlength="100" :aria-label="'{{ __('Category') }}'"
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
                                    <select :name="`options[${i}][currency]`" x-model="o.currency" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
                                        <option value="EUR">EUR</option><option value="USD">USD</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2.5">
                                    <input type="number" step="0.1" min="0" max="100" :name="`options[${i}][vat_rate]`" x-model="o.vat_rate" :aria-label="'{{ __('VAT') }}'"
                                        class="w-full text-right rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </td>
                                <template x-for="(lv, li) in levels" :key="lv.n">
                                    <td class="px-2 py-2.5">
                                        <input type="number" step="0.01" min="0" :name="`options[${i}][levels][${li}]`" x-model="o.levels[li]"
                                            :placeholder="ph(o.price, lv.options)" :aria-label="lv.name"
                                            :class="missing(o.levels[li]) ? 'border-amber-300 bg-amber-50' : 'border-gray-300'"
                                            class="w-full text-right rounded-lg text-sm placeholder:text-gray-400 focus:border-primary-800 focus:ring-primary-800" />
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
            <p x-show="options.length === 0" class="px-5 py-3 text-sm text-gray-500 italic">{{ __('No options yet.') }}</p>
            <div class="px-5 py-3 border-t border-gray-100">
                <button type="button" @click="addOption()" class="text-sm font-semibold text-primary-800 hover:underline"><i class="ri-add-line"></i> {{ __('Add option') }}</button>
            </div>
        </section>

        <div class="sticky bottom-0 z-10 mt-4 bg-primary-900 text-white rounded-xl px-5 py-3 flex flex-wrap items-center justify-between gap-3">
            <span class="text-sm">{{ __('Saving publishes to every connected dealer. Removed rows are archived; quotes already made never change.') }}</span>
            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-bold bg-white text-primary-900 rounded-lg hover:bg-primary-50 shrink-0">
                <i class="ri-save-line"></i> {{ __('Save & publish') }}
            </button>
        </div>
    </form>

    @push('scripts')
    <script>
        function brandModelEditor(versions, options, levels, exact) {
            let k = 0;
            const n = levels.length;
            const lv = r => { const a = Array.isArray(r) ? r.slice(0, n) : []; while (a.length < n) a.push(''); return a; };
            const blank = () => Array(n).fill('');
            return {
                tab: (new URLSearchParams(window.location.search)).get('tab') || 'boat',
                levels, exact,
                versions: versions.map(v => ({ ...v, key: k++, levels: lv(v.levels), equipment: Array.isArray(v.equipment) ? v.equipment : [] })),
                options: options.map(o => ({ ...o, key: k++, currency: o.currency || 'EUR', levels: lv(o.levels) })),
                modal: { open: false, index: null, list: [], paste: '' },

                // Exact-price mode: no % estimate; an empty level box is a price still to fill in.
                hint(pct) { return this.exact ? '' : '(−' + pct + '%)'; },
                ph(price, pct) { return this.exact ? @js(__('price required')) : this.calc(price, pct); },
                missing(val) { return this.exact && (val === '' || val === null); },
                calc(price, pct) { const p = parseFloat(price); return isNaN(p) ? '' : (p * (1 - (parseFloat(pct) || 0) / 100)).toFixed(2); },

                addVersion() { this.versions.push({ key: k++, id: '', name: '', price: '', currency: 'EUR', levels: blank(), equipment: [] }); },
                addOption() { this.options.push({ key: k++, id: '', category: '', label: '', description: '', price: '', currency: 'EUR', vat_rate: 20, levels: blank() }); },

                openModal(i) {
                    this.modal = { open: true, index: i, paste: '', list: this.versions[i].equipment.map(label => ({ label, checked: true })) };
                },
                promote() {
                    // Every completed line (Enter or a pasted list) becomes an item; the last, unfinished line stays.
                    const lines = this.modal.paste.split(/\r?\n/);
                    const rest = lines.pop();
                    lines.map(l => l.trim()).filter(Boolean).forEach(label => {
                        if (!this.modal.list.some(x => x.label.toLowerCase() === label.toLowerCase())) this.modal.list.push({ label, checked: true });
                    });
                    this.modal.paste = rest;
                },
                commit() {
                    this.modal.paste += '\n'; this.promote();
                    this.versions[this.modal.index].equipment = this.modal.list.filter(x => x.checked).map(x => x.label);
                    this.modal.open = false;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
