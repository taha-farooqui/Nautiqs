@props([
    'title'  => null,
    'header' => null,
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="nautiqs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Dashboard' }} · {{ config('app.name', 'Nautiqs') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('nautiqs_logo.png') }}" />
        <link rel="apple-touch-icon" href="{{ asset('nautiqs_logo.png') }}" />

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        {{-- Remixicon --}}
        <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('head')
    </head>
    <body class="font-sans antialiased bg-base-200 text-gray-800">
        <div class="min-h-screen flex" x-data="{ sidebarOpen: false }">
            <x-app.sidebar />

            <div
                x-show="sidebarOpen"
                x-transition.opacity
                @click="sidebarOpen = false"
                class="fixed inset-0 bg-gray-900/40 z-30 lg:hidden"
                style="display: none;"></div>

            <div class="flex-1 flex flex-col min-w-0 lg:ml-72">
                <x-app.header :title="$header ?? ($title ?? 'Dashboard')" />

                <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Global ⌘K command palette --}}
        <x-app.search-palette />

        @livewireScripts
        @stack('scripts')
    </body>
</html>
