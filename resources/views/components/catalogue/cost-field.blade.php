@props(['row', 'index', 'prefix', 'price' => null])
{{--
    Purchase-price (cost) input with a € | % toggle, used in the version and
    option repeaters. In € mode the dealer types an absolute amount; in % mode
    they type a percentage taken off the selling price (cost = price × (1 − %)),
    which the server persists and keeps tracking the selling price.

    Alpine row object must carry: cost, cost_mode ('amount'|'pct'), cost_pct.
    The component relies on a costPreview(row) method in the surrounding
    Alpine component to show the computed € while in % mode.
--}}
<div class="flex items-stretch gap-1">
    <div class="inline-flex shrink-0 rounded-lg border border-gray-300 overflow-hidden text-xs font-semibold">
        <button type="button" @click="{{ $row }}.cost_mode = 'amount'"
            :class="({{ $row }}.cost_mode || 'amount') === 'amount' ? 'bg-primary-800 text-white' : 'bg-white text-gray-500 hover:text-gray-800'"
            class="px-2">€</button>
        <button type="button" @click="{{ $row }}.cost_mode = 'pct'"
            :class="{{ $row }}.cost_mode === 'pct' ? 'bg-primary-800 text-white' : 'bg-white text-gray-500 hover:text-gray-800'"
            class="px-2 border-l border-gray-300">%</button>
    </div>
    <input x-show="({{ $row }}.cost_mode || 'amount') === 'amount'" type="number" step="0.01" min="0"
        :name="`{{ $prefix }}[${{{ $index }}}][cost]`" x-model="{{ $row }}.cost"
        class="w-full min-w-0 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
    <input x-show="{{ $row }}.cost_mode === 'pct'" x-cloak type="number" step="0.01" min="0" max="100"
        :name="`{{ $prefix }}[${{{ $index }}}][cost_pct]`" x-model="{{ $row }}.cost_pct" placeholder="%"
        class="w-full min-w-0 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
</div>
<input type="hidden" :name="`{{ $prefix }}[${{{ $index }}}][cost_mode]`" :value="{{ $row }}.cost_mode || 'amount'" />
<p x-show="{{ $row }}.cost_mode === 'pct'" x-cloak class="text-[11px] text-gray-500 mt-0.5">
    = <span x-text="costPreview({{ $row }})"></span> <span x-text="{{ $row }}.currency || 'EUR'"></span>
</p>
