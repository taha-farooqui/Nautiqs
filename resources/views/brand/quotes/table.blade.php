{{-- Quote rows built by BrandQuotePresenter::summary() — never a Quote. --}}
<section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
    @isset($title)
        <div class="px-5 py-4 flex items-center justify-between">
            <h3 class="font-semibold text-gray-900">{{ $title }}</h3>
            @if (! empty($more)) <a href="{{ route('brand.quotes.index') }}" class="text-sm font-medium text-primary-800 hover:underline">{{ __('All quotes') }} →</a> @endif
        </div>
    @endisset
    @if ($rows->isEmpty())
        <p class="px-5 py-5 text-sm text-gray-500">{{ __('No quotes yet.') }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-2.5">{{ __('Date') }}</th>
                        <th class="px-4 py-2.5">{{ __('Dealer · salesperson') }}</th>
                        <th class="px-4 py-2.5">{{ __('Product') }}</th>
                        <th class="px-4 py-2.5">{{ __('Client') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('Discount') }}</th>
                        <th class="px-4 py-2.5 text-right">{{ __('Your products HT') }}</th>
                        <th class="px-5 py-2.5">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $r)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 whitespace-nowrap">{{ $r['date']?->format('d/m/Y') }}</td>
                            <td class="px-4 py-2.5"><div class="font-medium text-gray-900">{{ $r['dealer'] }}</div><div class="text-xs text-gray-500">{{ $r['salesperson'] }}</div></td>
                            <td class="px-4 py-2.5">
                                <a href="{{ route('brand.quotes.show', $r['id']) }}" class="font-medium text-primary-800 hover:underline">
                                    {{ $r['is_boat'] ? ($r['version'] ?: $r['boat']) : (implode(', ', $r['engines']) ?: $r['boat']) }}
                                </a>
                                <div class="text-xs text-gray-500">{{ $r['is_boat'] ? $r['boat'] : __('on :boat', ['boat' => $r['boat']]) }}</div>
                            </td>
                            <td class="px-4 py-2.5 text-gray-500">{{ $r['client'] }}</td>
                            <td class="px-4 py-2.5 text-right {{ $r['discount_pct'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $r['discount_pct'] > 0 ? '−' . $r['discount_pct'] . ' %' : '—' }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold whitespace-nowrap">{{ number_format($r['value_ht'], 2, ',', ' ') }} €</td>
                            <td class="px-5 py-2.5"><x-app.status-pill :status="$r['status']" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
