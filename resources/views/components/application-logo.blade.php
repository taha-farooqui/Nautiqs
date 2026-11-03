{{-- Nautiqs logo. full = sails + wordmark (navy, for light backgrounds; white
     = the same on dark ones); icon = the sails on a navy square. --}}
@props(['variant' => 'full'])
@php
    $src = match ($variant) {
        'icon'  => 'nautiqs-icon.png',
        'white' => 'nautiqs-logo-white.png',
        default => 'nautiqs-logo.png',
    };
@endphp
<img
    src="{{ asset($src) }}"
    alt="{{ config('app.name', 'Nautiqs') }}"
    {{ $attributes->merge(['class' => 'object-contain']) }}
/>
