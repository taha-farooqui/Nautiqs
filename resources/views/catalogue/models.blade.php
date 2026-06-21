<x-app-layout :title="__('Catalogue')" :header="__('Catalogue')">

    {{-- Toast stack --}}
    @if (session('status') || $errors->any())
        <div class="fixed top-20 right-6 z-50 space-y-2 w-full max-w-sm">
            @if (session('status'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
                    x-transition.opacity
                    class="rounded-lg border border-emerald-200 bg-white shadow-lg px-4 py-3 flex items-start gap-3">
                    <i class="ri-checkbox-circle-fill text-emerald-600 text-xl shrink-0"></i>
                    <p class="flex-1 text-sm text-gray-800">{{ session('status') }}</p>
                    <button type="button" @click="show = false" class="text-gray-400 hover:text-gray-700">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
            @if ($errors->any())
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 7000)"
                    x-transition.opacity
                    class="rounded-lg border border-red-200 bg-white shadow-lg px-4 py-3 flex items-start gap-3">
                    <i class="ri-error-warning-fill text-red-600 text-xl shrink-0"></i>
                    <p class="flex-1 text-sm text-gray-800">{{ $errors->first() }}</p>
                    <button type="button" @click="show = false" class="text-gray-400 hover:text-gray-700">
                        <i class="ri-close-line"></i>
                    </button>
                </div>
            @endif
        </div>
    @endif

    {{-- Top bar: filter + add boat --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <form method="GET" action="{{ route('catalogue.models') }}" class="flex items-center gap-2">
            <div class="relative">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="q" value="{{ request('q', '') }}"
                    placeholder="{{ __('Search by brand, model, or variant…') }}"
                    class="pl-9 pr-3 py-2 rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[280px]" />
            </div>
            <select name="brand" onchange="this.form.submit()"
                class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800">
                <option value="">{{ __('All brands') }}</option>
                @foreach ($brands as $b)
                    <option value="{{ $b->_id }}" @selected($brandFilter === (string) $b->_id)>{{ $b->name }}</option>
                @endforeach
            </select>
        </form>

        <a href="{{ route('catalogue.models.create') }}"
            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-add-line"></i> {{ __('Add boat') }}
        </a>
    </div>

    @if ($rows->isEmpty())
        <x-app.empty-state
            icon="ri-sailboat-line"
            :title="__('Your catalogue is empty')"
            :message="__('Add your first boat — pick the brand, model, then add versions and options.')"
            :ctaLabel="__('Add a boat')"
            ctaHref="{{ route('catalogue.models.create') }}"
            size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Model') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('From TTC') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Updated') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($rows as $r)
                        @php
                            $v = $r['variant']; $m = $r['model']; $b = $r['brand'];
                            $ttc = $v ? $v->base_price * (1 + ($v->vat_rate ?? 20) / 100) : null;
                        @endphp
                        <tr class="hover:bg-gray-50 cursor-pointer"
                            data-href="{{ route('catalogue.models.edit', $m?->_id) }}"
                            onclick="if (!event.target.closest('a, button, form, input')) window.location = this.dataset.href">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $b?->name ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <p class="font-medium text-gray-900">{{ $m?->name ?? '—' }}{{ $m?->complement ? ' ' . $m->complement : '' }}</p>
                                @if ($v)
                                    <p class="text-xs text-gray-500">{{ $v->name }}</p>
                                @else
                                    <p class="text-xs text-amber-600 italic">{{ __('No versions yet') }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                @if ($v)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold">
                                        <i class="ri-checkbox-circle-fill"></i> {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[11px] font-semibold">
                                        <i class="ri-draft-line"></i> {{ __('Draft') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ $ttc !== null ? '€' . number_format($ttc, 0, ',', ' ') : '—' }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500">{{ $m?->updated_at?->diffForHumans() ?? '—' }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('catalogue.models.edit', $m?->_id) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                @if ($m && $m->source === 'private')
                                    <form method="POST" action="{{ route('catalogue.models.destroy', $m->_id) }}" class="inline"
                                        data-confirm="{{ __('Permanently delete «:name» and all its versions and options? This cannot be undone.', ['name' => $m->name]) }}"
                                        data-confirm-danger="1">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Delete') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-app-layout>
