<x-app-layout :title="$client->full_name" :header="$client->full_name">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Profile card --}}
        <div class="xl:col-span-1 space-y-4">
            <div class="bg-white rounded-2xl border border-gray-200 p-6">
                <div class="flex items-center gap-3 mb-4">
                    <div class="w-12 h-12 rounded-full bg-primary-50 text-primary-800 font-bold flex items-center justify-center text-lg">
                        {{ strtoupper(mb_substr($client->first_name, 0, 1) . mb_substr($client->last_name, 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ $client->full_name }}</h3>
                        @if ($client->company_name)
                            <p class="text-sm text-gray-500">{{ $client->company_name }}</p>
                        @endif
                    </div>
                </div>

                <dl class="space-y-3 text-sm">
                    @if ($client->email)
                        <div class="flex items-start gap-2"><i class="ri-mail-line text-gray-400 mt-0.5"></i>
                            <a href="mailto:{{ $client->email }}" class="text-primary-800 hover:underline break-all">{{ $client->email }}</a>
                        </div>
                    @endif
                    @if ($client->phone)
                        <div class="flex items-start gap-2"><i class="ri-phone-line text-gray-400 mt-0.5"></i>
                            <a href="tel:{{ $client->phone }}" class="text-gray-700 hover:text-primary-800">{{ $client->phone }}</a>
                        </div>
                    @endif
                    @if ($client->full_address)
                        <div class="flex items-start gap-2"><i class="ri-map-pin-line text-gray-400 mt-0.5"></i>
                            <span class="text-gray-700">{{ $client->full_address }}</span>
                        </div>
                    @endif
                </dl>

                <div class="mt-5 flex gap-2">
                    <a href="{{ route('clients.edit', $client->_id) }}"
                        class="flex-1 inline-flex items-center justify-center gap-2 bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium px-4 py-2 rounded-lg text-sm transition">
                        <i class="ri-pencil-line"></i> Edit
                    </a>
                    <a href="{{ route('quotes.create', ['client_id' => $client->_id]) }}"
                        class="flex-1 inline-flex items-center justify-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm transition">
                        <i class="ri-add-line"></i> New quote
                    </a>
                </div>
            </div>

            @if ($client->internal_notes)
                <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="ri-lock-2-line text-amber-700"></i>
                        <h4 class="font-semibold text-amber-900 text-sm">Internal notes</h4>
                    </div>
                    <p class="text-sm text-amber-900 whitespace-pre-line">{{ $client->internal_notes }}</p>
                </div>
            @endif
        </div>

        {{-- Quotes --}}
        <div class="xl:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-gray-900">Quotes</h3>
                    <p class="text-sm text-gray-500">{{ $quotes->count() }} total</p>
                </div>
                <a href="{{ route('quotes.create', ['client_id' => $client->_id]) }}"
                    class="text-sm font-medium text-primary-800 hover:text-primary-900">
                    <i class="ri-add-line"></i> New quote
                </a>
            </div>
            @if ($quotes->isEmpty())
                <x-app.empty-state
                    icon="ri-file-list-3-line"
                    title="No quotes yet"
                    message="Build the first quote for this client."
                    ctaLabel="New quote"
                    :ctaHref="route('quotes.create', ['client_id' => $client->_id])" />
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-5 py-3 text-left font-semibold">Quote</th>
                                <th class="px-5 py-3 text-left font-semibold">Model</th>
                                <th class="px-5 py-3 text-right font-semibold">Amount</th>
                                <th class="px-5 py-3 text-left font-semibold">Status</th>
                                <th class="px-5 py-3 text-left font-semibold">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($quotes as $q)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('quotes.show', $q->_id) }}" class="font-medium text-gray-900 hover:text-primary-800">
                                            {{ $q->number }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 text-gray-700">{{ $q->model_snapshot['name'] ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right font-semibold text-gray-900">
                                        €{{ number_format($q->totals['total_ttc'] ?? 0, 0, ',', ' ') }}
                                    </td>
                                    <td class="px-5 py-3"><x-app.status-pill :status="$q->status" /></td>
                                    <td class="px-5 py-3 text-gray-600">{{ $q->created_at?->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
