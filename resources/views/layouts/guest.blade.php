<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="nautiqs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Nautiqs') }}</title>

        <link rel="icon" href="{{ asset('favicon.ico') }}?v=2" sizes="any" />
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v=2" />
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}?v=2" />
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}?v=2" />
        <link rel="manifest" href="{{ asset('site.webmanifest') }}" />

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&family=Geist+Mono:wght@500&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    {{-- Clement's sign-in Figma (frames "01–15 / Light"): 1440 × 900 split into a
         672 px product panel and a 768 px form panel. Light mode only. --}}
    <body class="font-sans antialiased bg-white text-nq-text">
        @php $currentLocale = app()->getLocale(); @endphp
        <div class="min-h-screen lg:grid lg:grid-cols-[672fr_768fr]">

            {{-- Product context (node 106:14174) --}}
            <aside class="hidden lg:block relative bg-nq-layout" aria-hidden="true">
                <div class="sticky top-0 h-screen overflow-hidden">
                    <div class="relative w-full aspect-[672/559]">
                        <img src="{{ asset('images/auth/monitor.jpg') }}" alt=""
                            class="absolute top-0 left-0 w-full aspect-[672/550] object-cover select-none" draggable="false" />
                        {{-- Atmosphere: fade + progressive blur from the monitor base upward.
                             Figma's background blur 12 ≈ CSS blur(6px); the mask makes it ramp
                             in instead of starting on a hard line. --}}
                        <div class="absolute inset-x-0 top-[26.833%] h-[73.167%] backdrop-blur-[6px] [mask-image:linear-gradient(180deg,transparent_0%,#000_55%)] [-webkit-mask-image:linear-gradient(180deg,transparent_0%,#000_55%)]"></div>
                        <div class="absolute inset-x-0 top-[26.833%] h-[73.167%]"
                            style="background-image: linear-gradient(180deg, rgba(247,247,248,0) 0%, rgba(247,247,248,.04) 30%, rgba(247,247,248,.48) 60%, rgba(247,247,248,.94) 85%, #f7f7f8 100%)"></div>
                    </div>
                    <div class="absolute left-16 right-16 bottom-[85px] flex flex-col gap-5">
                        <p class="text-[38px] leading-[46px] font-semibold tracking-[-0.76px] text-nq-navy">
                            {{ __('Configure your boats.') }}<br>{{ __('Prepare your quotes.') }}<br>{{ __('Master your margins.') }}
                        </p>
                        <p class="text-base leading-6 text-nq-text-2">
                            {{ __('Tailored configuration, simpler quotes, margins under control: discover Nautiqs, the sales software for marine professionals.') }}
                        </p>
                    </div>
                </div>
            </aside>

            {{-- Form panel (node 106:15196) --}}
            <main class="min-h-screen flex flex-col bg-white pt-8 pb-6 px-6 sm:px-12">
                <div class="flex items-center justify-between gap-4">
                    <a href="{{ url('/') }}" class="block">
                        <x-application-logo class="h-[28.8px] w-auto" />
                    </a>
                    <nav class="flex gap-0.5 p-[3px] bg-nq-layout rounded-lg" aria-label="{{ __('Language') }}">
                        @foreach (['fr' => 'FR', 'en' => 'EN'] as $code => $label)
                            <a href="{{ route('locale.switch', $code) }}"
                                class="h-8 px-3 flex items-center rounded-md text-sm leading-[22px] transition-colors {{ $currentLocale === $code ? 'bg-white shadow-nq-xs font-medium text-nq-text' : 'text-nq-text-2 hover:text-nq-text' }}"
                                @if ($currentLocale === $code) aria-current="true" @endif>{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>

                <div class="flex-1 flex items-center justify-center py-10">
                    <div class="w-full max-w-[400px] flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>

                <footer class="flex flex-wrap items-center gap-x-3.5 gap-y-1 text-xs leading-5 text-nq-text-2">
                    <p class="flex-1 min-w-max">© {{ date('Y') }} Nautiqs</p>
                    <a href="{{ config('app.privacy_url') }}" target="_blank" rel="noopener" class="hover:text-nq-text">{{ __('Privacy') }}</a>
                    <a href="{{ config('app.legal_url') }}" target="_blank" rel="noopener" class="hover:text-nq-text">{{ __('Legal notice') }}</a>
                    <a href="mailto:{{ config('mail.from.address') }}" class="hover:text-nq-text">{{ __('Help') }}</a>
                </footer>
            </main>
        </div>

        {{-- Show / hide for password fields (x-auth.field type="password"). --}}
        <script>
            document.addEventListener('click', (e) => {
                const b = e.target.closest('[data-toggle-password]');
                if (!b) return;
                const i = document.getElementById(b.dataset.togglePassword);
                const show = i.type === 'password';
                i.type = show ? 'text' : 'password';
                b.querySelector('i').className = 'ph-bold ' + (show ? 'ph-eye-slash' : 'ph-eye') + ' text-base';
                b.setAttribute('aria-pressed', show ? 'true' : 'false');
            });
        </script>
    </body>
</html>
