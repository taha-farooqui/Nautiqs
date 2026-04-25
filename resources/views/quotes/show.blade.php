<x-app-layout :title="$quote->number" :header="$quote->number">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Action bar --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex items-center gap-3">
            <x-app.status-pill :status="$quote->status" />
            @if ($quote->duplicated_from)
                <span class="text-xs text-gray-500">Duplicated from <span class="font-mono">{{ $quote->duplicated_from }}</span></span>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('quotes.pdf', $quote->_id) }}"
                class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
                <i class="ri-download-line"></i> Download PDF
            </a>

            @if ($quote->isEditable())
                <a href="{{ route('quotes.edit', $quote->_id) }}"
                    class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm transition">
                    <i class="ri-pencil-line"></i> Edit
                </a>
            @endif

            {{-- §11.2 status transitions --}}
            @if ($quote->status === \App\Models\Quote::STATUS_DRAFT)
                <form method="POST" action="{{ route('quotes.mark-sent', $quote->_id) }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition">
                        <i class="ri-send-plane-line"></i> Mark Sent
                    </button>
                </form>
            @endif
            @if ($quote->status === \App\Models\Quote::STATUS_SENT)
                <form method="POST" action="{{ route('quotes.mark-won', $quote->_id) }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition">
                        <i class="ri-trophy-line"></i> Mark Won
                    </button>
                </form>
                <form method="POST" action="{{ route('quotes.mark-lost', $quote->_id) }}">
                    @csrf
                    <button class="inline-flex items-center gap-2 bg-gray-600 hover:bg-gray-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition">
                        <i class="ri-close-circle-line"></i> Mark Lost
                    </button>
                </form>
            @endif

            {{-- §13 Order confirmation --}}
            @if ($quote->status === \App\Models\Quote::STATUS_WON)
                <a href="{{ route('quotes.order-confirmation', $quote->_id) }}"
                    class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
                    <i class="ri-file-paper-2-line"></i>
                    {{ $quote->order_confirmation_number ? 'Re-download order (' . $quote->order_confirmation_number . ')' : 'Generate order confirmation' }}
                </a>
            @endif

            {{-- §11.3 Duplicate --}}
            <form method="POST" action="{{ route('quotes.duplicate', $quote->_id) }}">
                @csrf
                <button class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm transition">
                    <i class="ri-file-copy-line"></i> Duplicate
                </button>
            </form>

            @if ($quote->status === \App\Models\Quote::STATUS_DRAFT)
                <form method="POST" action="{{ route('quotes.destroy', $quote->_id) }}"
                    onsubmit="return confirm('Delete this draft? This cannot be undone.');">
                    @csrf @method('DELETE')
                    <button class="inline-flex items-center justify-center w-9 h-9 text-red-600 hover:bg-red-50 rounded-lg">
                        <i class="ri-delete-bin-line"></i>
                    </button>
                </form>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Main column --}}
        <div class="xl:col-span-2 space-y-6">
            {{-- Client + Boat --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Client</h4>
                        <p class="font-semibold text-gray-900">{{ trim(($quote->client_snapshot['first_name'] ?? '') . ' ' . ($quote->client_snapshot['last_name'] ?? '')) }}</p>
                        @if (! empty($quote->client_snapshot['company_name']))
                            <p class="text-sm text-gray-700">{{ $quote->client_snapshot['company_name'] }}</p>
                        @endif
                        <p class="text-sm text-gray-600">{{ $quote->client_snapshot['email'] ?? '' }}</p>
                        <p class="text-sm text-gray-600">{{ $quote->client_snapshot['phone'] ?? '' }}</p>
                        <p class="text-sm text-gray-600 mt-1">
                            {{ $quote->client_snapshot['address_line'] ?? '' }}
                            @if (! empty($quote->client_snapshot['postal_code']) || ! empty($quote->client_snapshot['city']))
                                <br>{{ trim(($quote->client_snapshot['postal_code'] ?? '') . ' ' . ($quote->client_snapshot['city'] ?? '')) }}
                            @endif
                        </p>
                    </div>
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Boat</h4>
                        <p class="font-semibold text-gray-900">{{ $quote->model_snapshot['name'] ?? '' }}</p>
                        <p class="text-sm text-gray-700">{{ $quote->variant_snapshot['name'] ?? '' }}</p>
                        <p class="text-sm text-gray-500">{{ $quote->model_snapshot['brand'] ?? '' }} · {{ $quote->model_snapshot['code'] ?? '' }}</p>
                    </div>
                </div>
            </div>

            {{-- Included equipment --}}
            @if (! empty($quote->included_equipment))
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Included equipment</h4>
                    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-sm text-gray-700">
                        @foreach ($quote->included_equipment as $eq)
                            <li class="flex items-center gap-2">
                                <i class="ri-check-line text-emerald-600"></i> {{ $eq['label'] ?? '' }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Options --}}
            @if (! empty($quote->options))
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Selected options</h4>
                    <table class="w-full text-sm">
                        <thead class="text-xs text-gray-500">
                            <tr>
                                <th class="text-left py-2">Item</th>
                                <th class="text-right py-2">Qty</th>
                                <th class="text-right py-2">Unit</th>
                                <th class="text-right py-2">Total HT</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($quote->options as $opt)
                                <tr>
                                    <td class="py-2">
                                        <div class="font-medium text-gray-900">{{ $opt['label'] ?? '' }}</div>
                                        <div class="text-xs text-gray-500">{{ $opt['category'] ?? '' }}</div>
                                    </td>
                                    <td class="py-2 text-right">{{ $opt['quantity'] ?? 1 }}</td>
                                    <td class="py-2 text-right">€{{ number_format($opt['unit_price'] ?? 0, 2, ',', ' ') }}</td>
                                    <td class="py-2 text-right font-medium">€{{ number_format($opt['line_after_cat'] ?? 0, 2, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Custom items --}}
            @if (! empty($quote->custom_items))
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Custom items</h4>
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($quote->custom_items as $ci)
                                <tr>
                                    <td class="py-2 font-medium text-gray-900">{{ $ci['label'] ?? '' }}</td>
                                    <td class="py-2 text-right font-medium">€{{ number_format($ci['line_after_cat'] ?? $ci['amount'] ?? 0, 2, ',', ' ') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Trade-in --}}
            @if (! empty($quote->trade_in))
                <div class="bg-white rounded-2xl border border-gray-200 p-6">
                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3">Trade-in</h4>
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div><dt class="text-gray-500 text-xs">Brand</dt><dd class="font-medium">{{ $quote->trade_in['brand'] ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 text-xs">Model</dt><dd class="font-medium">{{ $quote->trade_in['model'] ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 text-xs">Year</dt><dd class="font-medium">{{ $quote->trade_in['year'] ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 text-xs">Engine</dt><dd class="font-medium">{{ $quote->trade_in['engine'] ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 text-xs">Engine hours</dt><dd class="font-medium">{{ $quote->trade_in['engine_hours'] ?? '—' }}</dd></div>
                        <div><dt class="text-gray-500 text-xs">Trade-in value</dt><dd class="font-semibold">€{{ number_format($quote->trade_in['value'] ?? 0, 2, ',', ' ') }}</dd></div>
                    </dl>
                    @if (! empty($quote->trade_in['description']))
                        <p class="mt-3 text-sm text-gray-700">{{ $quote->trade_in['description'] }}</p>
                    @endif
                </div>
            @endif

            {{-- Internal notes --}}
            @if ($quote->internal_notes)
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="ri-lock-2-line text-amber-700"></i>
                        <h4 class="font-semibold text-amber-900 text-sm">Internal notes</h4>
                        <span class="ml-auto text-xs text-amber-700">Never in PDF</span>
                    </div>
                    <p class="text-sm text-amber-900 whitespace-pre-line">{{ $quote->internal_notes }}</p>
                </div>
            @endif
        </div>

        {{-- Side: totals --}}
        <div class="xl:col-span-1">
            <div class="sticky top-20 bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 bg-primary-900 text-white">
                    <h3 class="font-semibold">Totals</h3>
                    <p class="text-xs text-white/70">Snapshot at creation</p>
                </div>
                @php $t = $quote->totals ?? []; @endphp
                <dl class="p-5 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-600">Base (HT)</dt><dd class="font-medium">€{{ number_format($t['base_ht'] ?? 0, 2, ',', ' ') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Options (HT)</dt><dd class="font-medium">€{{ number_format($t['options_ht'] ?? 0, 2, ',', ' ') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">Custom (HT)</dt><dd class="font-medium">€{{ number_format($t['custom_items_ht'] ?? 0, 2, ',', ' ') }}</dd></div>
                    @if (($t['global_discount_amount'] ?? 0) > 0)
                        <div class="flex justify-between text-red-600"><dt>Global discount</dt><dd class="font-medium">−€{{ number_format($t['global_discount_amount'], 2, ',', ' ') }}</dd></div>
                    @endif
                    <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="font-semibold">Total HT</dt><dd class="font-semibold">€{{ number_format($t['total_ht'] ?? 0, 2, ',', ' ') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-600">VAT ({{ $t['vat_rate'] ?? 20 }}%)</dt><dd class="font-medium">€{{ number_format($t['vat_amount'] ?? 0, 2, ',', ' ') }}</dd></div>
                    <div class="flex justify-between pt-2 border-t border-gray-100"><dt class="font-semibold">Total TTC</dt><dd class="font-semibold">€{{ number_format($t['total_ttc'] ?? 0, 2, ',', ' ') }}</dd></div>
                    @if (($t['trade_in_deduction'] ?? 0) > 0)
                        <div class="flex justify-between text-gray-600"><dt>Trade-in</dt><dd>−€{{ number_format($t['trade_in_deduction'], 2, ',', ' ') }}</dd></div>
                    @endif
                    <div class="flex justify-between pt-3 border-t-2 border-gray-200">
                        <dt class="font-bold text-lg">Net payable</dt>
                        <dd class="font-bold text-lg text-primary-900">€{{ number_format($t['net_payable'] ?? 0, 2, ',', ' ') }}</dd>
                    </div>
                </dl>
                <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-gray-600">Margin
                            @if (($t['margin_type'] ?? '') === 'real')
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 font-semibold">REAL</span>
                            @else
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 font-semibold">ESTIMATED</span>
                            @endif
                        </span>
                        <span class="font-semibold text-gray-900">€{{ number_format($t['margin_amount'] ?? 0, 0, ',', ' ') }} ({{ $t['margin_pct'] ?? 0 }}%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
