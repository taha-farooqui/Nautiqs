@props(['name', 'value' => '', 'options' => [], 'placeholder' => '', 'icon' => null])
{{--
    Single-value combobox: a text field with a dropdown of suggestions. The
    dealer picks one, or types their own and presses Enter — the field keeps
    whatever is typed. Used for the accessory brand and category.
--}}
<div x-data="{
        open: false,
        q: @js((string) $value),
        opts: @js(array_values(array_filter(array_map('strval', $options), fn ($o) => $o !== ''))),
        hi: -1,
        get matches() {
            const s = this.q.trim().toLowerCase();
            return this.opts.filter(o => s === '' || o.toLowerCase().includes(s)).slice(0, 12);
        },
        pick(v) { this.q = v; this.open = false; this.hi = -1; },
    }"
    class="relative" @click.outside="open = false">
    <div class="relative">
        @if ($icon)
            <i class="{{ $icon }} absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
        @endif
        <input type="text" name="{{ $name }}" x-model="q" autocomplete="off" maxlength="120"
            @focus="open = true" @input="open = true; hi = -1"
            @keydown.arrow-down.prevent="open = true; hi = Math.min(hi + 1, matches.length - 1)"
            @keydown.arrow-up.prevent="hi = Math.max(hi - 1, -1)"
            @keydown.enter.prevent="hi >= 0 && matches[hi] ? pick(matches[hi]) : open = false"
            @keydown.escape="open = false"
            placeholder="{{ $placeholder }}"
            class="w-full {{ $icon ? 'pl-9' : '' }} rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
    </div>
    <ul x-show="open && matches.length" x-cloak x-transition.opacity
        class="absolute z-20 mt-1 w-full max-h-56 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-lg py-1 text-sm">
        <template x-for="(o, i) in matches" :key="o">
            <li>
                <button type="button" @mousedown.prevent="pick(o)" @mouseenter="hi = i"
                    class="w-full text-left px-3 py-2" :class="hi === i ? 'bg-primary-50 text-primary-900' : 'text-gray-700 hover:bg-gray-50'"
                    x-text="o"></button>
            </li>
        </template>
    </ul>
</div>
