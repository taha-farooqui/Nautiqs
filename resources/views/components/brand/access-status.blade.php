@props(['status'])
@php
    [$label, $cls, $dot] = match ($status) {
        \App\Models\BrandAccess::STATUS_ACTIVE       => [__('Active'), 'text-emerald-700 bg-emerald-50', 'bg-emerald-500'],
        \App\Models\BrandAccess::STATUS_PENDING      => [__('Waiting for answer'), 'text-amber-700 bg-amber-50', 'bg-amber-500 animate-pulse'],
        \App\Models\BrandAccess::STATUS_INVITED      => [__('Not on Nautiqs yet'), 'text-sky-700 bg-sky-50', 'bg-sky-500'],
        \App\Models\BrandAccess::STATUS_DECLINED     => [__('Declined'), 'text-gray-600 bg-gray-100', 'bg-gray-400'],
        \App\Models\BrandAccess::STATUS_REVOKED      => [__('Revoked'), 'text-red-700 bg-red-50', 'bg-red-500'],
        \App\Models\BrandAccess::STATUS_DISCONNECTED => [__('Disconnected'), 'text-gray-600 bg-gray-100', 'bg-gray-400'],
        default                                      => [(string) $status, 'text-gray-700 bg-gray-100', 'bg-gray-400'],
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 text-xs font-semibold rounded-full px-2.5 py-1 $cls"]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $dot }}"></span>{{ $label }}
</span>
