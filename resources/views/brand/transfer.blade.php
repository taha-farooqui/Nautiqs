<x-app-layout :title="__('Import')" :header="__('Import')">
    @include('brand.partials.flash')
    @php
        $back = $account->isEngine() ? route('brand.engines.index') : route('brand.catalogue.index');
        $levels = $account->levelsResolved();
    @endphp

    <div class="mb-4 text-sm"><a href="{{ $back }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Catalogue') }}</a></div>

    @if (session('import_errors'))
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 px-4 py-3 text-sm max-w-3xl">
            <div class="font-semibold mb-1">{{ __('Some rows were skipped') }}</div>
            <ul class="list-disc list-inside space-y-0.5">@foreach (session('import_errors') as $e) <li>{{ $e }}</li> @endforeach</ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 max-w-5xl">
        <section class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6">
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Import a price list') }}</h3>
            <p class="text-sm text-gray-500 mb-4">{{ __('Rows are matched by name: existing items are updated, new ones are added. Nothing is deleted — archive items in the catalogue instead.') }}</p>
            <form method="POST" action="{{ route('brand.transfer.import') }}" enctype="multipart/form-data" class="space-y-4">@csrf
                <input type="file" name="file" accept=".xlsx" required class="block w-full text-sm text-gray-700 file:mr-3 file:px-4 file:py-2 file:rounded-lg file:border-0 file:bg-gray-100 file:font-medium hover:file:bg-gray-200">
                <button class="px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">{{ __('Import and publish') }}</button>
            </form>
        </section>

        <section class="bg-white rounded-2xl border border-gray-200 p-6 text-sm space-y-3 self-start">
            <h3 class="font-semibold text-gray-900">{{ __('File format') }}</h3>
            @if ($account->isEngine())
                <p class="text-gray-600">{{ __('Two sheets: MOTEURS (one row per engine) and KITS (kits and propellers; TYPE = KIT or HELICE, MOTEURS = the engine codes it fits, separated by ;).') }}</p>
            @else
                <p class="text-gray-600">{{ __('Two sheets: BATEAUX (one row per version, equipment separated by ;) and OPTIONS (one row per option).') }}</p>
            @endif
            <p class="text-gray-600">{{ __('MARQUE must be one of your brands.') }}</p>
            <ul class="text-gray-600 space-y-0.5">
                @foreach ($levels as $n => $l) <li><span class="font-mono text-xs">NIVEAU {{ $n }} HT</span> = {{ $l['name'] }}</li> @endforeach
            </ul>
            <p class="text-gray-500">{{ __('Leave a level empty to use its %.') }}</p>
            <div class="flex flex-wrap gap-2 pt-1">
                <a href="{{ route('brand.transfer.template') }}" class="inline-flex items-center gap-1 px-3 py-1.5 font-medium border border-gray-300 rounded-lg hover:bg-gray-50"><i class="ri-file-excel-2-line"></i> {{ __('Template') }}</a>
                <a href="{{ route('brand.transfer.export') }}" class="inline-flex items-center gap-1 px-3 py-1.5 font-medium border border-gray-300 rounded-lg hover:bg-gray-50"><i class="ri-download-2-line"></i> {{ __('Export current') }}</a>
            </div>
        </section>
    </div>
</x-app-layout>
