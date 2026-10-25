<x-app-layout :title="__('Partner brands')" :header="__('Partner brands')">
    @include('brand.partials.flash')

    <p class="mb-4 text-sm text-gray-600 max-w-3xl">{{ __('Brands that share their official catalogue with you: public prices stay up to date and your purchase prices are filled in from your level.') }}</p>

    @if ($accesses->isEmpty())
        <x-app.empty-state icon="ri-building-4-line" :title="__('No partner brands yet')"
            :message="__('When a brand or importer invites you, the invitation appears here.')" size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('Brand account') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('Brands') }}</th>
                            <th class="px-4 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($accesses as $a)
                            @php $acc = $accounts[(string) $a->brand_account_id] ?? null; @endphp
                            <tr>
                                <td class="px-5 py-3 font-semibold text-gray-900">{{ $acc?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ implode(', ', $invitations->brandNames($a)) }}</td>
                                <td class="px-4 py-3">
                                    @if ($a->isActive())
                                        <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5">{{ __('Connected since :date', ['date' => $a->responded_at?->format('d/m/Y')]) }}</span>
                                    @else
                                        <span class="text-xs font-semibold text-amber-700 bg-amber-50 rounded-full px-2 py-0.5">{{ __('Waiting for you') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($a->isActive())
                                        @if (auth()->user()->isTenantAdmin())
                                            <form method="POST" action="{{ route('partner-brands.disconnect', $a->_id) }}" data-confirm-danger="1"
                                                data-confirm="{{ __('Disconnect from this brand? Its catalogue is hidden from your quotes; quotes already made are unchanged.') }}">
                                                @csrf
                                                <button class="px-3 py-1.5 text-xs font-semibold border border-red-200 text-red-700 hover:bg-red-50 rounded-lg">{{ __('Disconnect') }}</button>
                                            </form>
                                        @endif
                                    @else
                                        <a href="{{ route('partner-brands.show', $a->_id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">{{ __('Review') }}</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($archivedBrands->isNotEmpty() || $archivedEngines->isNotEmpty())
        <section class="mt-6 bg-white rounded-2xl border border-gray-200 p-5 space-y-3">
            <div>
                <h3 class="font-semibold text-gray-900">{{ __('Archived manual catalogues') }}</h3>
                <p class="text-sm text-gray-500">{{ __('Archived when a brand connected, so your quotes don\'t offer the same boats twice. Your old quotes still use them.') }}</p>
            </div>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($archivedBrands as $b)
                    <li class="py-2 flex items-center justify-between gap-2">
                        <span>{{ $b->name }} <span class="text-gray-500">· {{ __('boats') }}</span></span>
                        @if (auth()->user()->isTenantAdmin())
                            <form method="POST" action="{{ route('partner-brands.restore') }}">@csrf
                                <input type="hidden" name="brand_id" value="{{ $b->_id }}">
                                <button class="text-xs font-semibold text-primary-800 hover:underline">{{ __('Restore') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
                @foreach ($archivedEngines as $brandName => $engines)
                    <li class="py-2 flex items-center justify-between gap-2">
                        <span>{{ $brandName }} <span class="text-gray-500">· {{ trans_choice('{1}:count engine|[2,*]:count engines', $engines->count(), ['count' => $engines->count()]) }}</span></span>
                        @if (auth()->user()->isTenantAdmin())
                            <form method="POST" action="{{ route('partner-brands.restore') }}">@csrf
                                <input type="hidden" name="engine_brand" value="{{ $brandName }}">
                                <button class="text-xs font-semibold text-primary-800 hover:underline">{{ __('Restore') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-app-layout>
