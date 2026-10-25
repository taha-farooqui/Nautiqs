{{-- A price level's colour and icon follow its number, not its (renamable) name. --}}
@props(['n', 'name' => null, 'size' => 'sm'])
@php
    [$cls, $icon] = match ((int) $n) {
        1       => ['bg-amber-50 text-amber-800 ring-amber-200', 'ri-vip-crown-2-fill'],
        2       => ['bg-slate-100 text-slate-700 ring-slate-200', 'ri-medal-fill'],
        default => ['bg-orange-50 text-orange-800 ring-orange-200', 'ri-award-fill'],
    };
    $pad = $size === 'lg' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full font-semibold ring-1 ring-inset $cls $pad"]) }}>
    <i class="{{ $icon }}"></i>{{ $name ?? __('Level :n', ['n' => $n]) }}
</span>
