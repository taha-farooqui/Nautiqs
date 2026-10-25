<x-app-layout :title="$account->name" :header="__('Invitation from :brand', ['brand' => $account->name])">
    @include('brand.partials.flash')
    @php $initials = mb_strtoupper(collect(preg_split('/\s+/', trim($account->name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')); @endphp

    <div class="mb-4 text-sm"><a href="{{ route('partner-brands.index') }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Partner brands') }}</a></div>

    <div class="max-w-3xl space-y-4">
        {{-- Hero --}}
        <section class="rise relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-900 via-primary-800 to-primary-600 text-white p-6">
            <i class="{{ $account->isEngine() ? 'ri-settings-3-line' : 'ri-sailboat-line' }} absolute -right-4 -bottom-6 text-[9rem] text-white/10"></i>
            <div class="relative flex items-start gap-4">
                <span class="w-16 h-16 rounded-2xl bg-white text-primary-900 text-xl font-bold flex items-center justify-center shrink-0">{{ $initials }}</span>
                <div>
                    <p class="text-sm text-white/70">{{ __('Sent by :name on :date', ['name' => $access->invited_by_name ?: $account->name, 'date' => $access->invited_at?->format('d/m/Y')]) }}</p>
                    <h2 class="text-2xl font-bold">{{ __(':brand wants to share its catalogue with you', ['brand' => $account->name]) }}</h2>
                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @foreach ($brandNames as $n) <span class="text-sm rounded-lg bg-white/15 px-2.5 py-1">{{ $n }}</span> @endforeach
                    </div>
                </div>
            </div>
            @if ($access->message)
                <blockquote class="relative mt-5 rounded-xl bg-white/10 px-4 py-3 text-sm italic"><i class="ri-double-quotes-l text-white/50"></i> {{ $access->message }}</blockquote>
            @endif
        </section>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <section class="rise bg-white rounded-2xl border border-gray-200 p-5" style="--d: 1">
                <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2"><span class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center"><i class="ri-gift-line"></i></span> {{ __('What you get') }}</h3>
                <ul class="space-y-2.5 text-sm text-gray-700">
                    <li class="flex gap-2"><i class="ri-checkbox-circle-fill text-emerald-500 text-base"></i> {{ __('Official public prices, always up to date') }}</li>
                    <li class="flex gap-2"><i class="ri-checkbox-circle-fill text-emerald-500 text-base"></i> <span>{{ __('Your purchase prices, at your level') }} <x-brand.level-badge :n="$access->level" :name="$levelName" /></span></li>
                    <li class="flex gap-2"><i class="ri-checkbox-circle-fill text-emerald-500 text-base"></i> {{ __('Ready to quote immediately') }}</li>
                </ul>
            </section>
            <section class="rise bg-white rounded-2xl border border-gray-200 p-5" style="--d: 2">
                <h3 class="font-semibold text-gray-900 mb-3 flex items-center gap-2"><span class="w-8 h-8 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center"><i class="ri-eye-line"></i></span> {{ __('What :brand will see', ['brand' => $account->name]) }}</h3>
                <ul class="space-y-2.5 text-sm text-gray-700">
                    <li class="flex gap-2"><i class="ri-bar-chart-2-line text-sky-600 text-base"></i> {{ __('Quotes made with its products: model, options, amounts, discounts, status') }}</li>
                    <li class="flex gap-2"><i class="ri-store-2-line text-sky-600 text-base"></i> {{ __('Your dealership and the salesperson who made them') }}</li>
                    <li class="flex gap-2"><i class="ri-lock-fill text-gray-400 text-base"></i> <span><strong>{{ __('Never') }}</strong> {{ __('your clients\' name, email, phone or address') }}</span></li>
                    <li class="flex gap-2"><i class="ri-lock-fill text-gray-400 text-base"></i> <span><strong>{{ __('Never') }}</strong> {{ __('your margins or special prices') }}</span></li>
                </ul>
            </section>
        </div>

        @if ($access->status === \App\Models\BrandAccess::STATUS_PENDING)
            @if (auth()->user()->isTenantAdmin())
                <form method="POST" action="{{ route('partner-brands.accept', $access->_id) }}" x-data="{ dup: 'archive', ok: false }"
                    class="rise bg-white rounded-2xl border border-gray-200 p-5 space-y-5" style="--d: 3">
                    @csrf
                    @if ($duplicates)
                        <div>
                            <h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="ri-error-warning-line text-amber-500"></i> {{ __('You already have this brand in your catalogue') }}</h3>
                            <p class="text-sm text-gray-500 mt-0.5 mb-3">
                                @foreach ($duplicates as $d) {{ $d['name'] }} ({{ trans_choice('{1}:count item|[2,*]:count items', $d['count'], ['count' => $d['count']]) }})@if (! $loop->last), @endif @endforeach
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="cursor-pointer rounded-xl border-2 p-4 transition" :class="dup === 'archive' ? 'border-primary-800 bg-primary-50/50' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" name="duplicates" value="archive" x-model="dup" class="sr-only">
                                    <div class="flex items-center justify-between"><span class="font-semibold text-gray-900"><i class="ri-archive-line"></i> {{ __('Archive my manual copy') }}</span>
                                        <span class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 rounded-full px-2 py-0.5">{{ __('recommended') }}</span></div>
                                    <p class="text-xs text-gray-500 mt-1">{{ __('hidden from new quotes, old quotes untouched, can be restored.') }}</p>
                                </label>
                                <label class="cursor-pointer rounded-xl border-2 p-4 transition" :class="dup === 'keep' ? 'border-primary-800 bg-primary-50/50' : 'border-gray-200 hover:border-gray-300'">
                                    <input type="radio" name="duplicates" value="keep" x-model="dup" class="sr-only">
                                    <span class="font-semibold text-gray-900"><i class="ri-file-copy-line"></i> {{ __('Keep both') }}</span>
                                    <p class="text-xs text-gray-500 mt-1">{{ __('Your copy stays next to the brand\'s catalogue.') }}</p>
                                </label>
                            </div>
                        </div>
                    @endif

                    <label class="flex items-start gap-3 rounded-xl bg-gray-50 p-4 text-sm cursor-pointer">
                        <input type="checkbox" name="consent" value="1" required x-model="ok" class="mt-0.5 w-5 h-5 rounded border-gray-300 text-primary-800 focus:ring-primary-800">
                        <span class="text-gray-700">{{ __('I agree that :brand can see statistics on the quotes made with its products, as described above.', ['brand' => $account->name]) }}</span>
                    </label>
                    <x-input-error :messages="$errors->get('consent')" />

                    <div class="flex flex-wrap justify-end gap-2">
                        <button type="submit" form="decline-form" class="px-4 py-2.5 text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50 rounded-lg">{{ __('Decline') }}</button>
                        <button :disabled="!ok" class="inline-flex items-center gap-1.5 px-5 py-2.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 disabled:opacity-40 disabled:cursor-not-allowed text-white rounded-lg transition">
                            <i class="ri-check-double-line"></i> {{ __('Accept & import catalogue') }}</button>
                    </div>
                </form>
                <form id="decline-form" method="POST" action="{{ route('partner-brands.decline', $access->_id) }}" data-confirm="{{ __('Decline this invitation?') }}">@csrf</form>
            @else
                <p class="rounded-xl bg-gray-50 border border-gray-200 px-4 py-3 text-sm text-gray-700 flex items-center gap-2"><i class="ri-shield-user-line text-gray-400"></i> {{ __('An admin of your dealership needs to accept this invitation.') }}</p>
            @endif
        @endif
    </div>
</x-app-layout>
