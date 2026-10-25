{{-- Marks a dealer catalogue item published by a partner brand. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-primary-50 text-primary-800 text-[11px] font-semibold align-middle']) }}
    title="{{ __('Managed by the brand — you can set a special price') }}">
    <i class="ri-links-line"></i> {{ __('Partner') }}
</span>
