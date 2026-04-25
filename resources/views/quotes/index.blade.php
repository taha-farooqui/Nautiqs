<x-app-layout title="Quotes" header="Quotes">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        {{-- Status tabs --}}
        <div class="px-5 pt-4 border-b border-gray-100 flex flex-wrap gap-2 overflow-x-auto">
            @php
                $tabs = [
                    ''      => ['All',    $counts['all']],
                    'draft' => ['Draft',  $counts['draft']],
                    'sent'  => ['Sent',   $counts['sent']],
                    'won'   => ['Won',    $counts['won']],
                    'lost'  => ['Lost',   $counts['lost']],
                ];
            @endphp
            @foreach ($tabs as $key => [$label, $count])
                @php $active = ($status ?? '') === $key; @endphp
                <a href="{{ route('quotes.index', array_filter(['status' => $key ?: null, 'q' => $q])) }}"
                    class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium border-b-2 -mb-px
                        {{ $active ? 'border-primary-800 text-primary-800' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
                    {{ $label }}
                    <span class="text-xs px-1.5 rounded-full {{ $active ? 'bg-primary-50' : 'bg-gray-100' }}">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        {{-- Toolbar --}}
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 sm:items-center">
            <form action="{{ route('quotes.index') }}" method="GET" class="flex-1 max-w-md">
                @if ($status) <input type="hidden" name="status" value="{{ $status }}" /> @endif
                <div class="relative">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="search" name="q" value="{{ $q }}"
                        placeholder="Search by quote number, client or model..."
                        class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800 focus:bg-white" />
                </div>
            </form>
            <a href="{{ route('quotes.create') }}"
                class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm transition sm:ml-auto">
                <i class="ri-add-line"></i> New quote
            </a>
        </div>

        @if ($quotes->isEmpty())
            @if ($q || $status)
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
                            <th class="px-5 py-3 text-left font-semibold">Quote</th>
                            <th class="px-5 py-3 text-left font-semibold">Client</th>
                            <th class="px-5 py-3 text-left font-semibold">Model</th>
                            <th class="px-5 py-3 text-right font-semibold">Amount</th>
                            <th class="px-5 py-3 text-left font-semibold">Status</th>
                            <th class="px-5 py-3 text-left font-semibold">Date</th>
                            <th class="px-5 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($quotes as $q)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('quotes.show', $q->_id) }}" class="font-medium text-gray-900 hover:text-primary-800">{{ $q->number }}</a>
                                </td>
                                <td class="px-5 py-3 text-gray-700">
                                    {{ trim(($q->client_snapshot['first_name'] ?? '') . ' ' . ($q->client_snapshot['last_name'] ?? '')) ?: '—' }}
                                </td>
                                <td class="px-5 py-3 text-gray-700">{{ $q->model_snapshot['name'] ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-semibold">€{{ number_format($q->totals['total_ttc'] ?? 0, 0, ',', ' ') }}</td>
                                <td class="px-5 py-3"><x-app.status-pill :status="$q->status" /></td>
                                <td class="px-5 py-3 text-gray-600">{{ $q->created_at?->format('M j, Y') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('quotes.show', $q->_id) }}" title="View"
                                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg"><i class="ri-eye-line"></i></a>
                                        <a href="{{ route('quotes.pdf', $q->_id) }}" title="Download PDF"
                                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg"><i class="ri-download-line"></i></a>
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
