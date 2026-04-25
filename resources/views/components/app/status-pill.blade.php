@props([
    'status' => 'draft',
])

@php
    $map = [
        'draft' => ['label' => 'Draft', 'classes' => 'bg-gray-100 text-gray-700',      'icon' => 'ri-draft-line'],
        'sent'  => ['label' => 'Sent',  'classes' => 'bg-blue-50 text-blue-700',       'icon' => 'ri-send-plane-line'],
        'won'   => ['label' => 'Won',   'classes' => 'bg-emerald-50 text-emerald-700', 'icon' => 'ri-trophy-line'],
        'lost'  => ['label' => 'Lost',  'classes' => 'bg-red-50 text-red-700',         'icon' => 'ri-close-circle-line'],
    ];
    $s = $map[$status] ?? $map['draft'];
@endphp

<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium {{ $s['classes'] }}">
    <i class="{{ $s['icon'] }}"></i>
    {{ $s['label'] }}
</span>
