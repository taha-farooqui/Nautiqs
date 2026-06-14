<x-app-layout :title="__('Engines')" :header="__('Engines')">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
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

    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3"
        x-data="{ importOpen: false }">
        <form method="GET" action="{{ route('engines.index') }}" class="flex-1 max-w-md">
            <div class="relative">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search by brand, code, or description…') }}"
                    class="w-full pl-9 pr-3 py-2 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </form>
        <div class="flex items-center gap-2">
            <button type="button" @click="importOpen = true"
                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg">
                <i class="ri-upload-2-line"></i> {{ __('Import') }}
            </button>
            <a href="{{ route('engines.create') }}"
                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-add-line"></i> {{ __('Add engine') }}
            </a>
        </div>

        {{-- Import modal --}}
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
                        <h3 class="font-semibold text-gray-900">{{ __('Import engines') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('Upload a CSV or XLSX file with your engine list.') }}</p>
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
                            {{ __('Download the sample CSV, fill in your engines, then upload it back here.') }}
                        </p>
                        <a href="{{ route('engines.template') }}"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-white border border-primary-200 hover:bg-primary-50 text-primary-800 rounded-lg">
                            <i class="ri-download-2-line"></i> {{ __('Download template (CSV)') }}
                        </a>
                    </div>

                    <form method="POST" action="{{ route('engines.import') }}"
                        enctype="multipart/form-data" id="engine-import-form"
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
                                <li><span class="font-mono">Brand</span> — {{ __('required') }}</li>
                                <li><span class="font-mono">Model</span> — {{ __('required, engine code/SKU') }}</li>
                                <li><span class="font-mono">PV HT</span> — {{ __('required, selling price HT') }}</li>
                                <li><span class="font-mono">PA HT</span> — {{ __('optional, purchase cost') }}</li>
                                <li><span class="font-mono">TVA</span> — {{ __('optional, defaults to 20') }}</li>
                            </ul>
                            <p class="text-gray-500 mt-2">{{ __('Rows matching an existing Brand + Model will be updated; the rest will be created.') }}</p>
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

    @if ($engines->isEmpty())
        <x-app.empty-state
            icon="ri-settings-3-line"
            :title="__('No engines yet')"
            :message="__('Add the engine SKUs you carry so they show up in the quote builder.')"
            :ctaLabel="__('Add the first engine')"
            ctaHref="{{ route('engines.create') }}"
            size="lg" />
    @else
        <div x-data="{ selected: [], allIds: {{ Js::from($engines->pluck('id')->values()) }} }">
            {{-- Bulk actions toolbar — appears once one or more rows are ticked. --}}
            <div x-show="selected.length > 0" x-cloak
                class="mb-3 flex items-center justify-between gap-3 rounded-lg border border-primary-200 bg-primary-50/60 px-4 py-2.5">
                <span class="text-sm font-medium text-primary-900">
                    <span x-text="selected.length"></span> {{ __('selected') }}
                </span>
                <form method="POST" action="{{ route('engines.bulk-destroy') }}"
                    data-confirm="{{ __('Delete the selected engines?') }}" data-confirm-danger="1">
                    @csrf @method('DELETE')
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="ids[]" :value="id" />
                    </template>
                    <button type="submit"
                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-semibold bg-red-600 hover:bg-red-700 text-white rounded-lg">
                        <i class="ri-delete-bin-line"></i> {{ __('Delete selected') }}
                    </button>
                </form>
            </div>

            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 w-10">
                                <input type="checkbox" title="{{ __('Select all') }}"
                                    @change="selected = $event.target.checked ? [...allIds] : []"
                                    :checked="allIds.length > 0 && selected.length === allIds.length"
                                    class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                            </th>
                            <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Code') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('HP') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Public HT') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('VAT') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('TTC') }}</th>
                            <th class="px-5 py-3 font-semibold"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($engines as $engine)
                            <tr class="hover:bg-gray-50" :class="selected.includes('{{ $engine->id }}') ? 'bg-primary-50/40' : ''">
                                <td class="px-5 py-3">
                                    <input type="checkbox" x-model="selected" value="{{ $engine->id }}"
                                        class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                                </td>
                                <td class="px-5 py-3 font-medium text-gray-900">{{ $engine->brand }}</td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-700">{{ $engine->code }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $engine->horsepower ? number_format($engine->horsepower, 0) . ' ' . __('HP') : '—' }}</td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-900">€{{ number_format($engine->price, 2, ',', ' ') }}</td>
                                <td class="px-5 py-3 text-right text-gray-700">{{ number_format($engine->vat_rate, 2) }}%</td>
                                <td class="px-5 py-3 text-right font-semibold text-gray-900">€{{ number_format($engine->ttc, 2, ',', ' ') }}</td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('engines.edit', $engine->id) }}"
                                            class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                        <form method="POST" action="{{ route('engines.destroy', $engine->id) }}"
                                            data-confirm="{{ __('Delete this engine?') }}"
                                            data-confirm-danger="1"
                                            class="inline">
                                            @csrf @method('DELETE')
                                            <button class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Delete') }}">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $engines->links() }}</div>
        </div>
    @endif
</x-app-layout>
