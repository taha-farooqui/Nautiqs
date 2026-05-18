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

    <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden max-w-3xl">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">{{ __('Branding') }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Platform name and logo shown across the app.') }}</p>
        </div>

        <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data"
            class="p-6 space-y-5">
            @csrf @method('PATCH')

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

            <div class="flex items-center justify-end pt-3 border-t border-gray-100">
                <button type="submit"
                    class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                    <i class="ri-save-line"></i> {{ __('Save changes') }}
                </button>
            </div>
        </form>
    </section>
</x-admin-layout>
