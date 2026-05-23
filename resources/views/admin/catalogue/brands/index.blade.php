<x-admin-layout :title="__('Brands')" :header="__('Global brands')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">
            <i class="ri-error-warning-line"></i> {{ $errors->first() }}
        </div>
    @endif

    <div x-data="{
            open: false,
            mode: 'create',
            id: '',
            name: '',
            errored: {{ $errors->any() ? 'true' : 'false' }},
            openCreate() { this.mode = 'create'; this.id = ''; this.name = ''; this.open = true; this.$nextTick(() => this.$refs.input?.focus()); },
            openEdit(id, name) { this.mode = 'edit'; this.id = id; this.name = name; this.open = true; this.$nextTick(() => this.$refs.input?.focus()); },
            close() { this.open = false; },
         }"
         x-init="if (errored) open = true">

        <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <form method="GET" action="{{ route('admin.brands.index') }}" class="flex-1 max-w-md">
                <div class="relative">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="search" name="q" value="{{ $q }}"
                        placeholder="{{ __('Search brand name…') }}"
                        class="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800" />
                </div>
            </form>
            <button type="button" @click="openCreate()"
                class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                <i class="ri-add-line"></i> {{ __('Add brand') }}
            </button>
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
                            <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($brands as $b)
                            <tr class="hover:bg-gray-50/50">
                                <td class="px-5 py-3">
                                    <div class="font-medium text-gray-900">{{ $b->name }}</div>
                                </td>
                                <td class="px-5 py-3 text-right font-medium text-gray-900">{{ $b->_models_count }}</td>
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
                                    <button type="button" @click="openEdit('{{ $b->_id }}', @js($b->name))"
                                        class="inline-flex items-center justify-center w-8 h-8 text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg" title="{{ __('Edit') }}">
                                        <i class="ri-pencil-line"></i>
                                    </button>
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

        {{-- Add/Edit modal — single Name field, scoped to one form that
             posts to either store or update based on mode. --}}
        <template x-teleport="body">
            <div x-show="open" x-cloak x-transition.opacity
                @keydown.escape.window="close()"
                class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
                <div @click.outside="close()"
                    class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
                    <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                            <i class="ri-building-4-line"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-semibold text-gray-900" x-text="mode === 'create' ? @js(__('Add brand')) : @js(__('Edit brand'))"></h3>
                        </div>
                        <button type="button" @click="close()"
                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg">
                            <i class="ri-close-line text-xl"></i>
                        </button>
                    </div>

                    <form :action="mode === 'create' ? '{{ route('admin.brands.store') }}' : ('{{ url('admin/brands') }}/' + id)"
                        method="POST" class="p-5 space-y-4">
                        @csrf
                        <template x-if="mode === 'edit'">
                            <input type="hidden" name="_method" value="PATCH" />
                        </template>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                            <input type="text" name="name" x-model="name" x-ref="input" required maxlength="120"
                                class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                            <button type="button" @click="close()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</button>
                            <button type="submit"
                                class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                <i class="ri-save-line"></i>
                                <span x-text="mode === 'create' ? @js(__('Create brand')) : @js(__('Save changes'))"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </template>
    </div>
</x-admin-layout>
