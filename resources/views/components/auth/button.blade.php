{{-- Auth button (Figma "Button", Large 48): 16/22 medium, 20 px side padding, 8 px radius.
     Primary is navy with an arrow-right 20 px; secondary is white with a #d6d6db border.
     Renders a link when given an href, otherwise a submit button. --}}
@props([
    'variant' => 'primary',
    'href' => null,
    'arrow' => null,
    'type' => 'submit',
])

@php
    $primary = $variant === 'primary';
    $arrow = $arrow ?? $primary;
    $classes = 'w-full h-12 inline-flex items-center justify-center gap-2 px-5 rounded-lg text-base leading-[22px] font-medium transition-colors focus:outline-none focus-visible:ring-[3px] focus-visible:ring-primary-800/25 '
        . ($primary
            ? 'bg-primary-800 text-white hover:bg-primary-700'
            : 'bg-white border border-nq-border-strong text-nq-text hover:bg-nq-subtle');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }} @if ($arrow) <i class="ph-bold ph-arrow-right text-xl"></i> @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }} @if ($arrow) <i class="ph-bold ph-arrow-right text-xl"></i> @endif
    </button>
@endif
