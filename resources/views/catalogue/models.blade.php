<x-app-layout title="Models & variants" header="Models & variants">

    {{-- Toast stack (top-right, auto-dismiss). Lives outside the main flow
         so it floats over the table even when scrolled. --}}
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

    {{-- Top bar: tabs + filter + new buttons --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="inline-flex items-center bg-gray-100 rounded-lg p-1 text-sm w-fit">
            <a href="{{ route('catalogue.models', ['tab' => 'workspace']) }}"
                class="px-4 py-1.5 rounded-md font-medium transition {{ $tab === 'workspace' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                <i class="ri-store-2-line"></i> My workspace
                <span class="ml-1 text-xs text-gray-400">({{ $workspaceCount }})</span>
            </a>
            <a href="{{ route('catalogue.models', ['tab' => 'available']) }}"
                class="px-4 py-1.5 rounded-md font-medium transition {{ $tab === 'available' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                <i class="ri-global-line"></i> Available globally
            </a>
        </div>

        <div class="flex items-center gap-2">
            <form method="GET" action="{{ route('catalogue.models') }}" class="shrink-0">
                <input type="hidden" name="tab" value="{{ $tab }}" />
                <select name="brand" onchange="this.form.submit()"
                    class="rounded-lg border-gray-200 text-sm focus:border-primary-800 focus:ring-primary-800">
                    <option value="">All brands</option>
                    @foreach ($brands as $b)
                        <option value="{{ $b->_id }}" @selected($brandFilter === (string) $b->_id)>{{ $b->name }}</option>
                    @endforeach
                </select>
            </form>

            @if ($tab === 'workspace')
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                        <i class="ri-add-line"></i> Add
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity
                        class="absolute right-0 top-full mt-2 w-44 z-20 bg-white rounded-lg border border-gray-200 shadow-lg py-1">
                        <a href="{{ route('catalogue.models.create') }}"
                            class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="ri-sailboat-line text-gray-400"></i> Add model
                        </a>
                        <a href="{{ route('catalogue.variants.create') }}"
                            class="flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="ri-list-check-2 text-gray-400"></i> Add variant
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($rows->isEmpty())
        @if ($tab === 'workspace')
            <x-app.empty-state
                icon="ri-store-2-line"
                title="Your workspace is empty"
                message="Switch to «Available globally» to pick variants from the platform catalogue, or click «Add variant» to create your own."
                size="lg" />
        @else
            <x-app.empty-state
                icon="ri-sailboat-line"
                title="No global models yet"
                message="The platform team hasn't published any boat models."
                size="lg" />
        @endif
    @else

        {{-- ============================== WORKSPACE TAB --}}
        @if ($tab === 'workspace')
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Brand</th>
                            <th class="px-5 py-3 font-semibold">Model</th>
                            <th class="px-5 py-3 font-semibold">Variant</th>
                            <th class="px-5 py-3 font-semibold">Source</th>
                            <th class="px-5 py-3 font-semibold text-right">Base price</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($rows as $r)
                            @php $v = $r['variant']; $m = $r['model']; $b = $r['brand']; @endphp
                            <tr class="hover:bg-gray-50 {{ $v->is_active ? '' : 'bg-gray-50/40' }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-7 h-7 rounded-md bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                                            <i class="ri-building-4-line text-sm"></i>
                                        </span>
                                        <span class="font-medium text-gray-900">{{ $b?->name ?? '—' }}</span>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900">{{ $m?->name ?? '—' }}</p>
                                    <p class="text-xs text-gray-500 font-mono">{{ $m?->code }}</p>
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-medium text-gray-900">{{ $v->name }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $v->currency ?? 'EUR' }}
                                        @if (!empty($v->included_equipment))
                                            · {{ count($v->included_equipment) }} equipment
                                        @endif
                                    </p>
                                </td>
                                <td class="px-5 py-3">
                                    @if (($v->source ?? null) === 'private')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">Private</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">From global</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    <p class="font-semibold text-gray-900">€{{ number_format($v->base_price, 0, ',', ' ') }}</p>
                                    <p class="text-[11px] text-gray-400">excl. VAT</p>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($v->is_active)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Hidden
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <form method="POST" action="{{ route('catalogue.variants.toggle', $v->_id) }}">@csrf
                                            <button title="{{ $v->is_active ? 'Hide from quote builder' : 'Show in quote builder' }}"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium {{ $v->is_active ? 'bg-amber-50 hover:bg-amber-100 text-amber-800' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-800' }} rounded-lg">
                                                <i class="ri-{{ $v->is_active ? 'eye-off' : 'eye' }}-line"></i>
                                                {{ $v->is_active ? 'Hide' : 'Show' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('catalogue.models.edit', $m?->_id) }}"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg">
                                            <i class="ri-pencil-line"></i> Edit
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- ============================== AVAILABLE TAB --}}
        @if ($tab === 'available')
            @php
                $selectableCount = $rows->reject(fn ($r) => in_array((string) $r['variant']->_id, $activatedVariantIds, true))->count();
            @endphp
            <form method="POST" action="{{ route('catalogue.variants.activate-bulk') }}"
                x-data="{
                    selected: [],
                    toggleAll(e) {
                        let ids = [...document.querySelectorAll('[data-variant-id]:not([data-activated])')].map(el => el.dataset.variantId);
                        this.selected = e.target.checked ? ids : [];
                    }
                }">
                @csrf

                {{-- Sticky bulk action bar --}}
                <div class="sticky top-0 z-10 mb-4 rounded-2xl border border-gray-200 bg-white px-4 py-3 flex items-center justify-between"
                    x-show="selected.length > 0" x-cloak x-transition.opacity>
                    <p class="text-sm font-medium text-gray-900">
                        <span x-text="selected.length"></span> variant<span x-show="selected.length !== 1">s</span> selected
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="selected = []"
                            class="px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-900">Clear</button>
                        <button type="submit"
                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                            <i class="ri-add-line"></i> Add to my workspace
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                            <tr>
                                <th class="px-5 py-3 w-10">
                                    <input type="checkbox"
                                        @change="toggleAll($event)"
                                        @if ($selectableCount === 0) disabled title="All variants are already in your workspace" @endif
                                        class="rounded border-gray-300 text-primary-800 focus:ring-primary-800 {{ $selectableCount === 0 ? 'cursor-not-allowed opacity-50' : '' }}" />
                                </th>
                                <th class="px-5 py-3 font-semibold">Brand</th>
                                <th class="px-5 py-3 font-semibold">Model</th>
                                <th class="px-5 py-3 font-semibold">Variant</th>
                                <th class="px-5 py-3 font-semibold text-right">Base price</th>
                                <th class="px-5 py-3 font-semibold">Status</th>
                                <th class="px-5 py-3 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $r)
                                @php
                                    $v = $r['variant']; $m = $r['model']; $b = $r['brand'];
                                    $isActivated = in_array((string) $v->_id, $activatedVariantIds, true);
                                @endphp
                                <tr class="hover:bg-gray-50 {{ $isActivated ? 'bg-emerald-50/30' : '' }}"
                                    data-variant-id="{{ $v->_id }}"
                                    @if ($isActivated) data-activated="1" @endif>
                                    <td class="px-5 py-3">
                                        @if (! $isActivated)
                                            <input type="checkbox" name="variant_ids[]" value="{{ $v->_id }}"
                                                x-model="selected"
                                                class="rounded border-gray-300 text-primary-800 focus:ring-primary-800" />
                                        @else
                                            <input type="checkbox" checked disabled
                                                title="Already in your workspace"
                                                class="rounded border-gray-300 text-emerald-600 cursor-not-allowed" />
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="font-medium text-gray-900">{{ $b?->name ?? '—' }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-gray-900">{{ $m?->name ?? '—' }}</p>
                                        <p class="text-xs text-gray-500 font-mono">{{ $m?->code }}</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-gray-900">{{ $v->name }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $v->currency ?? 'EUR' }}
                                            @if (!empty($v->included_equipment))
                                                · {{ count($v->included_equipment) }} equipment
                                            @endif
                                        </p>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <p class="font-semibold text-gray-900">€{{ number_format($v->base_price, 0, ',', ' ') }}</p>
                                        <p class="text-[11px] text-gray-400">excl. VAT</p>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($isActivated)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                                <i class="ri-check-line"></i> In your workspace
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">Not activated</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        @if (! $isActivated)
                                            <button type="button"
                                                @click="if (!selected.includes('{{ $v->_id }}')) selected.push('{{ $v->_id }}')"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-primary-50 hover:bg-primary-100 text-primary-800 rounded-lg">
                                                <i class="ri-add-line"></i> Add
                                            </button>
                                        @else
                                            <button type="button" disabled
                                                title="Already in your workspace"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-gray-100 text-gray-400 rounded-lg cursor-not-allowed">
                                                <i class="ri-check-line"></i> Added
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
        @endif
    @endif
</x-app-layout>
