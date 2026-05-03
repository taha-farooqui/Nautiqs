<x-app-layout :title="$quote->number" :header="$quote->number">
@php
    // Drives the Send button + modal copy. $sendType is computed in the
    // controller from the email log + quote status.
    $sendUi = match ($sendType ?? 'quote') {
        'order_confirmation' => [
            'button' => 'Send order confirmation',
            'icon'   => 'ri-file-paper-2-line',
            'title'  => 'Send order confirmation',
            'note'   => 'Order confirmation PDF is attached automatically.',
        ],
        'follow_up' => [
            'button' => 'Send follow-up',
            'icon'   => 'ri-mail-send-line',
            'title'  => 'Send follow-up',
            'note'   => 'Original quote PDF is attached automatically.',
        ],
        default => [
            'button' => 'Send by email',
            'icon'   => 'ri-mail-send-line',
            'title'  => "Send quote {$quote->number}",
            'note'   => 'PDF is attached automatically.',
        ],
    };
@endphp
<div x-data="{
        previewOpen: {{ request()->boolean('preview') ? 'true' : 'false' }},
        emailOpen: false,
        previewLoading: true
    }">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- ===================== LIFECYCLE TIMELINE =====================
         Each step is one of: 'done' (filled), 'pending' (greyed), 'skipped'
         (used when the quote is Lost — won/order steps drop out). --}}
    @php
        $isLost  = $quote->status === \App\Models\Quote::STATUS_LOST;
        $isWon   = $quote->status === \App\Models\Quote::STATUS_WON;
        $isSent  = in_array($quote->status, [\App\Models\Quote::STATUS_SENT, \App\Models\Quote::STATUS_WON, \App\Models\Quote::STATUS_LOST], true);
        $hasEmail = (bool) $firstQuoteEmailAt;

        $steps = [
            [
                'label'  => 'Quote created',
                'icon'   => 'ri-file-add-line',
                'date'   => $quote->created_at,
                'state'  => 'done',
            ],
            [
                'label'  => 'Marked sent',
                'icon'   => 'ri-send-plane-line',
                'date'   => $quote->sent_at,
                'state'  => $isSent ? 'done' : 'pending',
            ],
            [
                'label'  => 'Email sent',
                'icon'   => 'ri-mail-send-line',
                'date'   => $firstQuoteEmailAt,
                'state'  => $hasEmail ? 'done' : 'pending',
            ],
        ];

        if ($isLost) {
            $steps[] = ['label' => 'Lost',  'icon' => 'ri-close-circle-line', 'date' => $quote->lost_at, 'state' => 'lost'];
        } else {
            $steps[] = [
                'label'  => 'Won',
                'icon'   => 'ri-trophy-line',
                'date'   => $quote->won_at,
                'state'  => $isWon ? 'done' : 'pending',
            ];
            if ($isWon) {
                $steps[] = [
                    'label'  => 'Order confirmation sent',
                    'icon'   => 'ri-file-paper-2-line',
                    'date'   => $firstOrderEmailAt,
                    'state'  => $firstOrderEmailAt ? 'done' : 'pending',
                ];
            }
        }
    @endphp

    <div class="mb-6 bg-white rounded-2xl border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-4 gap-3">
            <div class="flex items-center gap-3">
                <h3 class="font-semibold text-gray-900 text-sm">Lifecycle</h3>
                @if ($quote->duplicated_from)
                    <span class="text-xs text-gray-500">Duplicated from <span class="font-mono">{{ $quote->duplicated_from }}</span></span>
                @endif
            </div>
        </div>

        @php $stepCount = count($steps); @endphp
        <ol class="grid gap-4 overflow-x-auto pb-1"
            style="grid-template-columns: repeat({{ $stepCount }}, minmax(140px, 1fr));">
            @foreach ($steps as $i => $s)
                @php
                    $isDone  = $s['state'] === 'done';
                    $isLostS = $s['state'] === 'lost';

                    $bullet  = $isDone   ? 'bg-primary-800 text-white border-primary-800'
                             : ($isLostS ? 'bg-red-500 text-white border-red-500'
                             :             'bg-white text-gray-400 border-gray-200');

                    // Connector line between this step and the next. Coloured
                    // by the *next* step's state so a "done → pending" transition
                    // shows half-bright/half-grey at the right place.
                    $nextDone = isset($steps[$i + 1]) && in_array($steps[$i + 1]['state'], ['done', 'lost'], true);
                    $line     = $nextDone ? 'bg-primary-800' : 'bg-gray-200';

                    $title   = $isDone   ? 'text-gray-900'
                             : ($isLostS ? 'text-red-700'
                             :             'text-gray-400');
                @endphp
                <li class="relative flex flex-col items-center text-center min-w-0">
                    {{-- connector to the next bullet — sits behind the bullet,
                         starts at the bullet's right edge and stops at the next
                         bullet's left edge so it never pokes through the icon --}}
                    @if ($i < $stepCount - 1)
                        <div class="pointer-events-none absolute top-5 left-1/2 right-[-50%] h-0.5 {{ $line }}"></div>
                    @endif

                    <div class="relative w-10 h-10 rounded-full border-2 {{ $bullet }} flex items-center justify-center shadow-sm">
                        <i class="{{ $s['icon'] }} text-base"></i>
                    </div>

                    <div class="mt-2 min-w-0 max-w-full">
                        <p class="text-sm font-semibold {{ $title }} truncate">{{ $s['label'] }}</p>
                        <p class="text-xs text-gray-500 truncate">
                            {{ $s['date'] ? \Illuminate\Support\Carbon::parse($s['date'])->format('d M Y, H:i') : '—' }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- ===================== ACTION BAR ===================== --}}
    <div class="mb-6 flex flex-wrap items-center justify-end gap-2">
        <button type="button" @click="previewOpen = true; previewLoading = true"
            class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
            <i class="ri-file-pdf-line"></i> Preview PDF
        </button>
        <button type="button" @click="emailOpen = true"
            class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm transition">
            <i class="{{ $sendUi['icon'] }}"></i> {{ $sendUi['button'] }}
        </button>

        @if ($quote->isEditable())
            <a href="{{ route('quotes.edit', $quote->_id) }}"
                class="inline-flex items-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm transition">
                <i class="ri-pencil-line"></i> Edit
            </a>
        @endif

        {{-- Mark Sent stays as a single button; Mark Won/Lost get unified
             into a single "Mark as ▾" dropdown that disappears once the
             quote leaves the Sent state. --}}
        @if ($quote->status === \App\Models\Quote::STATUS_DRAFT)
            <form method="POST" action="{{ route('quotes.mark-sent', $quote->_id) }}">
                @csrf
                <button class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-medium px-4 py-2 rounded-lg text-sm transition">
                    <i class="ri-send-plane-line"></i> Mark Sent
                </button>
            </form>
        @endif

        @if ($quote->status === \App\Models\Quote::STATUS_SENT)
            <div x-data="{ open: false }" class="relative">
                <button type="button" @click="open = !open" @click.outside="open = false"
                    class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
                    <i class="ri-flag-2-line"></i> Mark as
                    <i class="ri-arrow-down-s-line"></i>
                </button>
                <div x-show="open" x-cloak x-transition.opacity
                    class="absolute right-0 top-full mt-2 w-44 z-20 bg-white rounded-lg border border-gray-200 shadow-lg py-1">
                    <form method="POST" action="{{ route('quotes.mark-won', $quote->_id) }}">
                        @csrf
                        <button class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50">
                            <i class="ri-trophy-line"></i> Won
                        </button>
                    </form>
                    <form method="POST" action="{{ route('quotes.mark-lost', $quote->_id) }}">
                        @csrf
                        <button class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-red-700 hover:bg-red-50">
                            <i class="ri-close-circle-line"></i> Lost
                        </button>
                    </form>
                </div>
            </div>
        @endif

        @if ($quote->status === \App\Models\Quote::STATUS_WON)
            <a href="{{ route('quotes.order-confirmation', $quote->_id) }}"
                class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-600 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
                <i class="ri-file-paper-2-line"></i>
                {{ $quote->order_confirmation_number ? 'Re-download order (' . $quote->order_confirmation_number . ')' : 'Generate order confirmation' }}
            </a>
        @endif

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

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Main column --}}
        <div class="xl:col-span-2 space-y-6">
            {{-- Client + Boat --}}
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2 flex items-center gap-2">
                            Client
                            @if ($isGuest)
                                <span class="px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-bold">GUEST</span>
                            @endif
                        </h4>
                        @php $name = trim(($quote->client_snapshot['first_name'] ?? '') . ' ' . ($quote->client_snapshot['last_name'] ?? '')); @endphp
                        <p class="font-semibold text-gray-900">{{ $name ?: 'Not yet specified' }}</p>
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
            @if (! empty($quote->trade_in) && (($quote->trade_in['value'] ?? 0) > 0))
                <div class="bg-white rounded-2xl border border-gray-200 p-6 flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Trade-in</h4>
                        <p class="text-sm text-gray-600">Deducted from the total payable.</p>
                    </div>
                    <div class="text-2xl font-bold text-amber-700">
                        −€{{ number_format($quote->trade_in['value'], 2, ',', ' ') }}
                    </div>
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
                <div class="px-5 py-4 bg-primary-900 text-white flex items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">Totals</h3>
                        <p class="text-xs text-white/70">Snapshot at creation</p>
                    </div>
                    <x-app.status-pill :status="$quote->status" />
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

    {{-- ──── PDF preview modal ──────────────────────────────────────── --}}
    <div x-show="previewOpen" x-transition.opacity
        class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4"
        x-cloak
        @keydown.escape.window="previewOpen = false">
        <div class="bg-white rounded-2xl w-full max-w-5xl max-h-[92vh] flex flex-col overflow-hidden shadow-2xl"
            @click.outside="previewOpen = false">
            {{-- Modal header --}}
            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
                <div class="w-10 h-10 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                    <i class="ri-file-pdf-line text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900">Quote {{ $quote->number }}</h3>
                    <p class="text-xs text-gray-500 truncate">{{ $quote->client_snapshot['first_name'] ?? '' }} {{ $quote->client_snapshot['last_name'] ?? '' }} · {{ $quote->model_snapshot['name'] ?? '' }}</p>
                </div>
                <a href="{{ route('quotes.pdf', $quote->_id) }}"
                    class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
                    <i class="ri-download-line"></i> Download
                </a>
                <button type="button" @click="previewOpen = false; emailOpen = true"
                    class="inline-flex items-center gap-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm transition">
                    <i class="{{ $sendUi['icon'] }}"></i> {{ $sendUi['button'] }}
                </button>
                <button type="button" @click="previewOpen = false"
                    class="w-9 h-9 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            {{-- PDF iframe with loader. We set src lazily (x-bind:src) only
                 once the modal is actually open, so the `@load` event fires
                 *after* the user clicks Preview. Eagerly setting src on
                 page-load made the spinner stay forever because the iframe
                 had already loaded before previewLoading was ever reset. --}}
            <div class="flex-1 relative bg-gray-100 min-h-[60vh]">
                <div x-show="previewLoading" x-transition.opacity
                    class="absolute inset-0 flex flex-col items-center justify-center text-gray-500 z-10 bg-gray-100">
                    <svg class="animate-spin w-10 h-10 text-primary-800 mb-3" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25"></circle>
                        <path d="M22 12a10 10 0 0 1-10 10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                    </svg>
                    <p class="text-sm font-medium">Generating PDF…</p>
                </div>
                <iframe
                    x-bind:src="previewOpen ? '{{ route('quotes.pdf', $quote->_id) }}?inline=1' : ''"
                    @load="if (previewOpen) previewLoading = false"
                    class="absolute inset-0 w-full h-full"
                    title="Quote {{ $quote->number }}"></iframe>
            </div>
        </div>
    </div>

    {{-- ──── Send-by-email modal ────────────────────────────────────── --}}
    <div x-show="emailOpen" x-transition.opacity
        class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4"
        x-cloak
        @keydown.escape.window="emailOpen = false">
        <form method="POST" action="{{ route('quotes.send-email', $quote->_id) }}"
            class="bg-white rounded-2xl w-full max-w-lg overflow-hidden shadow-2xl"
            x-data
            @click.outside="emailOpen = false"
            @submit="$refs.messageInput.value = $refs.messageEditor.innerHTML">
            @csrf
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                    <i class="{{ $sendUi['icon'] }} text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900">{{ $sendUi['title'] }} {{ $sendType === 'order_confirmation' ? ($quote->order_confirmation_number ?? $quote->number) : $quote->number }}</h3>
                    <p class="text-xs text-gray-500">{{ $sendUi['note'] }}</p>
                </div>
                <button type="button" @click="emailOpen = false"
                    class="w-9 h-9 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="p-5 space-y-4">
                @if ($isGuest)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800">
                        <p class="flex items-center gap-2 font-medium">
                            <i class="ri-information-line"></i>
                            This is a guest quote — please enter the recipient's details.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">First name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" required
                                value="{{ old('first_name', $quote->client_snapshot['first_name'] ?? '') }}"
                                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" required
                                value="{{ old('last_name', $quote->client_snapshot['last_name'] ?? '') }}"
                                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
                        </div>
                    </div>
                @endif

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To <span class="text-red-500">*</span></label>
                    <input type="email" name="email" required
                        value="{{ $quote->client_snapshot['email'] ?? '' }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800"
                        placeholder="client@example.com" />
                    <x-input-error :messages="$errors->get('email')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
                    <input type="text" name="subject"
                        value="{{ $emailSubject ?? '' }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium text-gray-700">Message</label>
                        @php
                            $sendTemplateType = $quote->status === \App\Models\Quote::STATUS_WON
                                ? \App\Services\EmailTemplateService::TYPE_ORDER_CONFIRMATION
                                : \App\Services\EmailTemplateService::TYPE_QUOTE;
                        @endphp
                        <a href="{{ route('email-templates.edit', $sendTemplateType) }}"
                            target="_blank"
                            class="text-xs text-primary-800 hover:underline">
                            <i class="ri-edit-line"></i> Edit template
                        </a>
                    </div>
                    <div x-ref="messageEditor"
                        contenteditable="true"
                        class="w-full max-h-72 overflow-y-auto rounded-lg border border-gray-300 focus:border-primary-800 focus:ring-primary-800 focus:outline-none px-3 py-2 text-sm bg-white prose prose-sm max-w-none">{!! $emailBodyHtml ?? '' !!}</div>
                    <input type="hidden" name="message" x-ref="messageInput" value="{{ $emailBodyHtml ?? '' }}" />
                    <p class="text-xs text-gray-500 mt-1">
                        Edit visually — formatting and the logo are preserved. Variables already substituted from the saved template.
                    </p>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-2">
                <button type="button" @click="emailOpen = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">
                    Cancel
                </button>
                <button type="submit"
                    class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-5 py-2 rounded-lg text-sm transition">
                    <i class="{{ $sendUi['icon'] }}"></i> {{ $sendUi['button'] }}
                </button>
            </div>
        </form>
    </div>

    <style>[x-cloak] { display: none !important; }</style>
</div>
</x-app-layout>
