@php
    $s  = $parsed['summary'];
    $c  = $s['create'];
    $u  = $s['update'];
    $totalCreate = $c['brands'] + $c['models'] + $c['versions'] + $c['options'];
    $totalUpdate = $u['models'] + $u['versions'] + $u['options'];
    // A file of nothing but errors should not offer a confirm button.
    $canApply = $totalCreate + $totalUpdate > 0;

    $fieldNames = [
        'code' => 'CODE MODELE', 'complement' => 'COMPLEMENT', 'year' => 'ANNEE',
        'type' => 'TYPE', 'propulsion' => 'PROPULSION', 'length_total' => 'LONGUEUR',
        'beam' => 'LARGEUR', 'draft_max' => "TIRANT D'EAU", 'weight' => 'POIDS',
        'supplier' => 'FOURNISSEUR', 'base_price' => 'PRIX HT', 'cost' => 'COUT HT',
        'currency' => 'DEVISE', 'included_equipment' => 'EQUIPEMENTS', 'is_active' => 'ACTIF',
        'price' => 'PV HT', 'category' => 'FAMILLE', 'description' => 'DESCRIPTION',
        'vat_rate' => 'TVA', 'position' => 'ORDRE',
    ];
    $show = fn ($v) => $v === null || $v === '' ? '—' : (is_bool($v) ? ($v ? 'oui' : 'non') : (string) $v);
@endphp

<x-app-layout :title="__('Import preview')" :header="__('Import preview')">

    <div class="max-w-5xl">
        <a href="{{ route('catalogue.transfer.form') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 mb-4">
            <i class="ri-arrow-left-line"></i> {{ __('Choose a different file') }}
        </a>

        <p class="text-sm text-gray-500 mb-4">
            {{ __('From :file. Nothing has been saved yet.', ['file' => $filename]) }}
        </p>

        {{-- What it will do, before how it will do it. --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ __('Will create') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalCreate }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    @if ($c['brands']) {{ $c['brands'] }} {{ __('brands') }} · @endif
                    {{ $c['models'] }} {{ __('boats') }} · {{ $c['versions'] }} {{ __('versions') }} · {{ $c['options'] }} {{ __('options') }}
                </p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ __('Will update') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $totalUpdate }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    {{ $u['models'] }} {{ __('boats') }} · {{ $u['versions'] }} {{ __('versions') }} · {{ $u['options'] }} {{ __('options') }}
                </p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-200 p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ __('Unchanged') }}</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $u['unchanged'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Already matched the file') }}</p>
            </div>
            <div class="bg-white rounded-2xl border {{ $s['errors'] ? 'border-red-200' : 'border-gray-200' }} p-5">
                <p class="text-xs uppercase tracking-wide text-gray-500 font-semibold">{{ __('Rows skipped') }}</p>
                <p class="text-2xl font-bold {{ $s['errors'] ? 'text-red-700' : 'text-gray-900' }} mt-1">{{ $s['errors'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('The rest still imports') }}</p>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 mb-6">
            <i class="ri-shield-check-line text-gray-500"></i>
            {{ __('Nothing is deleted: anything not in this file keeps its current values. Quotes already written are snapshots and never change.') }}
        </div>

        @if ($parsed['errors'])
            <div class="bg-white rounded-2xl border border-red-200 overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-red-100 bg-red-50">
                    <h3 class="font-semibold text-red-800">{{ __('Rows that will be skipped') }}</h3>
                    <p class="text-xs text-red-700">{{ __('Everything else in the file still imports.') }}</p>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach (array_slice($parsed['errors'], 0, 50) as $e)
                        <li class="px-5 py-2.5 flex flex-wrap items-baseline gap-x-2">
                            <span class="font-mono text-xs text-gray-500 shrink-0">
                                {{ $e['sheet'] ?: '—' }}@if ($e['row']) · {{ __('row') }} {{ $e['row'] }}@endif
                            </span>
                            <span class="text-gray-800">{{ $e['message'] }}</span>
                        </li>
                    @endforeach
                    @if (count($parsed['errors']) > 50)
                        <li class="px-5 py-2.5 text-xs text-gray-500">
                            {{ __('…and :n more.', ['n' => count($parsed['errors']) - 50]) }}
                        </li>
                    @endif
                </ul>
            </div>
        @endif

        @if ($parsed['warnings'])
            <div class="bg-white rounded-2xl border border-amber-200 overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-amber-100 bg-amber-50">
                    <h3 class="font-semibold text-amber-800">{{ __('Worth a look') }}</h3>
                </div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach (array_slice($parsed['warnings'], 0, 20) as $w)
                        <li class="px-5 py-2.5 flex flex-wrap items-baseline gap-x-2">
                            <span class="font-mono text-xs text-gray-500 shrink-0">{{ __('row') }} {{ $w['row'] }}</span>
                            <span class="text-gray-800">{{ $w['message'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($parsed['plan'])
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-6">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('Boat by boat') }}</h3>
                    <p class="text-xs text-gray-500">{{ __('Every value that would change, with its current value beside it.') }}</p>
                </div>

                <div class="divide-y divide-gray-100">
                    @foreach (array_slice($parsed['plan'], 0, 100) as $entry)
                        @php
                            $touched = ($entry['model_action'] !== 'unchanged')
                                || collect($entry['versions'])->contains(fn ($v) => $v['action'] !== 'unchanged')
                                || collect($entry['options'])->contains(fn ($o) => $o['action'] !== 'unchanged');
                        @endphp
                        <div class="px-5 py-4" x-data="{ open: {{ $touched ? 'true' : 'false' }} }">
                            <button type="button" x-on:click="open = ! open"
                                class="w-full flex flex-wrap items-center gap-2 text-left">
                                <span class="font-medium text-gray-900">{{ $entry['brand'] }} {{ $entry['name'] }}</span>

                                @if ($entry['model_action'] === 'create')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-semibold">{{ __('new boat') }}</span>
                                @elseif ($entry['model_action'] === 'update')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 font-semibold">{{ __('updated') }}</span>
                                @endif
                                @if (($entry['brand_action'] ?? '') === 'create')
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-semibold">{{ __('new brand') }}</span>
                                @endif

                                @php
                                    $vNew = collect($entry['versions'])->where('action', 'create')->count();
                                    $vUpd = collect($entry['versions'])->where('action', 'update')->count();
                                    $oNew = collect($entry['options'])->where('action', 'create')->count();
                                    $oUpd = collect($entry['options'])->where('action', 'update')->count();
                                @endphp
                                <span class="text-xs text-gray-500 ml-auto">
                                    @if ($vNew || $vUpd)
                                        {{ __('versions') }}: <span class="text-emerald-700">+{{ $vNew }}</span> / <span class="text-blue-700">~{{ $vUpd }}</span>
                                    @endif
                                    @if ($oNew || $oUpd)
                                        · {{ __('options') }}: <span class="text-emerald-700">+{{ $oNew }}</span> / <span class="text-blue-700">~{{ $oUpd }}</span>
                                    @endif
                                    @if (! $touched) {{ __('no change') }} @endif
                                </span>
                                <i class="ri-arrow-down-s-line text-gray-400" x-bind:class="open && 'rotate-180'"></i>
                            </button>

                            <div x-show="open" x-cloak class="mt-3 space-y-3 text-sm">
                                @if ($entry['model_changes'])
                                    <div class="rounded-lg bg-gray-50 px-3 py-2">
                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('Boat') }}</p>
                                        @foreach ($entry['model_changes'] as $f => $pair)
                                            <div class="flex flex-wrap items-baseline gap-2 text-xs">
                                                <span class="font-mono text-gray-500 w-32 shrink-0">{{ $fieldNames[$f] ?? $f }}</span>
                                                <span class="text-gray-400 line-through">{{ $show($pair[0]) }}</span>
                                                <i class="ri-arrow-right-line text-gray-400"></i>
                                                <span class="text-gray-900 font-medium">{{ $show($pair[1]) }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @foreach ([__('Versions') => $entry['versions'], __('Options') => $entry['options']] as $groupLabel => $items)
                                    @php $items = collect($items)->where('action', '!=', 'unchanged'); @endphp
                                    @if ($items->isNotEmpty())
                                        <div class="rounded-lg bg-gray-50 px-3 py-2">
                                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ $groupLabel }}</p>
                                            @foreach ($items as $it)
                                                <div class="py-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <span class="text-xs font-medium text-gray-900">{{ $it['label'] ?? $it['name'] }}</span>
                                                        @if ($it['action'] === 'create')
                                                            <span class="text-[11px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">{{ __('new') }}</span>
                                                        @endif
                                                    </div>
                                                    @foreach ($it['changes'] as $f => $pair)
                                                        <div class="flex flex-wrap items-baseline gap-2 text-xs pl-3">
                                                            <span class="font-mono text-gray-500 w-32 shrink-0">{{ $fieldNames[$f] ?? $f }}</span>
                                                            <span class="text-gray-400 line-through break-all">{{ $show($pair[0]) }}</span>
                                                            <i class="ri-arrow-right-line text-gray-400"></i>
                                                            <span class="text-gray-900 font-medium break-all">{{ $show($pair[1]) }}</span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                @if (count($parsed['plan']) > 100)
                    <div class="px-5 py-3 text-xs text-gray-500 border-t border-gray-100">
                        {{ __('Showing the first 100 boats of :n. All of them will be imported.', ['n' => count($parsed['plan'])]) }}
                    </div>
                @endif
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-3 pb-6">
            <form method="POST" action="{{ route('catalogue.transfer.confirm') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}" />
                <button type="submit" @disabled(! $canApply)
                    class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 disabled:bg-gray-300 text-white font-semibold px-5 py-2 rounded-lg text-sm">
                    <i class="ri-check-line"></i> {{ __('Apply this import') }}
                </button>
            </form>
            <a href="{{ route('catalogue.models') }}"
                class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm">
                {{ __('Cancel') }}
            </a>
            <span class="text-xs text-gray-500">{{ __('This preview is held for :n minutes.', ['n' => $expires]) }}</span>
        </div>
    </div>

</x-app-layout>
