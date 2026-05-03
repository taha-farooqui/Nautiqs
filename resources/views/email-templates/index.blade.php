<x-app-layout title="Email templates" header="Email templates">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif

    <p class="text-sm text-gray-600 max-w-3xl mb-6">
        Three templates power every email Nautiqs sends. Each can be customised independently —
        variables like <code class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">@{{quote_number}}</code>
        resolve to whichever document is being sent. Your company logo is added automatically at the top of every email.
    </p>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                <tr>
                    <th class="px-5 py-3 font-semibold">Template</th>
                    <th class="px-5 py-3 font-semibold">Subject</th>
                    <th class="px-5 py-3 font-semibold">Last updated</th>
                    <th class="px-5 py-3 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach ($templates as $type => $template)
                    @php $m = $meta[$type]; @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-3">
                            <div class="flex items-start gap-3">
                                <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                                    <i class="{{ $m['icon'] }}"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-900">{{ $m['name'] }}</p>
                                    <p class="text-xs text-gray-500 max-w-md">{{ $m['description'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3">
                            <p class="text-gray-900 truncate max-w-md" title="{{ $template->subject }}">{{ $template->subject }}</p>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">
                            {{ $template->updated_at?->diffForHumans() ?? '—' }}
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('email-templates.edit', $type) }}"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-primary-50 hover:bg-primary-100 text-primary-800 rounded-lg">
                                    <i class="ri-pencil-line"></i> Edit
                                </a>
                                @php $resetMessage = "Reset the {$m['name']} template to the default?"; @endphp
                                <form method="POST" action="{{ route('email-templates.reset', $type) }}"
                                    onsubmit="return confirm({{ Js::from($resetMessage) }});">
                                    @csrf
                                    <button class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg">
                                        <i class="ri-refresh-line"></i> Reset
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-app-layout>
