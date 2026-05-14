@props([
    'title'  => null,
    'header' => null,
])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="nautiqs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? __('Dashboard') }} · {{ config('app.name', 'Nautiqs') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('nautiqs_logo.png') }}" />
        <link rel="apple-touch-icon" href="{{ asset('nautiqs_logo.png') }}" />

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet" />

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
                <x-app.header :title="$header ?? ($title ?? __('Dashboard'))" />

                <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- Global ⌘K command palette --}}
        <x-app.search-palette />

        {{-- SweetAlert2 — used for every destructive confirmation across
             the app instead of the browser's native confirm(). Forms opt
             in by adding `data-confirm="Your question"` (or the longer
             `data-confirm-text` for body copy). --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            (function () {
                const PRIMARY = '#0e4f79';
                const t = {
                    confirmYes: @json(__('Yes, do it')),
                    cancel:     @json(__('Cancel')),
                };

                // Single delegated submit handler — covers every <form
                // data-confirm="…"> on the page without each form needing
                // its own onsubmit attribute.
                document.addEventListener('submit', function (e) {
                    const form = e.target;
                    if (! (form instanceof HTMLFormElement)) return;
                    if (! form.dataset.confirm) return;
                    if (form.dataset.confirmed === '1') return; // second pass after user clicks Yes

                    e.preventDefault();
                    Swal.fire({
                        title: form.dataset.confirm,
                        text:  form.dataset.confirmText || '',
                        icon:  form.dataset.confirmIcon || 'warning',
                        showCancelButton: true,
                        confirmButtonText: form.dataset.confirmYes || t.confirmYes,
                        cancelButtonText:  t.cancel,
                        confirmButtonColor: form.dataset.confirmDanger === '1' ? '#dc2626' : PRIMARY,
                        cancelButtonColor: '#6b7280',
                        reverseButtons: true,
                    }).then((res) => {
                        if (res.isConfirmed) {
                            form.dataset.confirmed = '1';
                            form.submit();
                        }
                    });
                }, true);

                // Programmatic helper for non-form actions (e.g. an inline
                // <button> that submits a different form via JS). Returns a
                // Promise<boolean>.
                window.nautiqsConfirm = function (opts) {
                    return Swal.fire({
                        title: opts.title || '',
                        text:  opts.text  || '',
                        icon:  opts.icon  || 'warning',
                        showCancelButton: true,
                        confirmButtonText: opts.confirmText || t.confirmYes,
                        cancelButtonText:  opts.cancelText  || t.cancel,
                        confirmButtonColor: opts.danger ? '#dc2626' : PRIMARY,
                        cancelButtonColor: '#6b7280',
                        reverseButtons: true,
                    }).then(r => r.isConfirmed);
                };
            })();
        </script>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
