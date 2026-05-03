<x-app-layout title="Email log" header="Email log">
    @php
        $typeMeta = [
            'quote'              => ['label' => 'Quote',              'icon' => 'ri-file-list-3-line',  'color' => 'bg-blue-50 text-blue-700'],
            'order_confirmation' => ['label' => 'Order confirmation', 'icon' => 'ri-file-paper-2-line', 'color' => 'bg-emerald-50 text-emerald-700'],
            'follow_up'          => ['label' => 'Follow-up',          'icon' => 'ri-mail-send-line',    'color' => 'bg-amber-50 text-amber-700'],
        ];
    @endphp

    {{-- Filters + search --}}
    <form method="GET" action="{{ route('email-log.index') }}" class="mb-4 flex flex-wrap items-center gap-2">
        <div class="relative flex-1 min-w-[240px]">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="text" name="q" value="{{ $q }}"
                placeholder="Search recipient, subject, quote ref…"
                class="w-full pl-9 pr-3 py-2 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800" />
        </div>
        <select name="type" onchange="this.form.submit()"
            class="rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
            <option value="">All types</option>
            <option value="quote"              @selected($type === 'quote')>Quote</option>
            <option value="order_confirmation" @selected($type === 'order_confirmation')>Order confirmation</option>
            <option value="follow_up"          @selected($type === 'follow_up')>Follow-up</option>
        </select>
        <select name="status" onchange="this.form.submit()"
            class="rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">
            <option value="">All statuses</option>
            <option value="sent"   @selected($status === 'sent')>Sent</option>
            <option value="failed" @selected($status === 'failed')>Failed</option>
        </select>
        @if ($q || $type || $status)
            <a href="{{ route('email-log.index') }}" class="text-xs text-gray-500 hover:text-gray-900 px-2">Clear</a>
        @endif
    </form>

    {{-- KPI strip --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['all'] }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-emerald-700 uppercase tracking-wide font-medium">Sent</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['sent'] }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-red-700 uppercase tracking-wide font-medium">Failed</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['failed'] }}</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-200 p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Follow-ups</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $counts['follow_up'] }}</p>
        </div>
    </div>

    @if ($logs->isEmpty())
        <x-app.empty-state
            icon="ri-mail-line"
            title="No emails yet"
            message="Outbound emails will appear here as soon as you send a quote."
            size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Type</th>
                        <th class="px-5 py-3 font-semibold">Recipient</th>
                        <th class="px-5 py-3 font-semibold">Subject</th>
                        <th class="px-5 py-3 font-semibold">Quote</th>
                        <th class="px-5 py-3 font-semibold">Sent by</th>
                        <th class="px-5 py-3 font-semibold">Sent</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($logs as $log)
                        @php $tm = $typeMeta[$log->type] ?? $typeMeta['quote']; @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $tm['color'] }} text-xs font-semibold">
                                    <i class="{{ $tm['icon'] }}"></i> {{ $tm['label'] }}
                                </span>
                            </td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900 truncate max-w-xs">{{ $log->to_name ?: '—' }}</p>
                                <p class="text-xs text-gray-500 truncate max-w-xs">{{ $log->to_email }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-gray-900 truncate max-w-md" title="{{ $log->subject }}">{{ $log->subject }}</p>
                                @if ($log->attachment_filename)
                                    <p class="text-xs text-gray-500 mt-0.5"><i class="ri-attachment-2"></i> {{ $log->attachment_filename }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($log->quote_id)
                                    <a href="{{ route('quotes.show', $log->quote_id) }}"
                                        class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-primary-50 hover:text-primary-800">
                                        {{ $log->quote_number ?? '—' }}
                                    </a>
                                @else
                                    <span class="text-xs text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-700 text-xs">{{ $log->sent_by_user_name ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500">
                                {{ $log->sent_at?->format('d M Y H:i') }}
                                <span class="block text-gray-400">{{ $log->sent_at?->diffForHumans() }}</span>
                            </td>
                            <td class="px-5 py-3">
                                @if ($log->status === 'sent')
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Sent
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-xs font-semibold" title="{{ $log->error_message }}">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Failed
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('email-log.show', $log->_id) }}"
                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg">
                                    <i class="ri-eye-line"></i> View
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    @endif
</x-app-layout>
