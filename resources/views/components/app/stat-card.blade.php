@props([
    'label',
    'value',
    'delta' => null,
    'deltaType' => 'up', // 'up' | 'down' | 'neutral'
    'icon' => 'ri-line-chart-line',
    'iconColor' => 'bg-primary-50 text-primary-800',
])

@php
    $deltaClasses = match ($deltaType) {
        'up'   => 'text-emerald-600 bg-emerald-50',
        'down' => 'text-red-600 bg-red-50',
        default => 'text-gray-600 bg-gray-100',
    };
    $deltaIcon = match ($deltaType) {
        'up'   => 'ri-arrow-up-line',
        'down' => 'ri-arrow-down-line',
        default => 'ri-subtract-line',
    };
@endphp

<div class="bg-white rounded-2xl border border-gray-200 p-5 hover:shadow-sm transition">
    <div class="flex items-start justify-between">
        <div class="min-w-0">
            <p class="text-sm text-gray-500">{{ $label }}</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 truncate">{{ $value }}</p>
        </div>
        <span class="w-10 h-10 rounded-xl {{ $iconColor }} flex items-center justify-center shrink-0">
            <i class="{{ $icon }} text-xl"></i>
        </span>
    </div>
    @if ($delta)
        <div class="mt-3 flex items-center gap-2">
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold {{ $deltaClasses }}">
                <i class="{{ $deltaIcon }}"></i> {{ $delta }}
            </span>
            <span class="text-xs text-gray-500">vs last period</span>
        </div>
    @endif
</div>
