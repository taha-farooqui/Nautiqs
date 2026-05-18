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
