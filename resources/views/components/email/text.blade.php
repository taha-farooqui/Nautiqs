{{-- Body paragraph: 14/22, secondary text. `strong` for the main sentence. --}}
@props(['strong' => false, 'small' => false])
<p style="margin:0 0 16px; font-size:{{ $small ? '13px' : '14px' }}; line-height:{{ $small ? '20px' : '22px' }}; color:{{ $strong ? '#0b0b0d' : ($small ? '#73737c' : '#55555e') }};">{{ $slot }}</p>
