<x-admin-layout :title="__('Dealers')" :header="__('Dealers')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif

    {{-- Status tab strip --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
        <div class="flex items-center gap-1 bg-white p-1 rounded-xl border border-gray-200 w-fit">
            @foreach ([
                'all'       => __('All') . ' (' . $totals['all'] . ')',
                'active'    => __('Active') . ' (' . $totals['active'] . ')',
                'suspended' => __('Suspended') . ' (' . $totals['suspended'] . ')',
            ] as $tab => $label)
                <a href="{{ route('admin.dealers.index', ['status' => $tab, 'q' => $q]) }}"
                    class="px-3 py-1.5 text-sm font-medium rounded-lg transition
                        {{ $status === $tab ? 'bg-primary-800 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
        <a href="{{ route('admin.dealers.create') }}"
            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-add-line"></i> {{ __('Add dealer') }}
        </a>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('admin.dealers.index') }}" class="bg-white rounded-2xl border border-gray-200 p-3 mb-4">
        <input type="hidden" name="status" value="{{ $status }}" />
        <div class="relative max-w-md">
            <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
            <input type="search" name="q" value="{{ $q }}"
                placeholder="{{ __('Search by name, SIREN, VAT, or email…') }}"
                class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800 focus:bg-white" />
        </div>
    </form>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($dealers->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-store-2-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">
                    {{ $q !== '' ? __('No dealers match your search.') : __('No dealers yet.') }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">{{ __('Dealer') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Contact') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Users') }}</th>
                            <th class="px-5 py-3 font-semibold text-right">{{ __('Quotes') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-5 py-3 font-semibold">{{ __('Joined') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($dealers as $d)
                            @php
                                $isSuspended = ($d->status ?? 'active') === 'suspended';
                                $initials = strtoupper(mb_substr($d->name ?? '?', 0, 2));
                            @endphp
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 font-semibold text-xs flex items-center justify-center shrink-0">
                                            {{ $initials }}
                                        </span>
                                        <div class="min-w-0">
                                            <div class="font-medium text-gray-900 truncate">{{ $d->name }}</div>
                                            <div class="text-[11px] text-gray-500 truncate">
                                                {{ $d->legal_form ?? '—' }}
                                                @if ($d->siren) · {{ __('SIREN') }} {{ $d->siren }} @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($d->_primary_user)
                                        <div class="text-sm text-gray-900 truncate max-w-[220px]">{{ $d->_primary_user->name }}</div>
                                        <div class="text-[11px] text-gray-500 truncate max-w-[220px]">{{ $d->_primary_user->email }}</div>
                                    @else
                                        <div class="text-sm text-gray-400 italic">{{ __('No user yet') }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right font-medium text-gray-900">{{ $d->_users_count }}</td>
                                <td class="px-5 py-3 text-right font-medium text-gray-900">{{ $d->_quotes_count }}</td>
                                <td class="px-5 py-3">
                                    @if ($isSuspended)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-50 text-red-700">
                                            <i class="ri-pause-circle-line"></i> {{ __('Suspended') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700">
                                            <i class="ri-checkbox-circle-line"></i> {{ __('Active') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">
                                    {{ $d->created_at?->translatedFormat('j M Y') ?? '—' }}
                                </td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('admin.dealers.show', $d->_id) }}"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium text-primary-800 hover:bg-primary-50 rounded-lg">
                                        {{ __('Open') }} <i class="ri-arrow-right-line"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">{{ $dealers->links() }}</div>
        @endif
    </div>
</x-admin-layout>
