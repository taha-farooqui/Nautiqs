{{--
    Price level chooser as cards. $model is the Alpine property the choice is
    bound to; the radio posts as "level".
--}}
@php
    $catShort = fn ($c) => match ($c) {
        'versions' => __('boats'), 'options' => __('options'), 'engines' => __('engines'),
        'kits' => __('kits'), 'propellers' => __('propellers'), default => $c,
    };
@endphp
<div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
    @foreach ($levels as $n => $lvl)
        <label class="lift relative cursor-pointer rounded-xl border-2 p-3.5 transition"
            :class="{{ $model }} == {{ $n }} ? 'border-primary-800 bg-primary-50/60' : 'border-gray-200 hover:border-gray-300 bg-white'">
            <input type="radio" name="level" value="{{ $n }}" x-model.number="{{ $model }}" class="sr-only">
            <span class="absolute top-2.5 right-2.5 w-5 h-5 rounded-full flex items-center justify-center transition"
                :class="{{ $model }} == {{ $n }} ? 'bg-primary-800 text-white scale-100' : 'bg-gray-100 text-transparent scale-90'"><i class="ri-check-line text-xs"></i></span>
            <x-brand.level-badge :n="$n" :name="$lvl['name']" />
            <div class="mt-2.5 space-y-1">
                @foreach ($categories as $c)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-500">{{ $catShort($c) }}</span>
                        <span class="font-semibold {{ $lvl['pct'][$c] > 0 ? 'text-gray-900' : 'text-gray-400' }}">−{{ rtrim(rtrim(number_format($lvl['pct'][$c], 1, ',', ''), '0'), ',') }} %</span>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 pt-2 border-t border-gray-100 text-[11px] text-gray-500">
                <i class="ri-store-2-line"></i> {{ trans_choice('{0}no dealer|{1}:count dealer|[2,*]:count dealers', $levelCounts[$n] ?? 0, ['count' => $levelCounts[$n] ?? 0]) }}
            </div>
        </label>
    @endforeach
</div>
