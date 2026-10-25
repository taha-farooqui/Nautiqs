@props(['status'])
@php
    [$label, $cls] = match ($status) {
        \App\Models\BrandAccess::STATUS_ACTIVE       => [__('Active'), 'text-emerald-700 bg-emerald-50'],
        \App\Models\BrandAccess::STATUS_PENDING      => [__('Waiting for answer'), 'text-amber-700 bg-amber-50'],
        \App\Models\BrandAccess::STATUS_INVITED      => [__('Not on Nautiqs yet'), 'text-gray-700 bg-gray-100'],
        \App\Models\BrandAccess::STATUS_DECLINED     => [__('Declined'), 'text-red-700 bg-red-50'],
        \App\Models\BrandAccess::STATUS_REVOKED      => [__('Revoked'), 'text-red-700 bg-red-50'],
        \App\Models\BrandAccess::STATUS_DISCONNECTED => [__('Disconnected'), 'text-red-700 bg-red-50'],
        default                                      => [(string) $status, 'text-gray-700 bg-gray-100'],
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-block text-xs font-semibold rounded-full px-2 py-0.5 $cls"]) }}>{{ $label }}</span>
