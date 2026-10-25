<x-app-layout :title="__('Price levels')" :header="__('Price levels')">
    @include('brand.partials.flash')

    @php
        $catLabel = fn ($c) => match ($c) {
            'versions' => __('Boats (versions)'), 'options' => __('Options'),
            'engines' => __('Engines'), 'kits' => __('Kits'), 'propellers' => __('Propellers'), default => $c,
        };
    @endphp

    <p class="mb-4 text-sm text-gray-600 max-w-3xl">{{ __('Each dealer is on one level. Their purchase price for a product is the public price minus the level\'s discount — unless you typed an exact price for that level on the product. Dealers only ever see their own price.') }}</p>

    <form method="POST" action="{{ route('brand.levels.update') }}" class="space-y-4">
        @csrf @method('PATCH')

        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4"><h3 class="font-semibold text-gray-900">{{ __('Discounts by level') }}</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-2.5">{{ __('Level name') }}</th>
                            @foreach ($categories as $c) <th class="px-4 py-2.5">{{ $catLabel($c) }}</th> @endforeach
                            <th class="px-4 py-2.5 text-right">{{ __('Dealers') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($levels as $n => $lvl)
                            <tr>
                                <td class="px-5 py-3">
                                    <label class="block text-[11px] text-gray-500 mb-1" for="lv-{{ $n }}">{{ __('Level :n', ['n' => $n]) }}</label>
                                    <input id="lv-{{ $n }}" name="levels[{{ $n }}][name]" value="{{ old("levels.$n.name", $lvl['name']) }}" required maxlength="60"
                                        class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                </td>
                                @foreach ($categories as $c)
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-gray-500">−</span>
                                            <input type="number" step="0.1" min="0" max="100" name="levels[{{ $n }}][pct][{{ $c }}]"
                                                value="{{ old("levels.$n.pct.$c", $lvl['pct'][$c]) }}" aria-label="{{ $lvl['name'] }} · {{ $catLabel($c) }}"
                                                class="w-20 text-right rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
                                            <span class="text-gray-500">%</span>
                                        </div>
                                    </td>
                                @endforeach
                                <td class="px-4 py-3 text-right text-gray-700">{{ $counts[$n] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($brands->count() > 1)
            <section class="bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
                <div>
                    <h3 class="font-semibold text-gray-900">{{ __('Different terms for one brand?') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Importers often buy each brand on different terms. Customise a brand to override the discounts above for it.') }}</p>
                </div>
                @foreach ($brands as $b)
                    @php $id = (string) $b->_id; $o = is_array($b->level_pcts) ? $b->level_pcts : []; @endphp
                    <div x-data="{ custom: {{ $b->hasOverrides() ? 'true' : 'false' }} }" class="rounded-xl border border-gray-200">
                        <label class="flex items-center gap-3 px-4 py-3 cursor-pointer">
                            <input type="hidden" name="overrides[{{ $id }}][custom]" :value="custom ? 1 : 0">
                            <input type="checkbox" x-model="custom" class="rounded border-gray-300 text-primary-800 focus:ring-primary-800">
                            <span class="font-medium text-gray-900">{{ $b->name }}</span>
                            <span class="text-sm text-gray-500" x-text="custom ? '{{ __('custom discounts') }}' : '{{ __('uses the default') }}'"></span>
                        </label>
                        <div x-show="custom" x-cloak class="px-4 pb-4 overflow-x-auto">
                            <table class="text-sm">
                                <thead><tr class="text-xs text-gray-500"><th class="pr-4 py-1 text-left">{{ __('Level') }}</th>@foreach ($categories as $c)<th class="pr-4 py-1 text-left">{{ $catLabel($c) }}</th>@endforeach</tr></thead>
                                <tbody>
                                    @foreach ($levels as $n => $lvl)
                                        <tr>
                                            <td class="pr-4 py-1 text-gray-700">{{ $lvl['name'] }}</td>
                                            @foreach ($categories as $c)
                                                <td class="pr-4 py-1">
                                                    <div class="flex items-center gap-1"><span class="text-gray-500">−</span>
                                                        <input type="number" step="0.1" min="0" max="100" name="overrides[{{ $id }}][pct][{{ $n }}][{{ $c }}]"
                                                            value="{{ $o[$n][$c] ?? $o[(string) $n][$c] ?? $lvl['pct'][$c] }}" aria-label="{{ $b->name }} · {{ $lvl['name'] }} · {{ $catLabel($c) }}"
                                                            class="w-20 text-right rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" /><span class="text-gray-500">%</span></div>
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
            </section>
        @endif

        <div class="flex justify-end">
            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ __('Save levels') }}
            </button>
        </div>
    </form>
</x-app-layout>
