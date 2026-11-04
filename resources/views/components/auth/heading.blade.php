{{-- Auth screen heading (Figma "Heading"): 30/38 semibold title, 14/22 subtitle, 12 px gap. --}}
@props(['title'])

<div class="flex flex-col gap-3">
    <h1 class="text-[30px] leading-[38px] font-semibold tracking-[-0.6px] text-nq-text">{{ $title }}</h1>
    @if ($slot->isNotEmpty())
        <p class="text-sm leading-[22px] text-nq-text-2">{{ $slot }}</p>
    @endif
</div>
