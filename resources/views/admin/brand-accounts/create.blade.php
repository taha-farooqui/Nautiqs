<x-admin-layout :title="__('New brand account')" :header="__('New brand account')">
    <div class="mb-4 text-sm">
        <a href="{{ route('admin.brand-accounts.index') }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Brand accounts') }}</a>
    </div>
    @php $oldBrands = collect(preg_split('/[\r\n,;]+/', (string) old('brands')))->map(fn ($b) => trim($b))->filter()->values(); @endphp

    <form method="POST" action="{{ route('admin.brand-accounts.store') }}" class="max-w-3xl space-y-4"
        x-data="{ kind: @js(old('kind', 'boat')), brands: @js($oldBrands), draft: '',
                  add() { const v = this.draft.trim(); if (v && !this.brands.some(b => b.toLowerCase() === v.toLowerCase())) this.brands.push(v); this.draft = ''; } }"
        @submit="add(); $refs.brandsField.value = brands.join('\n')">
        @csrf

        <section class="rise bg-white rounded-2xl border border-gray-200 p-6 space-y-6">
            <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">1</span>
                <h3 class="font-semibold text-gray-900">{{ __('Account') }}</h3>
            </div>
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Account name') }} <span class="text-red-500">*</span></label>
                <input id="name" name="name" required maxlength="160" value="{{ old('name') }}" placeholder="{{ __('e.g. Atlantic Marine Import') }}"
                    class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700 mb-2">{{ __('Type') }} <span class="text-red-500">*</span></span>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach (['boat' => ['ri-sailboat-line', __('Boat brands'), __('Models, versions, options')], 'engine' => ['ri-settings-3-line', __('Engine brand'), __('Engines, kits, propellers')]] as $k => [$icon, $title, $text])
                        <label class="lift relative cursor-pointer rounded-xl border-2 p-4 flex items-center gap-4 transition"
                            :class="kind === '{{ $k }}' ? 'border-primary-800 bg-primary-50/60' : 'border-gray-200 hover:border-gray-300'">
                            <input type="radio" name="kind" value="{{ $k }}" x-model="kind" class="sr-only">
                            <span class="w-12 h-12 rounded-xl flex items-center justify-center transition" :class="kind === '{{ $k }}' ? 'bg-primary-800 text-white' : 'bg-gray-100 text-gray-500'"><i class="{{ $icon }} text-2xl"></i></span>
                            <span><span class="block font-semibold text-gray-900">{{ $title }}</span><span class="block text-xs text-gray-500">{{ $text }}</span></span>
                            <i class="ri-checkbox-circle-fill absolute top-3 right-3 text-primary-800 text-lg" x-show="kind === '{{ $k }}'" x-transition></i>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label for="brand-draft" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Brands it manages') }} <span class="text-red-500">*</span></label>
                <div class="rounded-lg border border-gray-300 focus-within:border-primary-800 focus-within:ring-1 focus-within:ring-primary-800 p-2 flex flex-wrap gap-1.5 bg-white">
                    <template x-for="(b, i) in brands" :key="b">
                        <span class="pop inline-flex items-center gap-1 rounded-lg bg-primary-800 text-white text-sm pl-2.5 pr-1 py-1">
                            <span x-text="b"></span>
                            <button type="button" @click="brands.splice(i, 1)" class="w-5 h-5 rounded hover:bg-white/20 inline-flex items-center justify-center" aria-label="{{ __('Remove') }}"><i class="ri-close-line text-sm"></i></button>
                        </span>
                    </template>
                    <input id="brand-draft" x-model="draft" @keydown.enter.prevent="add()" @keydown.comma.prevent="add()" @blur="add()"
                        @keydown.backspace="if (draft === '' && brands.length) brands.pop()"
                        :placeholder="brands.length ? '{{ __('Add another…') }}' : '{{ __('Type a brand and press Enter') }}'"
                        class="flex-1 min-w-[180px] border-0 p-1 text-sm focus:ring-0">
                </div>
                <input type="hidden" name="brands" x-ref="brandsField" :value="brands.join('\n')">
                <p class="text-xs text-gray-500 mt-1">{{ __('One brand for a manufacturer, several for an importer. A brand can belong to one account only.') }}</p>
                <x-input-error :messages="$errors->get('brands')" class="mt-1" />
            </div>
        </section>

        <section class="rise bg-white rounded-2xl border border-gray-200 p-6 space-y-4" style="--d: 1">
            <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-primary-800 text-white text-xs font-bold flex items-center justify-center">2</span>
                <h3 class="font-semibold text-gray-900">{{ __('Brand manager') }}</h3>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="manager_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }} <span class="text-red-500">*</span></label>
                    <div class="relative"><i class="ri-user-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input id="manager_name" name="manager_name" required maxlength="120" value="{{ old('manager_name') }}" class="w-full pl-9 rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" /></div>
                    <x-input-error :messages="$errors->get('manager_name')" class="mt-1" />
                </div>
                <div>
                    <label for="manager_email" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }} <span class="text-red-500">*</span></label>
                    <div class="relative"><i class="ri-mail-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input id="manager_email" name="manager_email" type="email" required maxlength="200" value="{{ old('manager_email') }}" class="w-full pl-9 rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" /></div>
                    <x-input-error :messages="$errors->get('manager_email')" class="mt-1" />
                </div>
            </div>
            <p class="text-sm text-gray-500 flex items-center gap-2"><i class="ri-mail-send-line text-primary-800"></i> {{ __('They receive an email to set a password, then land on their brand portal.') }}</p>
        </section>

        <div class="flex items-center justify-end gap-2">
            <a href="{{ route('admin.brand-accounts.index') }}" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</a>
            <button :disabled="brands.length === 0 && draft.trim() === ''" class="inline-flex items-center gap-1.5 px-4 py-2.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 disabled:opacity-40 text-white rounded-lg">
                <i class="ri-send-plane-fill"></i> {{ __('Create & send invitation') }}
            </button>
        </div>
    </form>
</x-admin-layout>
