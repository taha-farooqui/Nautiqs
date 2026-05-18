<x-admin-layout :title="__('Platform settings')" :header="__('Platform settings')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            <i class="ri-error-warning-line"></i> {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
        class="space-y-6 max-w-3xl">
        @csrf @method('PATCH')

        {{-- Branding ─────────────────────────────────────────────── --}}
        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('Branding') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Platform name and logo shown across the app.') }}</p>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Platform name') }}</label>
                    <input type="text" name="platform_name" value="{{ old('platform_name', $settings->platform_name ?? 'Nautiqs') }}" maxlength="80"
                        class="w-full max-w-md rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <p class="text-[11px] text-gray-500 mt-1">{{ __('Shown in the browser tab title.') }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Logo') }}</label>
                    <div class="flex items-center gap-4">
                        @if ($settings->logo_path)
                            <img src="{{ asset('storage/' . $settings->logo_path) }}" alt="" class="w-16 h-16 rounded-lg object-contain bg-gray-50 border border-gray-200" />
                        @else
                            <div class="w-16 h-16 rounded-lg bg-gray-50 border border-gray-200 flex items-center justify-center text-gray-300">
                                <i class="ri-image-line text-2xl"></i>
                            </div>
                        @endif
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml"
                            class="text-sm file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-primary-50 file:text-primary-800 file:font-semibold hover:file:bg-primary-100" />
                    </div>
                    <p class="text-[11px] text-gray-500 mt-1">{{ __('PNG, JPG or SVG. Max 2 MB. Replaces the current logo on save.') }}</p>
                </div>
            </div>
        </section>

        {{-- Sign-ups ──────────────────────────────────────────────── --}}
        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('Sign-ups') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Control whether anyone can register a new dealer account on this platform.') }}</p>
            </div>
            <div class="p-6">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="signups_enabled" value="0" />
                    <input type="checkbox" name="signups_enabled" value="1"
                        {{ old('signups_enabled', $settings->signups_enabled ?? true) ? 'checked' : '' }}
                        class="mt-0.5 rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                    <span>
                        <span class="block text-sm font-medium text-gray-900">{{ __('Allow new dealer sign-ups') }}</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">{{ __('When off, the public /register page returns 404. Use this for closed beta or while you onboard dealers manually.') }}</span>
                    </span>
                </label>
            </div>
        </section>

        {{-- Email tracking ────────────────────────────────────────── --}}
        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('Email open tracking') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Public URL where the tracking pixel is reachable from Gmail and Outlook.') }}</p>
            </div>
            <div class="p-6">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Tracking base URL') }}</label>
                <input type="url" name="email_tracking_base_url" value="{{ old('email_tracking_base_url', $settings->email_tracking_base_url ?? '') }}"
                    placeholder="https://nautiqs-production.up.railway.app"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <p class="text-[11px] text-gray-500 mt-1">
                    {{ __('Leave blank to use the EMAIL_TRACKING_BASE_URL env variable. Required in production so Gmail can reach the pixel — without it, opens are not counted.') }}
                </p>
            </div>
        </section>

        {{-- Maintenance mode ──────────────────────────────────────── --}}
        <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('Maintenance mode') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Temporarily block dealer access without taking the platform offline.') }}</p>
            </div>
            <div class="p-6 space-y-5">
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="maintenance_mode" value="0" />
                    <input type="checkbox" name="maintenance_mode" value="1"
                        {{ old('maintenance_mode', $settings->maintenance_mode ?? false) ? 'checked' : '' }}
                        class="mt-0.5 rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                    <span>
                        <span class="block text-sm font-medium text-gray-900">{{ __('Enable maintenance mode') }}</span>
                        <span class="block text-[11px] text-gray-500 mt-0.5">{{ __("When on, dealer users see a maintenance page on any tenant route. Superadmins keep full access to /admin.") }}</span>
                    </span>
                </label>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Maintenance message') }}</label>
                    <textarea name="maintenance_message" rows="2" maxlength="500"
                        placeholder="{{ __('We are upgrading the platform — back online shortly.') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">{{ old('maintenance_message', $settings->maintenance_message ?? '') }}</textarea>
                    <p class="text-[11px] text-gray-500 mt-1">{{ __('Optional. Shown to dealers on the maintenance page when this mode is on.') }}</p>
                </div>
            </div>
        </section>

        {{-- Single save button at the bottom ───────────────────────── --}}
        <div class="flex items-center justify-end">
            <button type="submit"
                class="inline-flex items-center gap-1 px-5 py-2.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-save-line"></i> {{ __('Save all settings') }}
            </button>
        </div>
    </form>
</x-admin-layout>
