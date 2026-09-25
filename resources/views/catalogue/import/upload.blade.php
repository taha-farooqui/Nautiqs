<x-app-layout :title="__('Import the catalogue')" :header="__('Import the catalogue')">

    <div class="max-w-3xl">
        <a href="{{ route('catalogue.models') }}" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-gray-900 mb-4">
            <i class="ri-arrow-left-line"></i> {{ __('Back to the catalogue') }}
        </a>

        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('catalogue.transfer.preview') }}" enctype="multipart/form-data"
            class="bg-white rounded-2xl border border-gray-200 p-6">
            @csrf

            <h3 class="font-semibold text-gray-900">{{ __('Choose a file') }}</h3>
            <p class="text-sm text-gray-500 mt-1">
                {{ __('Excel (.xlsx) or CSV, up to 10 MB. Nothing is written until you have seen what the file would change.') }}
            </p>

            {{-- The picked file is shown back, because "I uploaded the wrong
                 price list" is the expensive mistake this screen prevents. --}}
            <div class="mt-4"
                x-data="{ name: '', size: 0,
                    pick(e) { const f = e.target.files[0]; this.name = f ? f.name : ''; this.size = f ? f.size : 0; },
                    human(b) { return b < 1048576 ? Math.round(b / 1024) + ' KB' : (b / 1048576).toFixed(1) + ' MB'; } }">
                <input type="file" name="file" required x-ref="picker" class="hidden"
                    accept=".xlsx,.xls,.csv,.txt" x-on:change="pick($event)" />

                <div x-on:click="$refs.picker.click()"
                    class="cursor-pointer rounded-lg border border-dashed border-gray-300 bg-gray-50 hover:border-primary-800 hover:bg-primary-50 px-4 py-6 text-center transition-colors">
                    <i class="ri-file-excel-2-line text-2xl text-gray-400 pointer-events-none"></i>
                    <p class="text-sm text-gray-600 mt-1 pointer-events-none">
                        <span class="font-medium text-primary-800">{{ __('Choose a file') }}</span>
                    </p>
                </div>

                <template x-if="name">
                    <div class="mt-2 flex items-center gap-2.5 rounded-lg border border-gray-200 bg-white px-3 py-2">
                        <i class="ri-file-excel-2-line text-gray-500"></i>
                        <span class="min-w-0 flex-1 text-xs font-medium text-gray-900 truncate" x-text="name"></span>
                        <span class="text-xs text-gray-400" x-text="human(size)"></span>
                    </div>
                </template>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="submit"
                    class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2 rounded-lg text-sm">
                    <i class="ri-eye-line"></i> {{ __('See what will change') }}
                </button>
                <a href="{{ route('catalogue.transfer.template') }}"
                    class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm">
                    <i class="ri-download-line"></i> {{ __('Download the template') }}
                </a>
                <a href="{{ route('catalogue.transfer.export') }}"
                    class="inline-flex items-center gap-2 border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm">
                    <i class="ri-upload-line"></i> {{ __('Export the current catalogue') }}
                </a>
            </div>
        </form>

        {{-- Short, and about consequences rather than syntax: the columns are
             self-describing in the file itself. --}}
        <div class="mt-6 bg-white rounded-2xl border border-gray-200 p-6 text-sm text-gray-700 space-y-4">
            <div>
                <h4 class="font-semibold text-gray-900 mb-1">{{ __('Two sheets') }}</h4>
                <p class="text-gray-600">
                    {{ __('BATEAUX: MARQUE, MODELE, VERSION, PRIX HT, COUT HT, DEVISE and EQUIPEMENTS INCLUS — one row per version, with the equipment in a single cell separated by a semicolon. OPTIONS: MARQUE, MODELE, FAMILLE, DESIGNATION, DESCRIPTION, PA HT and PV HT — one row per option.') }}
                </p>
            </div>
            <div>
                <h4 class="font-semibold text-gray-900 mb-1">{{ __('Nothing is ever deleted') }}</h4>
                <p class="text-gray-600">
                    {{ __('A boat, version or option that is not in the file is left exactly as it is. Importing a price list for one brand cannot touch the rest of your catalogue.') }}
                </p>
            </div>
            <div>
                <h4 class="font-semibold text-gray-900 mb-1">{{ __('Only the columns you include are changed') }}</h4>
                <p class="text-gray-600">
                    {{ __('For a yearly price update, keep just MARQUE, MODELE, VERSION and PRIX HT. Every other field — equipment, costs, codes — stays as it was.') }}
                </p>
            </div>
            <div>
                <h4 class="font-semibold text-gray-900 mb-1">{{ __('Export first if you are updating') }}</h4>
                <p class="text-gray-600">
                    {{ __('Rows are matched on their names: the brand, then the boat, then the version, then the option\'s famille and désignation. Start from an export and nothing is ever duplicated. Change one of those names and you create a new record rather than rename the old one — rename in the catalogue instead.') }}
                </p>
            </div>
        </div>
    </div>

</x-app-layout>
