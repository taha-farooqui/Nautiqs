<x-admin-layout :title="__('Equipment')" :header="__('Global equipment library')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" action="{{ route('admin.equipment.index') }}" class="flex flex-1 items-center gap-2 max-w-2xl">
            <div class="relative flex-1">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search equipment label…') }}"
                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <select name="category" onchange="this.form.submit()"
                class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[180px]">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected($category === $key)>{{ __($label) }}</option>
                @endforeach
            </select>
        </form>
        <a href="{{ route('admin.equipment.create') }}"
            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-add-line"></i> {{ __('Add equipment') }}
        </a>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($items->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-tools-line text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No equipment yet.') }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Category') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Label') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($items as $i)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3">
                                <span class="inline-block text-[11px] font-medium px-2 py-0.5 rounded-full bg-primary-50 text-primary-800 uppercase tracking-wider">
                                    {{ __($categories[$i->category] ?? $i->category) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-900">{{ $i->label }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('admin.equipment.edit', $i->_id) }}"
                                    class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                    <i class="ri-pencil-line"></i>
                                </a>
                                <form method="POST" action="{{ route('admin.equipment.destroy', $i->_id) }}" class="inline"
                                    data-confirm="{{ __('Delete this equipment item?') }}"
                                    data-confirm-text="{{ __('This will not affect equipment already on existing boats.') }}"
                                    data-confirm-danger="1">
                                    @csrf @method('DELETE')
                                    <button class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Delete') }}">
                                        <i class="ri-delete-bin-line"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-3 border-t border-gray-100">{{ $items->links() }}</div>
        @endif
    </div>
</x-admin-layout>
