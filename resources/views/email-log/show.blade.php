<x-app-layout :title="'Email · ' . $log->subject" :header="'Email log entry'">

    <div class="mb-4 flex items-center gap-2 text-sm text-gray-500">
        <a href="{{ route('email-log.index') }}" class="hover:text-primary-800">
            <i class="ri-arrow-left-line"></i> All emails
        </a>
    </div>

    @php
        $typeMeta = [
            'quote'              => ['label' => 'Quote',              'icon' => 'ri-file-list-3-line',  'color' => 'bg-blue-50 text-blue-700'],
            'order_confirmation' => ['label' => 'Order confirmation', 'icon' => 'ri-file-paper-2-line', 'color' => 'bg-emerald-50 text-emerald-700'],
            'follow_up'          => ['label' => 'Follow-up',          'icon' => 'ri-mail-send-line',    'color' => 'bg-amber-50 text-amber-700'],
        ];
        $tm = $typeMeta[$log->type] ?? $typeMeta['quote'];
    @endphp

    {{-- Metadata card --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-5 mb-4">
        <div class="flex items-start gap-3 mb-4">
            <span class="w-10 h-10 rounded-lg {{ $tm['color'] }} flex items-center justify-center shrink-0">
                <i class="{{ $tm['icon'] }} text-xl"></i>
            </span>
            <div class="flex-1 min-w-0">
                <h2 class="font-semibold text-gray-900 truncate">{{ $log->subject }}</h2>
                <p class="text-sm text-gray-500">
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $tm['color'] }} text-xs font-semibold mr-1">{{ $tm['label'] }}</span>
                    @if ($log->status === 'sent')
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">Sent</span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-xs font-semibold">Failed</span>
                    @endif
                </p>
            </div>
        </div>

        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">To</dt>
                <dd class="text-gray-900">{{ $log->to_name ?: '—' }} &lt;{{ $log->to_email }}&gt;</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Reply-to</dt>
                <dd class="text-gray-900">{{ $log->reply_to_email ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Quote</dt>
                <dd>
                    @if ($log->quote_id)
                        <a href="{{ route('quotes.show', $log->quote_id) }}"
                            class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700 hover:bg-primary-50 hover:text-primary-800">
                            {{ $log->quote_number }}
                        </a>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Attachment</dt>
                <dd class="text-gray-900">{{ $log->attachment_filename ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Sent by</dt>
                <dd class="text-gray-900">{{ $log->sent_by_user_name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Sent at</dt>
                <dd class="text-gray-900">{{ $log->sent_at?->format('d M Y, H:i') }} <span class="text-gray-500 text-xs">({{ $log->sent_at?->diffForHumans() }})</span></dd>
            </div>
        </dl>

        @if ($log->status === 'failed' && $log->error_message)
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <p class="font-medium mb-1">Error</p>
                <p class="font-mono text-xs whitespace-pre-wrap break-all">{{ $log->error_message }}</p>
            </div>
        @endif
    </div>

    {{-- Body preview --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
            <h3 class="font-semibold text-gray-900 text-sm">Email body (as the client received it)</h3>
        </div>
        <div class="p-6">
            <div class="prose prose-sm max-w-none">
                {!! $log->body_html !!}
            </div>
        </div>
    </div>
</x-app-layout>
