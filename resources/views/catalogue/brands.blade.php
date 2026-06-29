<x-app-layout :title="__('Brands')" :header="__('Brands')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-5 text-sm text-blue-900 flex items-start gap-3">
        <i class="ri-information-line text-lg mt-0.5"></i>
        <div>
            <p class="font-medium">{{ __('Brands come from the Nautiqs platform library.') }}</p>
            <p class="text-blue-800/80 mt-0.5">{{ __('All listed brands are available to you. You can customise prices on individual models, variants and options — those changes only affect your account.') }}</p>
        </div>
    </div>

    @if ($companyBrands->isEmpty())
        <x-app.empty-state
            icon="ri-store-2-line"
            :title="__('No brands yet')"
            :message="__('The platform team has not published any brands. Contact your platform admin.')"
            size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($companyBrands as $brand)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                                        <i class="ri-building-4-line"></i>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="font-semibold text-gray-900 truncate">{{ $brand->name }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('catalogue.models', ['brand' => $brand->_id]) }}"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg">
                                        <i class="ri-list-check-2"></i> {{ __('Models') }}
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

</x-app-layout>
