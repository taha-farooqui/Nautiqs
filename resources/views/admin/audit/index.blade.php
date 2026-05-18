<x-admin-layout :title="__('Activity log')" :header="__('Activity log')">

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.audit.index') }}"
        class="bg-white rounded-2xl border border-gray-200 p-3 mb-4 flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
        <div class="relative flex-1">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="search" name="q" value="{{ $q }}"
                placeholder="{{ __('Search actor, action, or target…') }}"
                class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800 focus:bg-white" />
        </div>
        <select name="action" onchange="this.form.submit()"
            class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[160px]">
            <option value="">{{ __('Any action') }}</option>
            @foreach ($allActions as $a)
                <option value="{{ $a }}" @selected($action === $a)>{{ $a }}</option>
            @endforeach
        </select>
        <select name="target_type" onchange="this.form.submit()"
            class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[160px]">
            <option value="">{{ __('Any target') }}</option>
            @foreach ($allTargetTypes as $t)
                <option value="{{ $t }}" @selected($targetType === $t)>{{ $t }}</option>
            @endforeach
        </select>
        <button type="submit"
            class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-filter-line"></i> {{ __('Filter') }}
        </button>
    </form>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($events->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-shield-check-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No activity yet.') }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ __('Destructive superadmin actions are recorded here automatically.') }}</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('When') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Actor') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Action') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Target') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('IP') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($events as $row)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                                    <div class="text-gray-900">{{ $row->created_at?->translatedFormat('j M Y H:i') }}</div>
                                    <div>{{ $row->created_at?->diffForHumans() }}</div>
                                </td>
                                <td class="px-5 py-3 text-sm">
                                    <div class="text-gray-900 truncate max-w-[220px]">{{ $row->actor_email ?? '—' }}</div>
                                    <div class="text-[11px] text-gray-500">{{ $row->actor_role ?? '' }}</div>
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-mono text-xs px-2 py-1 rounded bg-gray-100 text-gray-700">{{ $row->action }}</span>
                                </td>
                                <td class="px-5 py-3 text-sm">
                                    @if ($row->target_label)
                                        <div class="text-gray-900 truncate max-w-[300px]">{{ $row->target_label }}</div>
                                        <div class="text-[11px] text-gray-500">{{ $row->target_type }}</div>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500 font-mono">{{ $row->ip_address ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">{{ $events->links() }}</div>
        @endif
    </div>
</x-admin-layout>
