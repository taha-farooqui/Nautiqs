<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="nautiqs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Nautiqs') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('nautiqs_logo.png') }}" />
        <link rel="apple-touch-icon" href="{{ asset('nautiqs_logo.png') }}" />

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-base-200 text-gray-900">
        <div class="min-h-screen flex items-center justify-center px-4 py-8">
            <div class="w-full max-w-6xl bg-white rounded-2xl shadow-sm overflow-hidden grid grid-cols-1 lg:grid-cols-2">
                {{-- Left: form --}}
                <div class="p-8 sm:p-12 flex flex-col justify-center">
                    <a href="/" class="inline-flex items-center mb-8">
                        <x-application-logo class="w-10 h-10" />
                    </a>
                    {{ $slot }}
                </div>

                {{-- Right: branded tile pattern --}}
                <div class="hidden lg:block bg-primary-800 p-6">
                    <x-auth-brand-panel />
                </div>
            </div>
        </div>

        {{-- Guest locale switcher — tiny pill bottom-right so the login
             screen can be French or English without a separate widget. --}}
        @php $currentLocale = app()->getLocale(); @endphp
        <div class="fixed bottom-4 right-4 flex items-center gap-1 bg-white rounded-full border border-gray-200 shadow-sm text-xs">
            <a href="{{ route('locale.switch', 'fr') }}"
               class="px-3 py-1.5 rounded-full {{ $currentLocale === 'fr' ? 'bg-primary-800 text-white font-semibold' : 'text-gray-600 hover:text-gray-900' }}">FR</a>
            <a href="{{ route('locale.switch', 'en') }}"
               class="px-3 py-1.5 rounded-full {{ $currentLocale === 'en' ? 'bg-primary-800 text-white font-semibold' : 'text-gray-600 hover:text-gray-900' }}">EN</a>
        </div>

        <p class="text-center text-xs text-gray-500 mt-6 pb-6">
            © {{ date('Y') }} {{ config('app.name', 'Nautiqs') }}. {{ __('All rights reserved.') }}
        </p>
    </body>
</html>
