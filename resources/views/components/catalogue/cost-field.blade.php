@props(['row', 'index', 'prefix', 'price' => null])
@php
    // Build the Alpine :name expressions with plain string concatenation so we
    // never nest a Blade echo inside a JS `${…}` template literal (that
    // collapses to a literal `$i` and posts a phantom row).
    $nm = fn (string $field) => "'{$prefix}[' + {$index} + '][{$field}]'";
@endphp
{{--
    Purchase-price (cost) input with a € | % toggle. In € mode the dealer types
    an absolute amount; in % mode they type a percentage (0–100) taken off the
    selling price (cost = price × (1 − %)), which the server persists and keeps
    tracking the selling price. Needs cost / cost_mode / cost_pct on the row and
    a costPreview(row) method on the surrounding Alpine component.
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
        :name="{!! $nm('cost') !!}" x-model="{{ $row }}.cost"
        class="w-full min-w-0 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
    <input x-show="{{ $row }}.cost_mode === 'pct'" x-cloak type="number" step="0.01" min="0" max="100"
        :name="{!! $nm('cost_pct') !!}" x-model="{{ $row }}.cost_pct"
        @input="if ({{ $row }}.cost_pct !== '' && {{ $row }}.cost_pct !== null) { {{ $row }}.cost_pct = Math.min(100, Math.max(0, parseFloat({{ $row }}.cost_pct) || 0)); }"
        placeholder="%"
        class="w-full min-w-0 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
</div>
<input type="hidden" :name="{!! $nm('cost_mode') !!}" :value="{{ $row }}.cost_mode || 'amount'" />
<p x-show="{{ $row }}.cost_mode === 'pct'" x-cloak class="text-[11px] text-gray-500 mt-0.5">
    = <span x-text="costPreview({{ $row }})"></span> <span x-text="{{ $row }}.currency || 'EUR'"></span>
</p>
