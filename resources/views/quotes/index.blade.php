<x-app-layout title="Quotes" header="Quotes">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Top action --}}
    <div class="flex items-center justify-end gap-2 mb-4">
        <a href="{{ route('quotes.create') }}"
            class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
            <i class="ri-add-line"></i> New quote
        </a>
    </div>

    {{-- Stats strip --}}
    <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center"><i class="ri-file-list-3-line text-lg"></i></span>
            <div>
                <div class="text-2xl font-bold text-gray-900 leading-tight">{{ $stats['this_month'] }}</div>
                <div class="text-xs text-gray-500">Quotes this month</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center"><i class="ri-send-plane-line text-lg"></i></span>
            <div>
                <div class="text-2xl font-bold text-gray-900 leading-tight">{{ $stats['awaiting'] }}</div>
                <div class="text-xs text-gray-500">Sent — awaiting reply</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center"><i class="ri-trophy-line text-lg"></i></span>
            <div>
                <div class="text-2xl font-bold text-gray-900 leading-tight">{{ $stats['won_month'] }}</div>
                <div class="text-xs text-gray-500">Won this month</div>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center"><i class="ri-time-line text-lg"></i></span>
            <div>
                <div class="text-2xl font-bold {{ $stats['expiring'] > 0 ? 'text-amber-600' : 'text-gray-900' }} leading-tight">{{ $stats['expiring'] }}</div>
                <div class="text-xs text-gray-500">Expiring in 3 days</div>
            </div>
        </div>
    </section>

    {{-- Filters bar --}}
    <form action="{{ route('quotes.index') }}" method="GET" class="bg-white rounded-2xl border border-gray-200 p-3 mb-4 flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[200px]">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="search" name="q" value="{{ $q }}"
                placeholder="Search client, ref, model..."
                class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800 focus:bg-white" />
        </div>
        <select name="brand" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800">
            <option value="">All brands</option>
            @foreach ($brands as $b)
                <option value="{{ $b }}" @selected($brand === $b)>{{ $b }}</option>
            @endforeach
        </select>
        <select name="model" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800">
            <option value="">All models</option>
            @foreach ($models as $m)
                <option value="{{ $m['code'] }}" @selected($modelCode === $m['code'])>{{ $m['name'] }}</option>
            @endforeach
        </select>
        <select name="month" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800">
            <option value="">All months</option>
            @foreach ($months as $ym)
                <option value="{{ $ym }}" @selected($month === $ym)>{{ \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('F Y') }}</option>
            @endforeach
        </select>
        @if ($status) <input type="hidden" name="status" value="{{ $status }}" /> @endif
        <noscript><button class="px-3 py-2 rounded-lg bg-primary-800 text-white text-sm">Apply</button></noscript>
    </form>

    {{-- Status segmented tabs --}}
    @php
        $tabs = [
            ''      => ['label' => 'All',   'count' => $counts['all'],   'color' => 'text-gray-700 bg-gray-100'],
            'draft' => ['label' => 'Draft', 'count' => $counts['draft'], 'color' => 'text-gray-700 bg-gray-100'],
            'sent'  => ['label' => 'Sent',  'count' => $counts['sent'],  'color' => 'text-blue-700 bg-blue-50'],
            'won'   => ['label' => 'Won',   'count' => $counts['won'],   'color' => 'text-emerald-700 bg-emerald-50'],
            'lost'  => ['label' => 'Lost',  'count' => $counts['lost'],  'color' => 'text-red-700 bg-red-50'],
        ];
    @endphp
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-1">
            @foreach ($tabs as $key => $tab)
                @php $active = ($status ?? '') === $key; @endphp
                <a href="{{ route('quotes.index', array_filter(['status' => $key ?: null, 'q' => $q, 'brand' => $brand, 'model' => $modelCode, 'month' => $month])) }}"
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-sm font-medium transition
                        {{ $active ? 'bg-primary-800 text-white' : 'text-gray-700 hover:bg-gray-50' }}">
                    {{ $tab['label'] }}
                    <span class="text-xs px-1.5 py-0.5 rounded-full {{ $active ? 'bg-white/20' : $tab['color'] }}">{{ $tab['count'] }}</span>
                </a>
            @endforeach
            <span class="ml-auto text-xs text-gray-500">
                {{ $quotes->total() }} {{ $quotes->total() === 1 ? 'quote' : 'quotes' }}
                @if ($quotes->total() > 0) · sorted by date <i class="ri-arrow-down-s-line"></i> @endif
            </span>
        </div>

        @if ($quotes->isEmpty())
            @if ($q || $status || $brand || $modelCode || $month)
                <x-app.empty-state
                    icon="ri-search-line"
                    title="No matches"
                    message="No quotes matched your filters. Try clearing them or adjusting your search." />
            @else
                <x-app.empty-state
                    icon="ri-file-list-3-line"
                    title="No quotes yet"
                    message="Create your first quote — it takes under 2 minutes."
                    ctaLabel="Create your first quote"
                    ctaHref="{{ route('quotes.create') }}"
                    size="lg" />
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Client</th>
                            <th class="px-4 py-3 text-left font-semibold">Ref.</th>
                            <th class="px-4 py-3 text-left font-semibold">Boat</th>
                            <th class="px-4 py-3 text-right font-semibold">Amount excl. VAT</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Date</th>
                            <th class="px-4 py-3 text-left font-semibold">Expires</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($quotes as $quote)
                            @php
                                $first = $quote->client_snapshot['first_name'] ?? '';
                                $last  = $quote->client_snapshot['last_name']  ?? '';
                                $clientName = trim($first . ' ' . $last) ?: 'Guest';
                                $initials = strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: 'G';
                                $daysToExpiry = $quote->daysUntilExpiry();
                            @endphp
                            <tr class="hover:bg-gray-50 cursor-pointer"
                                data-href="{{ route('quotes.show', $quote->_id) }}"
                                onclick="if (!event.target.closest('a, button, form, input')) window.location = this.dataset.href">
                                {{-- Client --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-8 h-8 rounded-full bg-primary-50 text-primary-800 text-xs font-bold flex items-center justify-center shrink-0">{{ $initials }}</span>
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900 truncate">{{ $clientName }}</div>
                                            <div class="text-xs text-gray-500 truncate">{{ $quote->client_snapshot['email'] ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>

                                {{-- Ref --}}
                                <td class="px-4 py-3">
                                    <a href="{{ route('quotes.show', $quote->_id) }}"
                                        class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-primary-50 hover:text-primary-800">
                                        {{ $quote->number }}
                                    </a>
                                </td>

                                {{-- Boat --}}
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $quote->model_snapshot['name'] ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ $quote->model_snapshot['brand'] ?? '' }}</div>
                                </td>

                                {{-- Amount --}}
                                <td class="px-4 py-3 text-right">
                                    <div class="font-semibold text-gray-900">
                                        €{{ number_format($quote->totals['total_ht'] ?? 0, 0, ',', ' ') }}
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        incl. VAT €{{ number_format($quote->totals['total_ttc'] ?? 0, 0, ',', ' ') }}
                                    </div>
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-3"><x-app.status-pill :status="$quote->status" /></td>

                                {{-- Date --}}
                                <td class="px-4 py-3 text-gray-700">
                                    <div>{{ $quote->created_at?->format('j M Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $quote->created_at?->diffForHumans() }}</div>
                                </td>

                                {{-- Expires --}}
                                <td class="px-4 py-3">
                                    @if ($quote->status === \App\Models\Quote::STATUS_WON || $quote->status === \App\Models\Quote::STATUS_LOST)
                                        <span class="text-xs text-gray-400">—</span>
                                    @elseif ($daysToExpiry === null)
                                        <span class="text-xs text-gray-400">—</span>
                                    @elseif ($daysToExpiry < 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-xs font-semibold">
                                            <i class="ri-error-warning-line"></i> Expired
                                        </span>
                                    @elseif ($daysToExpiry <= 3)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs font-semibold">
                                            <i class="ri-time-line"></i> {{ $daysToExpiry }} {{ $daysToExpiry === 1 ? 'day' : 'days' }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-600">{{ $daysToExpiry }} days</span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-0.5">
                                        @if ($quote->isEditable())
                                            <a href="{{ route('quotes.edit', $quote->_id) }}" title="Edit"
                                                class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg">
                                                <i class="ri-pencil-line"></i>
                                            </a>
                                        @endif
                                        <form method="POST" action="{{ route('quotes.duplicate', $quote->_id) }}" class="inline">
                                            @csrf
                                            <button type="submit" title="Duplicate"
                                                class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg">
                                                <i class="ri-file-copy-line"></i>
                                            </button>
                                        </form>
                                        @if ($quote->status === \App\Models\Quote::STATUS_WON)
                                            <a href="{{ route('quotes.order-confirmation', $quote->_id) }}" title="Generate order confirmation"
                                                class="w-8 h-8 inline-flex items-center justify-center text-emerald-600 hover:bg-emerald-50 rounded-lg">
                                                <i class="ri-file-paper-2-line"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('quotes.pdf', $quote->_id) }}" title="Download PDF"
                                                class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg">
                                                <i class="ri-download-line"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('quotes.show', $quote->_id) }}" title="Open"
                                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">{{ $quotes->links() }}</div>
        @endif
    </div>
</x-app-layout>
