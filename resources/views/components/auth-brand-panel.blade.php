{{-- Decorative branded tile mosaic for the auth pages --}}
<div class="h-full w-full grid grid-cols-4 grid-rows-6 gap-2">
    {{-- row 1 --}}
    <div class="rounded-lg bg-primary-700/80"></div>
    <div class="rounded-lg bg-white col-span-2 flex items-center justify-center p-4">
        <img src="{{ asset('nautiqs_logo.png') }}" alt="{{ config('app.name', 'Nautiqs') }}" class="w-24 h-24 object-contain" />
    </div>
    <div class="rounded-lg bg-primary-700/80"></div>

    {{-- row 2 --}}
    <div class="rounded-lg bg-primary-600/90 flex items-center justify-center text-yellow-300">
        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.09 6.26L20 9l-4.91 4.26L16.18 20 12 16.77 7.82 20l1.09-6.74L4 9l5.91-.74L12 2z"/></svg>
    </div>
    <div class="rounded-lg bg-primary-600/90 flex items-center justify-center">
        <div class="flex gap-1">
            <span class="w-1 h-10 bg-white/70 rounded"></span>
            <span class="w-1 h-10 bg-white/70 rounded"></span>
            <span class="w-1 h-10 bg-white/70 rounded"></span>
            <span class="w-1 h-10 bg-white/70 rounded"></span>
        </div>
    </div>
    <div class="rounded-lg bg-primary-500/90 flex items-center justify-center text-white">
        {{-- wind --}}
        <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" d="M4 8h11a3 3 0 100-6M4 12h15a3 3 0 110 6M4 16h8"/>
        </svg>
    </div>
    <div class="rounded-lg bg-primary-700/80 grid grid-cols-3 grid-rows-3 gap-1 p-3">
        @for ($i=0; $i<9; $i++)
            <span class="bg-white/40 rounded-sm"></span>
        @endfor
    </div>

    {{-- row 3 (compass) --}}
    <div class="rounded-lg bg-primary-700/80 col-span-2 row-span-2 flex items-center justify-center relative">
        <div class="absolute top-3 left-3 text-white/60 text-[10px] tracking-[0.4em]">N · S · E · W</div>
        <svg viewBox="0 0 100 100" class="w-28 h-28 text-white">
            <circle cx="50" cy="50" r="42" fill="none" stroke="currentColor" stroke-width="3"/>
            <path d="M50 18 L56 50 L50 46 L44 50 Z" fill="currentColor"/>
            <path d="M50 82 L44 50 L50 54 L56 50 Z" fill="currentColor" opacity="0.6"/>
        </svg>
    </div>
    <div class="rounded-lg bg-primary-600/90 flex items-center justify-center text-white">
        {{-- anchor --}}
        <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2a2 2 0 100 4 2 2 0 000-4zm1 5.83V11h3v2h-3v6.92c2.44-.39 4.53-1.9 5.68-4.03l-2.14-.7L19 13l3 2-.46.23C20.21 18.98 16.43 22 12 22s-8.21-3.02-9.54-6.77L2 15l3-2 1.46 2.19-2.14.7C5.47 17.93 7.5 19.45 9.94 19.87V13H7v-2h3V7.83A3.001 3.001 0 019 5a3 3 0 016 0 3.001 3.001 0 01-2 2.83z"/></svg>
    </div>
    <div class="rounded-lg bg-primary-600/90 flex items-center justify-center text-yellow-300">
        <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l2.39 7.36H22l-6.19 4.5L18.2 21 12 16.5 5.8 21l2.39-7.14L2 9.36h7.61z"/></svg>
    </div>

    {{-- row 4 --}}
    <div class="rounded-lg bg-primary-600/90 flex items-center justify-center text-yellow-300">
        {{-- lightning --}}
        <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>
    </div>
    <div class="rounded-lg bg-primary-600/90 flex items-center justify-center text-white">
        {{-- target --}}
        <svg class="w-10 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5" fill="currentColor"/>
        </svg>
    </div>

    {{-- row 5 --}}
    <div class="rounded-lg bg-primary-700/80 col-span-2 flex items-center justify-center text-white/70">
        {{-- waves --}}
        <svg class="w-16 h-10" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 64 24">
            <path d="M2 8c6-6 12 6 18 0s12 6 18 0 12 6 18 0M2 16c6-6 12 6 18 0s12 6 18 0 12 6 18 0"/>
        </svg>
    </div>
    <div class="rounded-lg bg-primary-600/90 flex items-center justify-center text-white">
        {{-- mountains --}}
        <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24"><path d="M14 6l-4.5 6.5-3.5-4L2 18h20z"/></svg>
    </div>
    <div class="rounded-lg bg-white flex items-center justify-center">
        <img src="{{ asset('nautiqs_logo.png') }}" alt="" class="w-10 h-10 object-contain" />
        <svg viewBox="0 0 64 64" class="hidden" fill="currentColor"></svg>
    </div>

    {{-- row 6 --}}
    <div class="rounded-lg bg-primary-600/90 flex items-center justify-center text-white">
        {{-- sparkle --}}
        <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l1.5 6.5L20 10l-6.5 1.5L12 18l-1.5-6.5L4 10l6.5-1.5z"/></svg>
    </div>
    <div class="rounded-lg bg-primary-700/80"></div>
    <div class="rounded-lg bg-primary-500/90 col-span-2 flex items-center justify-center text-white/80">
        <svg class="w-16 h-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 64 24">
            <path d="M2 12c6-6 12 6 18 0s12 6 18 0 12 6 18 0"/>
        </svg>
    </div>
</div>
