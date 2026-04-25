<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="nautiqs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? config('app.name', 'Nautiqs') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

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

        <p class="text-center text-xs text-gray-500 mt-6 pb-6">
            © {{ date('Y') }} {{ config('app.name', 'Nautiqs') }}. All rights reserved.
        </p>
    </body>
</html>
