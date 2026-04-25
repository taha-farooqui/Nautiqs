@props([
    'icon'     => 'ri-inbox-line',
    'title'    => 'Nothing here yet',
    'message'  => null,
    'ctaLabel' => null,
    'ctaHref'  => null,
    'ctaIcon'  => 'ri-add-line',
    'size'     => 'md', // sm | md | lg
])

@php
    $sizes = [
        'sm' => ['py' => 'py-8',  'iw' => 'w-14 h-14', 'is' => 'text-2xl', 'tt' => 'text-sm',  'mt' => 'text-xs'],
        'md' => ['py' => 'py-12', 'iw' => 'w-20 h-20', 'is' => 'text-4xl', 'tt' => 'text-base','mt' => 'text-sm'],
        'lg' => ['py' => 'py-16', 'iw' => 'w-24 h-24', 'is' => 'text-5xl', 'tt' => 'text-lg',  'mt' => 'text-sm'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center ' . $s['py']]) }}>
    <div class="{{ $s['iw'] }} rounded-full bg-primary-50 text-primary-800 flex items-center justify-center mb-4">
        <i class="{{ $icon }} {{ $s['is'] }}"></i>
    </div>
    <h4 class="{{ $s['tt'] }} font-semibold text-gray-900">{{ $title }}</h4>
    @if ($message)
        <p class="mt-1 {{ $s['mt'] }} text-gray-500 max-w-sm">{{ $message }}</p>
    @endif
    @if ($ctaLabel && $ctaHref)
        <a href="{{ $ctaHref }}"
            class="mt-4 inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
            <i class="{{ $ctaIcon }}"></i> {{ $ctaLabel }}
        </a>
    @endif
</div>
