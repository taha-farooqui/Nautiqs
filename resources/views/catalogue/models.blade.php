<x-app-layout :title="__('Catalogue')" :header="__('Catalogue')">

    {{-- Toast stack --}}
    @if (session('status') || $errors->any())
        <div class="fixed top-20 right-6 z-50 space-y-2 w-full max-w-sm">
            @if (session('status'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                    x-transition.opacity
                    class="rounded-lg border border-emerald-200 bg-white shadow-lg px-4 py-3 flex items-start gap-3">
                    <i class="ri-checkbox-circle-fill text-emerald-600 text-xl shrink-0"></i>
                    <p class="flex-1 text-sm text-gray-800">{{ session('status') }}</p>
                    <button type="button" @click="show = false" class="text-gray-400 hover:text-gray-700">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if ($errors->any())
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 7000)"
                    x-transition.opacity
                    class="rounded-lg border border-red-200 bg-white shadow-lg px-4 py-3 flex items-start gap-3">
                    <i class="ri-error-warning-fill text-red-600 text-xl shrink-0"></i>
                    <p class="flex-1 text-sm text-gray-800">{{ $errors->first() }}</p>
                    <button type="button" @click="show = false" class="text-gray-400 hover:text-gray-700">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
        </div>
    @endif

    @php $importResult = session('import_result'); @endphp
    @if ($importResult && ! empty($importResult['errors']))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            <p class="font-medium mb-2">
                {{ __(':count row(s) could not be imported', ['count' => count($importResult['errors'])]) }}:
            </p>
            <ul class="list-disc list-inside space-y-0.5 text-xs">
                @foreach (array_slice($importResult['errors'], 0, 20) as $err)
                    <li>{{ __('Row') }} {{ $err['row'] }} — {{ $err['message'] }}</li>
                @endforeach
                @if (count($importResult['errors']) > 20)
                    <li class="italic">{{ __('…and :count more', ['count' => count($importResult['errors']) - 20]) }}</li>
                @endif
            </ul>
        </div>
    @endif

    {{-- Top bar: filter + add boat --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3"
        x-data="{ importOpen: false }">
        <form method="GET" action="{{ route('catalogue.models') }}" class="flex items-center gap-2">
            <div class="relative">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ request('q', '') }}"
                    placeholder="{{ __('Search by brand, model, or variant…') }}"
                    class="pl-9 pr-3 py-2 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[280px]" />
            </div>
            <select name="brand" onchange="this.form.submit()"
                class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800">
                <option value="">{{ __('All brands') }}</option>
                @foreach ($brands as $b)
                    <option value="{{ $b->_id }}" @selected($brandFilter === (string) $b->_id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </form>

        <div class="flex items-center gap-2">
            <button type="button" @click="importOpen = true"
                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg">
                <i class="ri-upload-2-line"></i> {{ __('Import options') }}
            </button>
            <a href="{{ route('catalogue.models.create') }}"
                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-add-line"></i> {{ __('Add boat') }}
            </a>
        </div>

        {{-- Import options modal --}}
        <div x-show="importOpen" x-cloak x-transition.opacity
            @keydown.escape.window="importOpen = false"
            class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
            <div @click.outside="importOpen = false"
                class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                        <i class="ri-upload-2-line"></i>
                    </span>
                    <div class="flex-1 min-w-0">
                        <h3 class="font-semibold text-gray-900">{{ __('Import options') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('Upload a CSV or XLSX file with your options list.') }}</p>
                    </div>
                    <button type="button" @click="importOpen = false"
                        class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg">
                        <i class="ri-close-line text-xl"></i>
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    <div class="rounded-lg border border-primary-200 bg-primary-50/40 px-4 py-3">
                        <p class="text-sm font-semibold text-primary-900 mb-1">
                            <i class="ri-information-line"></i> {{ __('Need a template?') }}
                        </p>
                        <p class="text-xs text-gray-700 mb-2">
                            {{ __('Download the sample file, fill in your options, then upload it back here.') }}
                        </p>
                        <a href="{{ route('catalogue.options.template') }}"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-white border border-primary-200 hover:bg-primary-50 text-primary-800 rounded-lg">
                            <i class="ri-download-2-line"></i> {{ __('Download template (XLSX)') }}
                        </a>
                    </div>

                    <form method="POST" action="{{ route('catalogue.options.import-bulk') }}"
                        enctype="multipart/form-data"
                        class="space-y-3"
                        x-data="{ filename: '', submitting: false }"
                        @submit="submitting = true">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('File') }} <span class="text-red-500">*</span></label>
                            <input type="file" name="file" required
                                accept=".csv,.xlsx,.xlsm,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                                @change="filename = $event.target.files[0]?.name || ''"
                                class="w-full text-sm file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-primary-50 file:text-primary-800 file:font-semibold hover:file:bg-primary-100" />
                            <p class="text-xs text-gray-500 mt-1">{{ __('CSV or XLSX. Max 10 MB. Up to 5000 rows.') }}</p>
                            <x-input-error :messages="$errors->get('file')" class="mt-1" />
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700">
                            <p class="font-semibold mb-1">{{ __('Expected columns') }}</p>
                            <ul class="space-y-0.5">
                                <li><span class="font-mono">CODE</span> — {{ __('required, the option SKU') }}</li>
                                <li><span class="font-mono">CODE MODELE</span> — {{ __("required, matches the boat's internal code") }}</li>
                                <li><span class="font-mono">MARQUE</span> — {{ __('optional') }}</li>
                                <li><span class="font-mono">DESIGNATION FR</span> — {{ __('required, French label') }}</li>
                                <li><span class="font-mono">DESIGNATION GB</span> — {{ __('optional, English label') }}</li>
                                <li><span class="font-mono">FAMILLE</span> — {{ __('optional, category') }}</li>
                                <li><span class="font-mono">PA HT</span> — {{ __('optional, purchase cost') }}</li>
                                <li><span class="font-mono">PV HT</span> — {{ __('required, public HT price') }}</li>
                                <li><span class="font-mono">TVA</span> — {{ __('optional, VAT (20 or 0.2)') }}</li>
                                <li><span class="font-mono">OPTION CHANTIER</span> — {{ __('optional, 0/1') }}</li>
                            </ul>
                            <p class="text-gray-500 mt-2">
                                {{ __('Boats must already exist in your catalogue with the matching internal code. Rows matching an existing boat + CODE will be updated; the rest will be created.') }}
                            </p>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button type="button" @click="importOpen = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                                {{ __('Cancel') }}
                            </button>
                            <button type="submit" :disabled="submitting"
                                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg disabled:opacity-50">
                                <span x-show="!submitting"><i class="ri-upload-2-line"></i> {{ __('Import') }}</span>
                                <span x-show="submitting" x-cloak><i class="ri-loader-4-line animate-spin"></i> {{ __('Importing…') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @if ($rows->isEmpty())
        <x-app.empty-state
            icon="ri-sailboat-line"
            :title="__('Your catalogue is empty')"
            :message="__('Add your first boat — pick the brand, model, then add versions and options.')"
            :ctaLabel="__('Add a boat')"
            ctaHref="{{ route('catalogue.models.create') }}"
            size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Model') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('From TTC') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Updated') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $r)
                        @php
                            $v = $r['variant']; $m = $r['model']; $b = $r['brand'];
                            $ttc = $v->base_price * (1 + ($v->vat_rate ?? 20) / 100);
                        @endphp
                        <tr class="hover:bg-gray-50 cursor-pointer"
                            data-href="{{ route('catalogue.models.edit', $m?->_id) }}"
                            onclick="if (!event.target.closest('a, button, form, input')) window.location = this.dataset.href">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $b?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $m?->name ?? '—' }}{{ $m?->complement ? ' ' . $m->complement : '' }}</p>
                                <p class="text-xs text-gray-500">{{ $v->name }}</p>
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">€{{ number_format($ttc, 0, ',', ' ') }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500">{{ $m?->updated_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('catalogue.models.edit', $m?->_id) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                    <i class="ri-pencil-line"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
