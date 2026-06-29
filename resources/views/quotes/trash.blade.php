<x-app-layout :title="__('Trash')" :header="__('Trash')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <div class="mb-4 flex items-center justify-between gap-2 text-sm text-gray-500">
        <a href="{{ route('quotes.index') }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> {{ __('Back to all quotes') }}
        </a>
        @if ($quotes->isNotEmpty())
            <form method="POST" action="{{ route('quotes.empty-trash') }}"
                data-confirm="{{ __('Empty the Trash?') }}"
                data-confirm-text="{{ __('All quotes in the Trash will be permanently deleted. This cannot be undone.') }}"
                data-confirm-danger="1">
                @csrf @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg">
                    <i class="ri-delete-bin-2-line"></i> {{ __('Empty Trash') }}
                </button>
            </form>
        @endif
    </div>

    <form action="{{ route('quotes.trash') }}" method="GET" class="bg-white rounded-2xl border border-gray-200 p-3 mb-4">
        <div class="relative max-w-md">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="search" name="q" value="{{ $q }}"
                placeholder="{{ __('Search client, ref, model…') }}"
                class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800 focus:bg-white" />
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($quotes->isEmpty())
            <x-app.empty-state
                icon="ri-delete-bin-line"
                :title="__('Trash is empty')"
                :message="__('Quotes you move to the Trash will appear here. You can restore or permanently delete them.')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('Client') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('Ref.') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('Boat') }}</th>
                            <th class="px-4 py-3 text-right font-semibold">{{ __('Amount excl. VAT') }}</th>
                            <th class="px-4 py-3 text-left font-semibold">{{ __('Trashed') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($quotes as $quote)
                            @php
                                $first = $quote->client_snapshot['first_name'] ?? '';
                                $last  = $quote->client_snapshot['last_name']  ?? '';
                                $clientName = trim($first . ' ' . $last) ?: __('Guest');
                                $initials = strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1)) ?: 'G';
                            @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-8 h-8 rounded-full bg-gray-100 text-gray-600 text-xs font-bold flex items-center justify-center shrink-0">{{ $initials }}</span>
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900 truncate">{{ $clientName }}</div>
                                            <div class="text-xs text-gray-500 truncate">{{ $quote->client_snapshot['email'] ?? '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">{{ $quote->number }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-gray-900">{{ $quote->model_snapshot['name'] ?? '—' }}</div>
                                    <div class="text-xs text-gray-500">{{ $quote->model_snapshot['brand'] ?? '' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-gray-900">
                                    {{ number_format($quote->totals['total_ht'] ?? 0, 0, ',', ' ') }} €
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500">
                                    <div>{{ $quote->trashed_at?->translatedFormat('j M Y') }}</div>
                                    <div class="text-gray-400">{{ $quote->trashed_at?->diffForHumans() }}</div>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1">
                                        <form method="POST" action="{{ route('quotes.restore', $quote->_id) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg">
                                                <i class="ri-arrow-go-back-line"></i> {{ __('Restore') }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('quotes.force-delete', $quote->_id) }}" class="inline"
                                            data-confirm="{{ __('Permanently delete this quote?') }}"
                                            data-confirm-text="{{ __('This cannot be undone.') }}"
                                            data-confirm-danger="1">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg">
                                                <i class="ri-delete-bin-2-line"></i> {{ __('Delete forever') }}
                                            </button>
                                        </form>
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
