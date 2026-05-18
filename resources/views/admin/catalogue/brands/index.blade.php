<x-admin-layout :title="__('Brands')" :header="__('Global brands')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" action="{{ route('admin.brands.index') }}" class="flex-1 max-w-md">
            <div class="relative">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search brand name…') }}"
                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
        </form>
        <a href="{{ route('admin.brands.create') }}"
            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-add-line"></i> {{ __('Add brand') }}
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($brands->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-building-4-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No brands.') }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Brand') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Models') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Order') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($brands as $b)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <div class="font-medium text-gray-900">{{ $b->name }}</div>
                                @if ($b->description)
                                    <div class="text-[11px] text-gray-500 truncate max-w-md">{{ $b->description }}</div>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right font-medium text-gray-900">{{ $b->_models_count }}</td>
                            <td class="px-5 py-3 text-right text-xs text-gray-500">{{ $b->display_order ?? '—' }}</td>
                            <td class="px-5 py-3">
                                @if ($b->is_active)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700">
                                        <i class="ri-checkbox-circle-line"></i> {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-gray-100 text-gray-500">
                                        <i class="ri-pause-circle-line"></i> {{ __('Inactive') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.brands.edit', $b->_id) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.brands.toggle', $b->_id) }}" class="inline"
                                    data-confirm="{{ $b->is_active ? __('Deactivate :name?', ['name' => $b->name]) : __('Activate :name?', ['name' => $b->name]) }}">
                                    @csrf
                                    <button class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:bg-gray-100 rounded-lg" title="{{ $b->is_active ? __('Deactivate') : __('Activate') }}">
                                        <i class="{{ $b->is_active ? 'ri-pause-circle-line' : 'ri-play-circle-line' }}"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-3 border-t border-gray-100">{{ $brands->links() }}</div>
        @endif
    </div>
</x-admin-layout>
