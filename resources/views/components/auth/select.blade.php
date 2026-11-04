{{-- Auth select (Figma "Input", Kind=Select): same 48 px field, caret-down 16 px suffix. --}}
@props([
    'label',
    'name',
    'options' => [],
    'value' => null,
    'id' => null,
])

@php
    $id = $id ?? $name;
    $hasError = $errors->has($name);
@endphp

<div class="flex flex-col gap-2">
    <label for="{{ $id }}" class="text-sm leading-[22px] font-medium text-nq-text">{{ $label }}</label>
    <div class="relative">
        <select id="{{ $id }}" name="{{ $name }}"
            {{ $attributes->class([
                'block w-full h-12 appearance-none rounded-lg border bg-nq-layout bg-none pl-3 pr-10 text-sm leading-[22px] text-nq-text shadow-none transition-colors',
                'focus:outline-none focus:ring-[3px]',
                'border-nq-error focus:border-nq-error focus:ring-nq-error/15' => $hasError,
                'border-nq-border-strong hover:border-primary-700 focus:border-primary-800 focus:ring-primary-800/15' => ! $hasError,
            ]) }}>
            @foreach ($options as $key => $optionLabel)
                <option value="{{ $key }}" @selected((string) $value === (string) $key)>{{ $optionLabel }}</option>
            @endforeach
        </select>
        <i class="ph-bold ph-caret-down text-base text-nq-text-2 absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
    </div>
    @if ($hasError)
        <p class="text-xs leading-5 text-nq-error-text">{{ $errors->first($name) }}</p>
    @endif
</div>
