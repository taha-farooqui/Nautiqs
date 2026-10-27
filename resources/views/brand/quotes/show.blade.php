<x-app-layout :title="$q['number']" :header="$q['number']">
    @php $eur = fn ($v) => number_format((float) $v, 2, ',', ' ') . ' €'; @endphp

    <div class="mb-4 text-sm"><a href="{{ route('brand.quotes.index') }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('All quotes') }}</a></div>

    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">{{ $q['boat'] }}@if ($q['version']) <span class="font-normal text-gray-500">· {{ $q['version'] }}</span>@endif</h2>
            <p class="text-sm text-gray-500">{{ __('Quote :n · :dealer · made :date', ['n' => $q['number'], 'dealer' => $q['dealer'], 'date' => $q['date']?->format('d/m/Y')]) }}</p>
        </div>
        <x-app.status-pill :status="$q['status']" />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
        <section class="bg-white rounded-2xl border border-gray-200 p-5 space-y-1">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ __('Dealer') }}</h3>
            <div class="font-semibold text-gray-900">{{ $q['dealer'] }}</div>
            <div class="text-sm text-gray-700">{{ __('Salesperson') }}: {{ $q['salesperson'] ?: '—' }}</div>
            @if ($q['level']) <div class="text-sm text-gray-700">{{ __('Level') }}: {{ $q['level'] }}</div> @endif
        </section>
        <section class="bg-white rounded-2xl border border-gray-200 p-5 space-y-1">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ __('Client') }}</h3>
            <div class="font-semibold text-gray-900 flex items-center gap-2"><i class="ri-lock-line text-gray-400"></i> {{ $q['client'] }}</div>
            <p class="text-sm text-gray-500">{{ __('Name, email, phone and address are never shared with brands.') }}</p>
        </section>
        <section class="bg-white rounded-2xl border border-gray-200 p-5 text-sm space-y-1.5">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-2">{{ __('Timeline') }}</h3>
            <div class="flex justify-between"><span>{{ __('Created') }}</span><span>{{ $q['date']?->format('d/m/Y') }}</span></div>
            @if ($q['sent_at']) <div class="flex justify-between"><span>{{ __('Sent') }}</span><span>{{ $q['sent_at']->format('d/m/Y') }}</span></div> @endif
            @if ($q['won_at']) <div class="flex justify-between"><span>{{ __('Won') }}</span><span>{{ $q['won_at']->format('d/m/Y') }}</span></div> @endif
            @if ($q['lost_at']) <div class="flex justify-between"><span>{{ __('Lost') }}</span><span>{{ $q['lost_at']->format('d/m/Y') }}</span></div> @endif
            @if ($q['expires_at']) <div class="flex justify-between"><span>{{ __('Valid until') }}</span><span>{{ $q['expires_at']->format('d/m/Y') }}</span></div> @endif
        </section>
    </div>

    <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4"><h3 class="font-semibold text-gray-900">{{ __('What was quoted') }}</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px] text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr><th class="px-5 py-2.5">{{ __('Item') }}</th><th class="px-4 py-2.5 text-center">{{ __('Qty') }}</th><th class="px-4 py-2.5 text-right">{{ __('Public HT') }}</th><th class="px-4 py-2.5 text-right">{{ __('Discount') }}</th><th class="px-5 py-2.5 text-right">{{ __('Line HT') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($q['lines'] as $l)
                        <tr>
                            <td class="px-5 py-2.5 {{ $l['kind'] === 'boat' ? 'font-semibold text-gray-900' : 'text-gray-800' }} {{ $l['kind'] === 'accessory' ? 'pl-9' : '' }}">
                                @if ($l['kind'] === 'engine') <i class="ri-settings-3-line text-gray-400"></i> @endif
                                {{ $l['label'] }}
                                @if (! empty($l['other'])) <span class="ml-1 text-[11px] font-medium text-gray-500 bg-gray-100 rounded px-1.5 py-0.5">{{ __('not your product') }}</span> @endif
                            </td>
                            <td class="px-4 py-2.5 text-center">{{ $l['qty'] }}</td>
                            <td class="px-4 py-2.5 text-right">{{ $eur($l['unit']) }}</td>
                            <td class="px-4 py-2.5 text-right {{ $l['discount_pct'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $l['discount_pct'] > 0 ? '−' . $l['discount_pct'] . ' %' : '—' }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold">{{ $eur($l['total']) }}</td>
                        </tr>
                    @endforeach
                    @if ($q['other_count'])
                        <tr class="text-gray-500">
                            <td class="px-5 py-2.5" colspan="4">{{ trans_choice('{1}Other item (other brands or services)|[2,*]Other items (other brands or services): :count', $q['other_count'], ['count' => $q['other_count']]) }}</td>
                            <td class="px-5 py-2.5 text-right">{{ $eur($q['other_total']) }}</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
            <dl class="grid grid-cols-[auto_auto] gap-x-8 gap-y-1.5 text-sm">
                <dt class="text-gray-500">{{ __('Your products HT') }}</dt><dd class="text-right font-semibold">{{ $eur($q['value_ht']) }}</dd>
                <dt class="text-gray-500">{{ __('Discounts on your products') }}</dt><dd class="text-right font-semibold {{ $q['discount_pct'] > 0 ? 'text-red-600' : '' }}">{{ $q['discount_pct'] > 0 ? '−' . $q['discount_pct'] . ' %' : '—' }}</dd>
                @if ($q['global_discount_pct'] > 0)
                    <dt class="text-gray-500">{{ __('Quote-wide discount') }}</dt><dd class="text-right font-semibold text-red-600">−{{ round($q['global_discount_pct'], 1) }} %</dd>
                @endif
                <dt class="text-gray-500">{{ __('Quote total HT') }}</dt><dd class="text-right font-semibold">{{ $eur($q['total_ht']) }}</dd>
            </dl>
        </div>
    </section>

    <p class="mt-4 text-sm text-gray-500">{{ __('Not shared with brands: the dealer\'s purchase prices and margin, internal notes, trade-in, and the detail of other brands\' items.') }}</p>
</x-app-layout>
