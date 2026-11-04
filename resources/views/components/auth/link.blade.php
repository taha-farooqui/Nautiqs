{{-- Auth text link (Figma): 13/20 medium, primary text, centred across the 400 px column. --}}
@props(['href' => null])

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => 'block text-center text-[13px] leading-5 font-medium text-primary-800 hover:underline']) }}>{{ $slot }}</a>
@else
    <button type="submit" {{ $attributes->merge(['class' => 'block w-full text-center text-[13px] leading-5 font-medium text-primary-800 hover:underline']) }}>{{ $slot }}</button>
@endif
