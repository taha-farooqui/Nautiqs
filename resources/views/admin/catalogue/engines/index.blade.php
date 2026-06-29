<x-admin-layout :title="__('Engines')" :header="__('Global engines')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
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

    {{-- Status tabs --}}
    <div class="flex items-center gap-1 mb-4 bg-white p-1 rounded-xl border border-gray-200 w-fit">
        @foreach ([
            'active'   => __('Active') . ' (' . $tabCounts['active'] . ')',
            'archived' => __('Archived') . ' (' . $tabCounts['archived'] . ')',
        ] as $tab => $label)
            <a href="{{ route('admin.engines.index', ['status' => $tab, 'q' => $q]) }}"
                class="px-3 py-1.5 text-sm font-medium rounded-lg transition
                    {{ $status === $tab ? 'bg-primary-800 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
        x-data="{ importOpen: false }">
        <form method="GET" action="{{ route('admin.engines.index') }}" class="flex-1 max-w-md">
            <input type="hidden" name="status" value="{{ $status }}" />
            <div class="relative">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search brand, code or description…') }}"
                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </form>
        @if ($status === 'active')
            <div class="flex items-center gap-2">
                <button type="button" @click="importOpen = true"
                    class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white hover:bg-gray-50 text-gray-800 border border-gray-300 rounded-lg">
                    <i class="ri-upload-2-line"></i> {{ __('Import') }}
                </button>
                <a href="{{ route('admin.engines.create') }}"
                    class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                    <i class="ri-add-line"></i> {{ __('Add engine') }}
                </a>
            </div>
        @endif

        {{-- Import modal — teleported to <body> so the overlay covers the
             full viewport regardless of any transformed ancestor. --}}
        <template x-teleport="body">
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
                        <h3 class="font-semibold text-gray-900">{{ __('Import global engines') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('Upload a CSV or XLSX file with your engines list.') }}</p>
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
                            {{ __('Download the sample file, fill in your engines, then upload it back here.') }}
                        </p>
                        <a href="{{ route('admin.engines.template') }}"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-white border border-primary-200 hover:bg-primary-50 text-primary-800 rounded-lg">
                            <i class="ri-download-2-line"></i> {{ __('Download template (CSV)') }}
                        </a>
                    </div>

                    <form method="POST" action="{{ route('admin.engines.import') }}"
                        enctype="multipart/form-data"
                        class="space-y-3"
                        x-data="{ filename: '', submitting: false, helpOpen: false }"
                        @submit="submitting = true">
                        @csrf
                        <div>
                            <div class="flex items-center gap-1.5 mb-1">
                                <label class="block text-sm font-medium text-gray-700">{{ __('File') }} <span class="text-red-500">*</span></label>
                                <div class="relative" @mouseleave="helpOpen = false">
                                    <button type="button"
                                        @mouseenter="helpOpen = true"
                                        @click="helpOpen = !helpOpen"
                                        class="w-4 h-4 inline-flex items-center justify-center rounded-full bg-gray-200 hover:bg-primary-100 text-gray-600 hover:text-primary-800 text-[10px] font-bold transition">
                                        ?
                                    </button>
                                    <div x-show="helpOpen" x-cloak x-transition.opacity
                                        class="absolute left-0 top-6 z-10 w-80 bg-gray-900 text-white rounded-lg shadow-xl p-3 text-xs">
                                        <p class="font-semibold mb-1.5">{{ __('Expected columns') }}</p>
                                        <ul class="space-y-0.5 text-white/90">
                                            <li><span class="font-mono text-primary-200">Brand</span> — {{ __('required') }}</li>
                                            <li><span class="font-mono text-primary-200">Model</span> — {{ __('required, engine code/SKU') }}</li>
                                            <li><span class="font-mono text-primary-200">PA HT</span> — {{ __('optional, purchase cost') }}</li>
                                            <li><span class="font-mono text-primary-200">PV HT</span> — {{ __('required, selling price HT') }}</li>
                                            <li><span class="font-mono text-primary-200">TVA</span> — {{ __('optional, defaults to 20') }}</li>
                                        </ul>
                                        <p class="text-white/70 mt-2 pt-2 border-t border-white/10">
                                            {{ __('Rows matching an existing Brand + Model will be updated; the rest will be created.') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <input type="file" name="file" required
                                accept=".csv,.xlsx,.xlsm,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
                                @change="filename = $event.target.files[0]?.name || ''"
                                class="w-full text-sm file:mr-3 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-primary-50 file:text-primary-800 file:font-semibold hover:file:bg-primary-100" />
                            <p class="text-xs text-gray-500 mt-1">{{ __('CSV or XLSX. Max 10 MB. Up to 5000 rows.') }}</p>
                            <x-input-error :messages="$errors->get('file')" class="mt-1" />
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
        </template>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($engines->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-settings-3-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No engines.') }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Code') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('HP') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Cost') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Price') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('VAT') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($engines as $e)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $e->brand }}</td>
                            <td class="px-5 py-3"><span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $e->code }}</span></td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $e->horsepower ? number_format($e->horsepower, 0) : '—' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $e->cost ? number_format($e->cost, 0, ',', ' ') . ' ' . ($e->currency === 'USD' ? '$' : '€') : '—' }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ number_format($e->price, 0, ',', ' ') }} {{ $e->currency === 'USD' ? '$' : '€' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $e->vat_rate ? $e->vat_rate . '%' : '—' }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('admin.engines.archive', $e->_id) }}" class="inline"
                                        data-confirm="{{ __('Restore :name?', ['name' => $e->brand . ' ' . $e->code]) }}">
                                        @csrf
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="{{ __('Restore') }}">
                                            <i class="ri-arrow-go-back-line"></i>
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.engines.edit', $e->_id) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.engines.archive', $e->_id) }}" class="inline"
                                        data-confirm="{{ __('Archive :name?', ['name' => $e->brand . ' ' . $e->code]) }}"
                                        data-confirm-danger="1">
                                        @csrf
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:bg-gray-100 rounded-lg" title="{{ __('Archive') }}">
                                            <i class="ri-archive-line"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-3 border-t border-gray-100">{{ $engines->links() }}</div>
        @endif
    </div>
</x-admin-layout>
