{{-- Auth notice: success / error / warning / info, in the semantic Figma colours. --}}
@props(['type' => 'success', 'title' => null])

@php
    [$box, $icon, $text] = match ($type) {
        'error'   => ['bg-nq-error-bg border-[#fbd5d6]', 'ph-warning-circle text-nq-error', 'text-nq-error-text'],
        'warning' => ['bg-nq-warning-bg border-[#fbe5bd]', 'ph-warning text-nq-chart3', 'text-[#8a5a12]'],
        'info'    => ['bg-nq-info-bg border-[#d4e9fb]', 'ph-info text-nq-chart2', 'text-[#1d6aa5]'],
        default   => ['bg-nq-success-bg border-nq-success-border', 'ph-check-circle text-nq-success', 'text-nq-success-text'],
    };
@endphp

<div role="{{ $type === 'error' ? 'alert' : 'status' }}" {{ $attributes->merge(['class' => "flex items-start gap-2.5 rounded-lg border px-3 py-2.5 $box"]) }}>
    <i class="ph-bold {{ $icon }} text-base mt-[3px] shrink-0"></i>
    <div class="flex-1 text-[13px] leading-5 {{ $text }}">
        @if ($title) <p class="font-medium">{{ $title }}</p> @endif
        <div>{{ $slot }}</div>
    </div>
</div>
