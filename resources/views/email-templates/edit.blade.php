<x-app-layout :title="$meta['name'] . ' email template'" :header="$meta['name'] . ' email template'">

    @push('head')
        {{-- Trix WYSIWYG editor --}}
        <link rel="stylesheet" type="text/css" href="https://unpkg.com/trix@2.1.15/dist/trix.css">
        <script src="https://unpkg.com/trix@2.1.15/dist/trix.umd.min.js" defer></script>
        <style>
            /* Brand the Trix toolbar with our primary color */
            trix-toolbar { background: #f8fafc; border: 1px solid #e5e7eb; border-bottom: none; border-radius: 0.5rem 0.5rem 0 0; padding: 0.5rem; }
            trix-toolbar .trix-button-group { border-color: #e5e7eb; background: #ffffff; }
            trix-toolbar .trix-button { border-color: #e5e7eb; }
            trix-toolbar .trix-button.trix-active { background: #0e4f79; color: #ffffff; }
            trix-toolbar .trix-button:not(:disabled):hover { background: #e0e7ee; }
            trix-toolbar .trix-button.trix-active:hover { background: #0e4f79; }
            trix-editor { border: 1px solid #e5e7eb; border-radius: 0 0 0.5rem 0.5rem; min-height: 320px; padding: 1rem; background: #ffffff; }
            trix-editor:focus { outline: none; border-color: #0e4f79; box-shadow: 0 0 0 3px rgba(14,79,121,0.15); }
            /* Hide the file-attachment button — we don't support attachments inside the body */
            trix-toolbar .trix-button-group--file-tools { display: none; }
        </style>
    @endpush

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('email-templates.index') }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> All templates
        </a>
    </div>

    <div class="mb-6 flex items-start gap-3 max-w-3xl">
        <span class="w-10 h-10 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
            <i class="{{ $meta['icon'] }} text-lg"></i>
        </span>
        <div>
            <h2 class="font-semibold text-gray-900">{{ $meta['name'] }}</h2>
            <p class="text-sm text-gray-600">{{ $meta['description'] }}</p>
            <p class="text-xs text-gray-500 mt-1">
                Variables like <code class="font-mono px-1 py-0.5 bg-gray-100 rounded">@{{quote_number}}</code>
                resolve at send time. Your company logo is added automatically at the top.
            </p>
        </div>
    </div>

    @php
        // Build the literal Blade-token sequences once on the server so we
        // never have to fight Blade's compiler inside the Alpine x-data JS.
        // (Writing '{{' directly as a string here would be parsed as the
        // start of a Blade echo expression and break the template.)
        $vOpen  = chr(123) . chr(123);   // {{
        $vClose = chr(125) . chr(125);   // }}
    @endphp

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6"
        x-data="{
            subject: @js($template->subject),
            body: @js($template->body),
            sample: @js($sample),
            placeholder(key) {
                return '{{ $vOpen }}' + key + '{{ $vClose }}';
            },
            renderedSubject() {
                let out = this.subject;
                for (const key in this.sample) {
                    out = out.split(this.placeholder(key)).join(this.sample[key]);
                }
                return out;
            },
            renderedBody() {
                let out = this.body;
                for (const key in this.sample) {
                    out = out.split(this.placeholder(key)).join(this.sample[key]);
                }
                return out;
            },
            insertVariable(token) {
                const el = document.querySelector('trix-editor');
                if (! el || ! el.editor) return;
                el.editor.insertString('{{ $vOpen }}' + token + '{{ $vClose }}');
                this.body = el.value || el.innerHTML;
            }
        }">

        {{-- LEFT: editor --}}
        <form method="POST" action="{{ route('email-templates.update', $type) }}"
            class="xl:col-span-2 space-y-4" id="template-form">
            @csrf
            @method('PATCH')

            {{-- Subject --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                <input type="text" name="subject" x-model="subject"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800"
                    required maxlength="300" />
                <x-input-error :messages="$errors->get('subject')" class="mt-2" />
            </div>

            {{-- Body --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <label class="block text-sm font-medium text-gray-700 mb-2">Body</label>
                <input id="template-body-input" type="hidden" name="body" x-model="body" />
                <trix-editor input="template-body-input"
                    @trix-change="body = $event.target.value"></trix-editor>
                <x-input-error :messages="$errors->get('body')" class="mt-2" />
                <p class="text-xs text-gray-500 mt-2">
                    Use the variable chips on the right to insert dynamic data like the client name or quote number.
                </p>
            </div>

            {{-- Action bar --}}
            <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                <button type="button"
                    onclick="if (confirm('Reset this template to the factory default? Your edits will be lost.')) document.getElementById('reset-form').submit();"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-100">
                    <i class="ri-refresh-line"></i> Reset to default
                </button>

                <div class="flex items-center gap-2">
                    <a href="{{ route('email-templates.index') }}"
                        class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                        Cancel
                    </a>
                    <button type="submit"
                        class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2 rounded-lg text-sm transition">
                        <i class="ri-save-line"></i> Save changes
                    </button>
                </div>
            </div>
        </form>

        {{-- RIGHT: variable picker + live preview --}}
        <div class="xl:col-span-1 space-y-4">
            {{-- Variable picker --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <h3 class="font-semibold text-gray-900 text-sm mb-1">Variables</h3>
                <p class="text-xs text-gray-500 mb-3">Click to insert into the body. Edit the Subject field directly to add them there.</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($variables as $token => $label)
                        <button type="button" @click="insertVariable('{{ $token }}')"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-primary-50 text-primary-800 text-xs font-mono hover:bg-primary-100"
                            title="{{ $label }}">
                            <i class="ri-add-line text-[10px]"></i>
                            {{ $vOpen . $token . $vClose }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Live preview --}}
            @php
                $previewCompanyName = auth()->user()->company?->name ?? config('app.name', 'Nautiqs');
            @endphp
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden sticky top-20">
                <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 text-sm">Live preview</h3>
                    <span class="text-xs text-gray-500">with sample data</span>
                </div>
                <div class="p-4">
                    <div class="text-xs text-gray-400 mb-1">SUBJECT</div>
                    <div class="text-sm font-semibold text-gray-900 mb-4 break-words" x-text="renderedSubject()"></div>
                    <div class="text-xs text-gray-400 mb-1">BODY</div>

                    {{-- Mirrors EmailTemplateService::wrapWithLogo so the preview
                         matches what the recipient actually sees. --}}
                    <div class="border-t border-gray-100 pt-3">
                        <div class="flex items-center gap-3 pb-3 mb-4 border-b-[3px] border-primary-800">
                            <img src="{{ asset('nautiqs_logo.png') }}" alt="{{ $previewCompanyName }}"
                                class="w-12 h-12 rounded-lg object-contain shrink-0" />
                            <span class="font-bold text-primary-800 text-base">{{ $previewCompanyName }}</span>
                        </div>
                        <div class="prose prose-sm max-w-none text-gray-800" x-html="renderedBody()"></div>
                        <div class="mt-6 pt-3 border-t border-gray-200 text-xs text-gray-500">
                            Sent from {{ $previewCompanyName }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <form id="reset-form" method="POST" action="{{ route('email-templates.reset', $type) }}" class="hidden">
        @csrf
    </form>
</x-app-layout>
