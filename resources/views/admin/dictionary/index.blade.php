<x-admin-layout :title="__('Dictionary')" :header="__('Translation dictionary')">

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

    {{-- Stats strip --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Keys in dictionary') }}</div>
            <div class="text-xl font-bold text-gray-900 mt-0.5">{{ number_format($stats['total_keys'], 0, ',', ' ') }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Customised') }}</div>
            <div class="text-xl font-bold text-primary-800 mt-0.5">{{ $stats['customised'] }}</div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 px-4 py-3">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Locale') }}</div>
            <div class="text-xl font-bold text-gray-900 mt-0.5 uppercase">{{ $locale }}</div>
        </div>
        <a href="{{ route('admin.dictionary.export', ['locale' => $locale]) }}"
            class="bg-white rounded-xl border border-gray-200 px-4 py-3 hover:border-primary-800 transition">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider">{{ __('Export') }}</div>
            <div class="text-xs font-medium text-primary-800 mt-1">
                <i class="ri-download-2-line"></i> {{ __('Download CSV') }}
            </div>
        </a>
    </div>

    {{-- Filter bar --}}
    <form method="GET" action="{{ route('admin.dictionary.index') }}"
        class="bg-white rounded-2xl border border-gray-200 p-3 mb-4 flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
        <div class="relative flex-1">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="search" name="q" value="{{ $q }}" autofocus
                placeholder="{{ __('Search any English word or its translation…') }}"
                class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800 focus:bg-white" />
        </div>
        <select name="locale" onchange="this.form.submit()"
            class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[110px]">
            <option value="fr" @selected($locale === 'fr')>{{ __('French') }}</option>
            <option value="en" @selected($locale === 'en')>{{ __('English') }}</option>
        </select>
        <select name="filter" onchange="this.form.submit()"
            class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[140px]">
            <option value="all"        @selected($filter === 'all')>{{ __('All keys') }}</option>
            <option value="customised" @selected($filter === 'customised')>{{ __('Customised only') }}</option>
            <option value="defaults"   @selected($filter === 'defaults')>{{ __('Defaults only') }}</option>
        </select>
        <button type="submit"
            class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-filter-line"></i> {{ __('Filter') }}
        </button>
    </form>

    {{-- Results --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-translate-2 text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">
                    @if ($q !== '')
                        {{ __('No translations match your search.') }}
                    @else
                        {{ __('No translations found.') }}
                    @endif
                </p>
                @if ($q !== '')
                    <p class="text-xs text-gray-500 mt-1">
                        {{ __('Try a different word or remove the filter.') }}
                    </p>
                @endif
            </div>
        @else
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between text-xs text-gray-500">
                <span>{{ __(':total result(s)', ['total' => number_format($total, 0, ',', ' ')]) }}</span>
                <span>{{ __('Showing :from – :to', ['from' => ($page - 1) * 50 + 1, 'to' => min($page * 50, $total)]) }}</span>
            </div>

            <ul class="divide-y divide-gray-100">
                @foreach ($rows as $row)
                    <li x-data="{ editing: false }" class="px-5 py-3 hover:bg-gray-50/50">
                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                            {{-- Key (English source) --}}
                            <div class="lg:col-span-5 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">{{ __('Key') }}</span>
                                    @if ($row['customised'])
                                        <span class="text-[10px] font-bold text-primary-800 bg-primary-50 px-1.5 py-0.5 rounded">
                                            <i class="ri-pencil-line"></i> {{ __('Customised') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="text-sm text-gray-900 break-words mt-0.5">{{ $row['key'] }}</div>
                                @if ($row['customised'] && $row['default'] !== $row['key'])
                                    <div class="text-[11px] text-gray-400 mt-1">
                                        {{ __('Default:') }} <span class="italic">{{ $row['default'] }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Current value / inline edit --}}
                            <div class="lg:col-span-6 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">
                                        {{ strtoupper($locale) }} {{ __('value') }}
                                    </span>
                                </div>

                                <div x-show="!editing">
                                    <button type="button" @click="editing = true; $nextTick(() => $refs.input.focus())"
                                        class="w-full text-left text-sm text-gray-900 mt-0.5 px-2 py-1 -ml-2 rounded hover:bg-white hover:ring-1 hover:ring-primary-200 transition">
                                        {{ $row['current'] }}
                                    </button>
                                </div>

                                <form x-show="editing" x-cloak method="POST" action="{{ route('admin.dictionary.update') }}"
                                    @keydown.escape="editing = false"
                                    class="mt-1 flex flex-col sm:flex-row gap-2">
                                    @csrf
                                    <input type="hidden" name="key"    value="{{ $row['key'] }}" />
                                    <input type="hidden" name="locale" value="{{ $locale }}" />
                                    <textarea name="value" x-ref="input" rows="1"
                                        @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                        class="flex-1 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 resize-none">{{ $row['current'] }}</textarea>
                                    <div class="flex items-center gap-1">
                                        <button type="submit"
                                            class="px-3 py-2 text-xs font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                            <i class="ri-save-line"></i> {{ __('Save') }}
                                        </button>
                                        <button type="button" @click="editing = false"
                                            class="px-3 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                                            {{ __('Cancel') }}
                                        </button>
                                    </div>
                                </form>
                            </div>

                            {{-- Reset action --}}
                            <div class="lg:col-span-1 flex justify-end">
                                @if ($row['customised'])
                                    <form method="POST" action="{{ route('admin.dictionary.reset') }}"
                                        data-confirm="{{ __('Reset to default?') }}"
                                        data-confirm-text="{{ __('The customised translation will be removed and the original file value used again.') }}"
                                        data-confirm-danger="0">
                                        @csrf
                                        <input type="hidden" name="key"    value="{{ $row['key'] }}" />
                                        <input type="hidden" name="locale" value="{{ $locale }}" />
                                        <button type="submit"
                                            class="inline-flex items-center justify-center w-8 h-8 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg"
                                            title="{{ __('Reset to default') }}">
                                            <i class="ri-restart-line"></i>
                                        </button>
                                    </form>
                                @else
                                    <span class="inline-block w-8 h-8"></span>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            {{-- Pagination --}}
            @php
                $totalPages = max(1, (int) ceil($total / 50));
            @endphp
            @if ($totalPages > 1)
                <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between text-xs">
                    <div class="text-gray-500">
                        {{ __('Page :page of :total', ['page' => $page, 'total' => $totalPages]) }}
                    </div>
                    <div class="flex items-center gap-1">
                        @if ($page > 1)
                            <a href="{{ route('admin.dictionary.index', array_merge(request()->query(), ['page' => $page - 1])) }}"
                                class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">
                                <i class="ri-arrow-left-line"></i> {{ __('Previous') }}
                            </a>
                        @endif
                        @if ($page < $totalPages)
                            <a href="{{ route('admin.dictionary.index', array_merge(request()->query(), ['page' => $page + 1])) }}"
                                class="px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50">
                                {{ __('Next') }} <i class="ri-arrow-right-line"></i>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>
</x-admin-layout>
