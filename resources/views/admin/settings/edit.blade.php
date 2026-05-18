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

    {{-- Branding card --}}
    <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">{{ __('Branding') }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">{{ __('Platform name, logo, and the "From" identity on system emails.') }}</p>
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

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email sender name') }}</label>
                    <input type="text" name="email_sender_name" value="{{ old('email_sender_name', $settings->email_sender_name ?? '') }}" maxlength="120"
                        placeholder="e.g. Nautiqs"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <p class="text-[11px] text-gray-500 mt-1">{{ __('Name shown next to system emails (verification, password reset).') }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email sender address') }}</label>
                    <input type="email" name="email_sender_address" value="{{ old('email_sender_address', $settings->email_sender_address ?? '') }}" maxlength="160"
                        placeholder="e.g. hello@nautiqs.com"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <p class="text-[11px] text-gray-500 mt-1">{{ __('Falls back to the MAIL_FROM_ADDRESS env when blank.') }}</p>
                </div>
            </div>

            <div class="flex items-center justify-end pt-3 border-t border-gray-100">
                <button type="submit"
                    class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                    <i class="ri-save-line"></i> {{ __('Save branding') }}
                </button>
            </div>
        </form>
    </section>

    {{-- Default email templates --}}
    <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-900">{{ __('Default email templates') }}</h2>
            <p class="text-xs text-gray-500 mt-0.5">
                {{ __('New dealers inherit these templates on signup. They can edit their own copy afterwards — your changes only affect future signups.') }}
            </p>
        </div>

        <div x-data="{ open: 'quote' }">
            <div class="flex border-b border-gray-100 px-2">
                @foreach ($templateTypes as $type => $meta)
                    <button type="button" @click="open = '{{ $type }}'"
                        :class="open === '{{ $type }}' ? 'border-primary-800 text-primary-900' : 'border-transparent text-gray-500 hover:text-gray-900'"
                        class="px-4 py-3 text-sm font-medium border-b-2 transition flex items-center gap-2">
                        <i class="{{ $meta['icon'] }}"></i> {{ __($meta['label']) }}
                    </button>
                @endforeach
            </div>

            @foreach ($templateTypes as $type => $meta)
                @php
                    $current = $settings->default_email_templates[$type] ?? null;
                @endphp
                <div x-show="open === '{{ $type }}'" x-cloak>
                    <form method="POST" action="{{ route('admin.settings.template.update', $type) }}" class="p-6 space-y-4">
                        @csrf @method('PATCH')

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Subject') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="subject" required maxlength="300"
                                value="{{ old('subject', $current['subject'] ?? '') }}"
                                placeholder="{{ __('e.g. Your quote :quote_number from :company_name') }}"
                                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Body') }} <span class="text-red-500">*</span></label>
                            <textarea name="body" required rows="10"
                                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 font-mono text-xs"
                                placeholder="{{ __('HTML allowed. See the variable list below.') }}">{{ old('body', $current['body'] ?? '') }}</textarea>
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-700">
                            <p class="font-semibold mb-1.5">{{ __('Available variables') }}</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-3 gap-y-0.5">
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;client_name&#125;&#125;</code>
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;client_first_name&#125;&#125;</code>
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;quote_number&#125;&#125;</code>
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;order_number&#125;&#125;</code>
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;boat_model&#125;&#125;</code>
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;total_ttc&#125;&#125;</code>
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;salesperson_name&#125;&#125;</code>
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;company_name&#125;&#125;</code>
                                <code class="font-mono text-[11px] text-primary-800">&#123;&#123;date&#125;&#125;</code>
                            </div>
                        </div>

                        <div class="flex items-center justify-end pt-2 border-t border-gray-100">
                            <button type="submit"
                                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                <i class="ri-save-line"></i> {{ __('Save template') }}
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </section>
</x-admin-layout>
