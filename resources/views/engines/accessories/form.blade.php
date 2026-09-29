<x-app-layout :title="$accessory ? __('Edit accessory') : __('Add accessory')" :header="$accessory ? __('Edit accessory') : __('Add accessory')">
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('engine-accessories.index') }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to accessories') }}
        </a>
    </div>

    @php
        $engineList = $engines->map(fn ($e) => [
            'id'    => (string) $e->_id,
            'label' => trim($e->brand . ' ' . $e->code),
            'hp'    => $e->horsepower ? (float) $e->horsepower : null,
        ])->values();
        $selected = collect(old('engine_ids', $linked))->map(fn ($i) => (string) $i)->values();
    @endphp

    <form method="POST"
        action="{{ $accessory ? route('engine-accessories.update', $accessory->_id) : route('engine-accessories.store') }}"
        class="max-w-3xl space-y-4">
        @csrf
        @if ($accessory) @method('PATCH') @endif

        <div class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Type') }} <span class="text-red-500">*</span></label>
                <div class="grid grid-cols-2 gap-2 max-w-md">
                    @foreach (['kit' => ['ri-dashboard-3-line', __('Pre-rigging kit'), __('Controls, harness, screen')],
                               'propeller' => ['ri-windy-line', __('Propeller'), __('Or a front + rear pair')]] as $value => [$icon, $label, $hint])
                        <label class="relative flex items-start gap-2.5 rounded-lg border p-3 cursor-pointer has-[:checked]:border-primary-800 has-[:checked]:bg-primary-50 border-gray-200 hover:border-gray-300">
                            <input type="radio" name="type" value="{{ $value }}" class="sr-only"
                                @checked(old('type', $accessory->type ?? $type) === $value) />
                            <i class="{{ $icon }} text-lg text-primary-800 mt-0.5"></i>
                            <span>
                                <span class="block text-sm font-medium text-gray-900">{{ $label }}</span>
                                <span class="block text-xs text-gray-500">{{ $hint }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                <input type="text" name="label" required maxlength="500"
                    value="{{ old('label', $accessory->label ?? '') }}"
                    placeholder="{{ __('e.g. Kit pré-rigging MECA - boîtier pupitre simple Keyless + faisceau 6,50 m') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <p class="text-xs text-gray-500 mt-1">{{ __('Printed on the quote as written.') }}</p>
            </div>

            <div class="sm:w-1/2">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Reference') }}</label>
                <input type="text" name="reference" maxlength="120"
                    value="{{ old('reference', $accessory->reference ?? '') }}"
                    placeholder="58100-90JC0-019"
                    class="w-full rounded-lg border-gray-300 font-mono text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Cost (dealer)') }}</label>
                    <input type="number" step="0.01" min="0" name="cost"
                        value="{{ old('cost', $accessory->cost ?? '') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <p class="text-xs text-gray-500 mt-1">{{ __('Internal — never shown to clients.') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Public HT') }} <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="price" required
                        value="{{ old('price', $accessory->price ?? '') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('VAT rate (%)') }}</label>
                    <input type="number" step="0.1" min="0" max="100" name="vat_rate"
                        value="{{ old('vat_rate', $accessory->vat_rate ?? '20') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </div>
        </div>

        {{-- Which engines it goes with. Filter, then "select all shown": the
             MECA kit alone fits twenty engines. --}}
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden"
            x-data="{
                engines: @js($engineList),
                picked: @js($selected),
                q: '',
                get shown() {
                    const n = this.q.trim().toLowerCase();
                    return n === '' ? this.engines : this.engines.filter(e => e.label.toLowerCase().includes(n));
                },
                has(id) { return this.picked.includes(id); },
                flip(id) { this.has(id) ? this.picked = this.picked.filter(x => x !== id) : this.picked.push(id); },
                allShown() { this.shown.forEach(e => { if (! this.has(e.id)) this.picked.push(e.id); }); },
                noneShown() { const ids = this.shown.map(e => e.id); this.picked = this.picked.filter(x => ! ids.includes(x)); },
            }">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ __('Fits these engines') }}</h3>
                    <p class="text-xs text-gray-500">{{ __('Suggested on the quote when one of them is added.') }}</p>
                </div>
                <span class="text-xs font-medium text-primary-800 bg-primary-50 rounded-full px-2 py-0.5">
                    <span x-text="picked.length"></span> {{ __('selected') }}
                </span>
            </div>

            @if ($engines->isEmpty())
                <div class="px-5 py-6 text-sm text-gray-500">
                    {{ __('No engines yet.') }}
                    <a href="{{ route('engines.create') }}" class="text-primary-800 hover:underline">{{ __('Add an engine') }}</a>
                </div>
            @else
                <div class="px-5 py-3 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center gap-2">
                    <div class="relative flex-1">
                        <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="search" x-model="q" placeholder="{{ __('Filter engines, e.g. DF140 or TL/TX…') }}"
                            class="w-full pl-9 pr-3 py-1.5 text-sm rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    </div>
                    <div class="flex gap-2 text-xs">
                        <button type="button" @click="allShown()" class="px-2.5 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 font-medium text-gray-700">
                            {{ __('Select all shown') }}
                        </button>
                        <button type="button" @click="noneShown()" class="px-2.5 py-1.5 rounded-lg border border-gray-300 hover:bg-gray-50 font-medium text-gray-700">
                            {{ __('Clear shown') }}
                        </button>
                    </div>
                </div>

                <div class="max-h-96 overflow-y-auto divide-y divide-gray-50">
                    <template x-for="e in shown" :key="e.id">
                        <label class="flex items-center gap-3 px-5 py-2 cursor-pointer hover:bg-gray-50"
                            :class="has(e.id) ? 'bg-primary-50/40' : ''">
                            <input type="checkbox" :checked="has(e.id)" @change="flip(e.id)"
                                class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                            <span class="flex-1 text-sm text-gray-900" x-text="e.label"></span>
                            <span class="text-xs text-gray-500" x-text="e.hp ? (Math.round(e.hp * 10) / 10) + ' HP' : ''"></span>
                        </label>
                    </template>
                    <template x-if="shown.length === 0">
                        <p class="px-5 py-4 text-sm text-gray-500 italic">{{ __('No engine matches.') }}</p>
                    </template>
                </div>

                {{-- The checked ids posted with the form. --}}
                <template x-for="id in picked" :key="id">
                    <input type="hidden" name="engine_ids[]" :value="id" />
                </template>
            @endif
        </div>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('engine-accessories.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ $accessory ? __('Save changes') : __('Add accessory') }}
            </button>
        </div>
    </form>
</x-app-layout>
