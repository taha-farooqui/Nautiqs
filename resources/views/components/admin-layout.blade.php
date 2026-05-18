@props([
    'title'  => null,
    'header' => null,
])

{{--
    Platform-side layout (/admin). Same chrome family as <x-app-layout> but
    visually distinct so the superadmin can never confuse a global-catalogue
    edit for a tenant-side action:
      - Darker top bar (slate-900 instead of white)
      - PLATFORM badge next to the page title
      - User menu has no "Company settings" / notifications stubs (tenant concepts)
      - Same sidebar — it already switches to the superadmin nav based on role
--}}

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="nautiqs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? __('Platform') }} · {{ config('app.name', 'Nautiqs') }} {{ __('Platform') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('nautiqs_logo.png') }}" />
        <link rel="apple-touch-icon" href="{{ asset('nautiqs_logo.png') }}" />

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@100..900&display=swap" rel="stylesheet" />

        <link href="https://cdn.jsdelivr.net/npm/remixicon@4.6.0/fonts/remixicon.css" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('head')
    </head>
    <body class="font-sans antialiased bg-slate-50 text-gray-800">
        <div class="min-h-screen flex" x-data="{ sidebarOpen: false }">
            <x-app.sidebar />

            <div
                x-show="sidebarOpen"
                x-transition.opacity
                @click="sidebarOpen = false"
                class="fixed inset-0 bg-gray-900/40 z-30 lg:hidden"
                style="display: none;"></div>

            <div class="flex-1 flex flex-col min-w-0 lg:ml-72">
                {{-- Slate top bar — visually unmistakable from the white tenant header. --}}
                <header class="sticky top-0 z-20 bg-slate-900 text-white border-b border-slate-800">
                    <div class="h-16 px-4 sm:px-6 lg:px-8 flex items-center gap-4">
                        <button @click="sidebarOpen = true" class="lg:hidden text-white/80 hover:text-white">
                            <i class="ri-menu-line text-2xl"></i>
                        </button>

                        <div class="flex items-center gap-3 min-w-0">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-400/15 text-amber-300 text-[10px] font-bold uppercase tracking-wider">
                                <i class="ri-shield-star-line"></i> {{ __('Platform') }}
                            </span>
                            <h1 class="text-lg font-semibold truncate">{{ $header ?? ($title ?? __('Overview')) }}</h1>
                        </div>

                        @php $currentLocale = app()->getLocale(); @endphp
                        <div class="ml-auto flex items-center gap-2">
                            {{-- Locale switcher (FR/EN) — drops a `locale` cookie and reloads. --}}
                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open"
                                    class="h-9 px-2.5 inline-flex items-center gap-1 text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition text-xs font-medium"
                                    :title="'{{ __('Language') }}'">
                                    <i class="ri-translate-2 text-base"></i>
                                    <span>{{ strtoupper($currentLocale) }}</span>
                                    <i class="ri-arrow-down-s-line text-sm"></i>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-cloak x-transition
                                    class="absolute right-0 mt-2 w-44 bg-white text-gray-900 border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                                    <a href="{{ route('locale.switch', 'fr') }}"
                                        class="w-full text-left px-4 py-2 text-sm flex items-center justify-between hover:bg-gray-50 {{ $currentLocale === 'fr' ? 'text-primary-800 font-semibold bg-primary-50/50' : 'text-gray-700' }}">
                                        <span>Français</span>
                                        @if ($currentLocale === 'fr') <i class="ri-check-line"></i> @endif
                                    </a>
                                    <a href="{{ route('locale.switch', 'en') }}"
                                        class="w-full text-left px-4 py-2 text-sm flex items-center justify-between hover:bg-gray-50 {{ $currentLocale === 'en' ? 'text-primary-800 font-semibold bg-primary-50/50' : 'text-gray-700' }}">
                                        <span>English</span>
                                        @if ($currentLocale === 'en') <i class="ri-check-line"></i> @endif
                                    </a>
                                </div>
                            </div>

                            <a href="{{ route('dashboard') }}"
                                class="hidden sm:inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-white/80 hover:text-white hover:bg-white/10 rounded-lg transition">
                                <i class="ri-arrow-left-line"></i> {{ __('Back to app') }}
                            </a>

                            <div x-data="{ open: false }" class="relative">
                                <button @click="open = !open"
                                    class="flex items-center gap-2 pl-1 pr-2 py-1 hover:bg-white/10 rounded-lg transition">
                                    <span class="w-8 h-8 rounded-full bg-amber-400 text-slate-900 font-semibold text-sm flex items-center justify-center">
                                        @php
                                            $u = auth()->user();
                                            $initials = collect(explode(' ', $u?->name ?? 'A'))
                                                ->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join('');
                                        @endphp
                                        {{ strtoupper($initials) }}
                                    </span>
                                    <div class="hidden sm:block text-left">
                                        <div class="text-sm font-semibold leading-tight">{{ $u?->name }}</div>
                                        <div class="text-xs text-white/60 leading-tight">{{ __('Superadmin') }}</div>
                                    </div>
                                    <i class="ri-arrow-down-s-line text-white/60"></i>
                                </button>
                                <div x-show="open" @click.outside="open = false" x-transition
                                    class="absolute right-0 mt-2 w-56 bg-white text-gray-900 border border-gray-200 rounded-xl shadow-lg overflow-hidden"
                                    style="display: none;">
                                    <div class="px-4 py-3 border-b border-gray-100">
                                        <p class="text-sm font-semibold truncate">{{ $u?->name }}</p>
                                        <p class="text-xs text-gray-500 truncate">{{ $u?->email }}</p>
                                    </div>
                                    <div class="py-1">
                                        <a href="{{ route('profile.edit') }}"
                                            class="flex items-center gap-3 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                            <i class="ri-user-settings-line"></i> {{ __('Profile') }}
                                        </a>
                                    </div>
                                    <div class="py-1 border-t border-gray-100">
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button type="submit"
                                                class="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 hover:bg-red-50 text-left">
                                                <i class="ri-logout-box-r-line"></i> {{ __('Log out') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="flex-1 px-4 sm:px-6 lg:px-8 py-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        {{-- SweetAlert2 — same delegated handler as the tenant layout. --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
            (function () {
                const PRIMARY = '#0e4f79';
                const t = {
                    confirmYes: @json(__('Yes, do it')),
                    cancel:     @json(__('Cancel')),
                };
                document.addEventListener('submit', function (e) {
                    const form = e.target;
                    if (! (form instanceof HTMLFormElement)) return;
                    if (! form.dataset.confirm) return;
                    if (form.dataset.confirmed === '1') return;
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
                        if (res.isConfirmed) { form.dataset.confirmed = '1'; form.submit(); }
                    });
                }, true);
            })();
        </script>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
