<x-admin-layout :title="__('Options')" :header="__('Global options')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif

    <div class="flex items-center gap-1 mb-4 bg-white p-1 rounded-xl border border-gray-200 w-fit">
        @foreach ([
            'active'   => __('Active') . ' (' . $tabCounts['active'] . ')',
            'archived' => __('Archived') . ' (' . $tabCounts['archived'] . ')',
        ] as $tab => $label)
            <a href="{{ route('admin.options.index', ['status' => $tab, 'q' => $q, 'model' => $modelId, 'category' => $category]) }}"
                class="px-3 py-1.5 text-sm font-medium rounded-lg transition
                    {{ $status === $tab ? 'bg-primary-800 text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <form method="GET" action="{{ route('admin.options.index') }}" class="flex flex-1 items-center gap-2 max-w-3xl">
            <input type="hidden" name="status" value="{{ $status }}" />
            <div class="relative flex-1">
                <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="search" name="q" value="{{ $q }}"
                    placeholder="{{ __('Search option label…') }}"
                    class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800" />
            </div>
            <select name="model" onchange="this.form.submit()"
                class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[180px]">
                <option value="">{{ __('All models') }}</option>
                @foreach ($allModels as $m)
                    <option value="{{ $m->_id }}" @selected($modelId === (string) $m->_id)>{{ $m->name }}</option>
                @endforeach
            </select>
            <select name="category" onchange="this.form.submit()"
                class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800 min-w-[160px]">
                <option value="">{{ __('All categories') }}</option>
                @foreach ($allCategories as $c)
                    <option value="{{ $c }}" @selected($category === $c)>{{ $c }}</option>
                @endforeach
            </select>
        </form>
        @if ($status === 'active')
            <a href="{{ route('admin.options.create', $modelId ? ['model' => $modelId] : []) }}"
                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-add-line"></i> {{ __('Add option') }}
            </a>
        @endif
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        @if ($options->isEmpty())
            <div class="px-6 py-12 text-center">
                <i class="ri-list-check-2 text-4xl text-gray-300"></i>
                <p class="text-sm text-gray-700 mt-3 font-medium">{{ __('No options.') }}</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Model') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Category') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Label') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Price') }}</th>
                        <th class="px-5 py-3 font-semibold text-right">{{ __('Cost') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($options as $o)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-5 py-3 text-gray-700">{{ $o->_model_name }}</td>
                            <td class="px-5 py-3">
                                <span class="text-[11px] font-medium px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">{{ $o->category }}</span>
                            </td>
                            <td class="px-5 py-3 text-gray-900">{{ $o->label }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-gray-900">{{ number_format($o->price, 2, ',', ' ') }} {{ $o->currency === 'USD' ? '$' : '€' }}</td>
                            <td class="px-5 py-3 text-right text-gray-700">{{ $o->cost ? number_format($o->cost, 2, ',', ' ') . ' ' . ($o->currency === 'USD' ? '$' : '€') : '—' }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                @if ($status === 'archived')
                                    <form method="POST" action="{{ route('admin.options.archive', $o->_id) }}" class="inline"
                                        data-confirm="{{ __('Restore :name?', ['name' => $o->label]) }}">
                                        @csrf
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-emerald-600 hover:bg-emerald-50 rounded-lg" title="{{ __('Restore') }}">
                                            <i class="ri-arrow-go-back-line"></i>
                                        </button>
                                    </form>
                                @else
                                    <a href="{{ route('admin.options.edit', $o->_id) }}"
                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                        <i class="ri-pencil-line"></i>
                                    </a>
                                    <form method="POST" action="{{ route('admin.options.archive', $o->_id) }}" class="inline"
                                        data-confirm="{{ __('Archive :name?', ['name' => $o->label]) }}"
                                        data-confirm-danger="1">
                                        @csrf
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:bg-gray-100 rounded-lg" title="{{ __('Archive') }}">
                                            <i class="ri-archive-line"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="px-5 py-3 border-t border-gray-100">{{ $options->links() }}</div>
        @endif
    </div>
</x-admin-layout>
