{{-- Auth input (Figma "Field" + "Input"): 14/22 medium label, 8 px gap, 48 px field on
     #f7f7f8 with a #d6d6db border, 8 px radius, 12 px padding. Passwords get the eye. --}}
@props([
    'label',
    'name',
    'type' => 'text',
    'id' => null,
    'value' => null,
    'placeholder' => null,
])

@php
    $id = $id ?? $name;
    $hasError = $errors->has($name);
    $isPassword = $type === 'password';
@endphp

<div class="flex flex-col gap-2">
    <label for="{{ $id }}" class="text-sm leading-[22px] font-medium text-nq-text">{{ $label }}</label>
    <div class="relative">
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
            @if (! $isPassword && $value !== null) value="{{ $value }}" @endif
            @if ($placeholder) placeholder="{{ $placeholder }}" @endif
            @if ($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->class([
                'block w-full h-12 rounded-lg border bg-nq-layout px-3 text-sm leading-[22px] text-nq-text placeholder:text-nq-text-2 shadow-none transition-colors',
                'focus:outline-none focus:ring-[3px]',
                'disabled:text-nq-text-2 disabled:cursor-not-allowed',
                'border-nq-error focus:border-nq-error focus:ring-nq-error/15' => $hasError,
                'border-nq-border-strong hover:border-primary-700 focus:border-primary-800 focus:ring-primary-800/15' => ! $hasError,
                'pr-11' => $isPassword,
            ]) }} />
        @if ($isPassword)
            <button type="button" data-toggle-password="{{ $id }}" aria-pressed="false" aria-label="{{ __('Show password') }}"
                class="absolute inset-y-0 right-0 w-11 flex items-center justify-center text-nq-text-2 hover:text-nq-text">
                <i class="ph-bold ph-eye text-base"></i>
            </button>
        @endif
    </div>
    @if ($hasError)
        <p id="{{ $id }}-error" class="text-xs leading-5 text-nq-error-text">{{ $errors->first($name) }}</p>
    @endif
</div>
