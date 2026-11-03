{{-- Marks a dealer catalogue item published by a partner brand. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 h-5 px-1.5 rounded-[5px] bg-nq-primary-bg border border-[#d9e6ef] text-primary-800 text-[11px] font-medium align-middle']) }}
    title="{{ __('Managed by the brand — you can set a special price') }}">
    <i class="ph-bold ph-handshake text-xs"></i> {{ __('Partner') }}
</span>
