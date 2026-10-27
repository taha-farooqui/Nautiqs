{{-- A price level's colour and icon follow its number, not its (renamable) name. --}}
@props(['n', 'name' => null, 'size' => 'sm'])
@php
    [$cls, $icon] = match ((int) $n) {
        1       => ['bg-amber-50 text-amber-800 ring-amber-200', 'ri-vip-crown-2-fill'],
        2       => ['bg-slate-100 text-slate-700 ring-slate-200', 'ri-medal-fill'],
        3       => ['bg-orange-50 text-orange-800 ring-orange-200', 'ri-award-fill'],
        4       => ['bg-sky-50 text-sky-800 ring-sky-200', 'ri-star-fill'],
        5       => ['bg-violet-50 text-violet-800 ring-violet-200', 'ri-star-fill'],
        6       => ['bg-emerald-50 text-emerald-800 ring-emerald-200', 'ri-star-fill'],
        7       => ['bg-rose-50 text-rose-800 ring-rose-200', 'ri-star-fill'],
        8       => ['bg-teal-50 text-teal-800 ring-teal-200', 'ri-star-fill'],
        9       => ['bg-indigo-50 text-indigo-800 ring-indigo-200', 'ri-star-fill'],
        default => ['bg-lime-50 text-lime-800 ring-lime-200', 'ri-star-fill'],
    };
    $pad = $size === 'lg' ? 'px-3 py-1 text-sm' : 'px-2 py-0.5 text-xs';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full font-semibold ring-1 ring-inset $cls $pad"]) }}>
    <i class="{{ $icon }}"></i>{{ $name ?? __('Level :n', ['n' => $n]) }}
</span>
