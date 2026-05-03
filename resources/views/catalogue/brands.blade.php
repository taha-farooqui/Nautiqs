<x-app-layout title="Brands" header="Brands">
<div x-data="{ brandModal: {{ $errors->any() && old('name') !== null ? 'true' : 'false' }} }">
    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('status') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    {{-- Tabs + actions --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="inline-flex items-center bg-gray-100 rounded-lg p-1 text-sm w-fit">
            <a href="{{ route('catalogue.brands', ['tab' => 'workspace']) }}"
                class="px-4 py-1.5 rounded-md font-medium transition {{ $tab === 'workspace' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                <i class="ri-store-2-line"></i> My workspace
                <span class="ml-1 text-xs text-gray-400">({{ $companyBrands->count() }})</span>
            </a>
            <a href="{{ route('catalogue.brands', ['tab' => 'available']) }}"
                class="px-4 py-1.5 rounded-md font-medium transition {{ $tab === 'available' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                <i class="ri-global-line"></i> Available globally
                <span class="ml-1 text-xs text-gray-400">({{ $globalBrands->count() }})</span>
            </a>
        </div>

        <button type="button" @click="brandModal = true"
            class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
            <i class="ri-add-line"></i> New private brand
        </button>
    </div>

    {{-- ============================================== WORKSPACE TAB --}}
    @if ($tab === 'workspace')
        @if ($companyBrands->isEmpty())
            <x-app.empty-state
                icon="ri-store-2-line"
                title="No brands in your workspace yet"
                message="Switch to «Available globally» to activate brands, or create a private brand."
                size="lg" />
        @else
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Brand</th>
                            <th class="px-5 py-3 font-semibold">Source</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Activated</th>
                            <th class="px-5 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($companyBrands as $brand)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                                            <i class="ri-{{ $brand->source === 'private' ? 'shield-user-line' : 'building-4-line' }}"></i>
                                        </span>
                                        <div class="min-w-0">
                                            <p class="font-semibold text-gray-900 truncate">{{ $brand->name }}</p>
                                            @if ($brand->description)
                                                <p class="text-xs text-gray-500 truncate max-w-md">{{ $brand->description }}</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-5 py-3">
                                    @if ($brand->source === 'private')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 text-xs font-semibold">Private</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold">From global</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @if ($brand->is_active)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 text-xs font-semibold">
                                            <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Deactivated
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-500 text-xs">
                                    {{ $brand->activated_at?->diffForHumans() ?? '—' }}
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('catalogue.models', ['brand' => $brand->_id]) }}"
                                            class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg">
                                            <i class="ri-list-check-2"></i> Models
                                        </a>

                                        @if ($brand->source === 'global')
                                            @if ($brand->is_active)
                                                <form method="POST" action="{{ route('catalogue.brands.deactivate', $brand->_id) }}">@csrf
                                                    <button class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg">
                                                        <i class="ri-eye-off-line"></i> Deactivate
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('catalogue.brands.reactivate', $brand->_id) }}">@csrf
                                                    <button class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-emerald-50 hover:bg-emerald-100 text-emerald-800 rounded-lg">
                                                        <i class="ri-eye-line"></i> Re-activate
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            <form method="POST" action="{{ route('catalogue.brands.private.destroy', $brand->_id) }}"
                                                onsubmit="return confirm('Delete this private brand and all its models, variants, and options? This cannot be undone.');">
                                                @csrf @method('DELETE')
                                                <button class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg">
                                                    <i class="ri-delete-bin-line"></i> Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif

    {{-- ============================================== AVAILABLE TAB --}}
    @if ($tab === 'available')
        @if ($globalBrands->isEmpty())
            <x-app.empty-state
                icon="ri-building-4-line"
                title="No brands published yet"
                message="The platform team hasn't published any boat brands."
                size="lg" />
        @else
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Brand</th>
                            <th class="px-5 py-3 font-semibold">Description</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($globalBrands as $brand)
                            @php $isActivated = in_array((string) $brand->_id, $activatedGlobalIds, true); @endphp
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="w-9 h-9 rounded-lg bg-gray-50 text-gray-700 flex items-center justify-center shrink-0">
                                            <i class="ri-building-4-line"></i>
                                        </span>
                                        <p class="font-semibold text-gray-900">{{ $brand->name }}</p>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-gray-600">
                                    @if ($brand->description)
                                        <p class="line-clamp-2 max-w-md">{{ $brand->description }}</p>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
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
                                    @if ($isActivated)
                                        <a href="{{ route('catalogue.models', ['tab' => 'workspace']) }}"
                                            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-800 rounded-lg">
                                            <i class="ri-arrow-right-line"></i> Open
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('catalogue.brands.activate', $brand->_id) }}">@csrf
                                            <button class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                                                <i class="ri-add-line"></i> Activate
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
    @endif

    {{-- ============================================== CREATE BRAND MODAL --}}
    <div x-show="brandModal" x-transition.opacity x-cloak
        class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4"
        @keydown.escape.window="brandModal = false">
        <form method="POST" action="{{ route('catalogue.brands.private.store') }}"
            @click.outside="brandModal = false"
            class="bg-white rounded-2xl w-full max-w-md overflow-hidden shadow-2xl">
            @csrf
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                    <i class="ri-shield-user-line text-xl"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900">Create private brand</h3>
                    <p class="text-xs text-gray-500">Sell a brand the platform doesn't list yet? Create it here. Only you'll see it.</p>
                </div>
                <button type="button" @click="brandModal = false"
                    class="w-9 h-9 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                    <i class="ri-close-line text-xl"></i>
                </button>
            </div>

            <div class="p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Brand name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required autofocus x-ref="brandNameInput"
                        value="{{ old('name') }}"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        placeholder="Optional — what makes this brand stand out for your dealership?"
                        class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800">{{ old('description') }}</textarea>
                </div>
            </div>

            <div class="px-5 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-end gap-2">
                <button type="button" @click="brandModal = false"
                    class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                <button type="submit"
                    class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">
                    <i class="ri-save-line"></i> Create brand
                </button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
