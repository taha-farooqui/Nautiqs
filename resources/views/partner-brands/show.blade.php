<x-app-layout :title="$account->name" :header="__('Invitation from :brand', ['brand' => $account->name])">
    @include('brand.partials.flash')

    <div class="mb-4 text-sm"><a href="{{ route('partner-brands.index') }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Partner brands') }}</a></div>

    <div class="max-w-3xl space-y-4">
        <section class="bg-white rounded-2xl border border-gray-200 p-6 space-y-5">
            <div>
                <h2 class="text-xl font-bold text-gray-900">{{ __('Invitation from :brand', ['brand' => $account->name]) }}</h2>
                <p class="text-sm text-gray-500">{{ __('Sent by :name on :date', ['name' => $access->invited_by_name ?: $account->name, 'date' => $access->invited_at?->format('d/m/Y')]) }}</p>
            </div>

            @if ($access->message)
                <blockquote class="rounded-xl border border-gray-200 px-4 py-3 text-sm italic text-gray-700">“{{ $access->message }}”</blockquote>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="rounded-xl bg-gray-50 p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('What you get') }}</h3>
                    <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                        <li>{{ implode(', ', $brandNames) }}</li>
                        <li>{{ __('Official public prices, always up to date') }}</li>
                        <li>{{ __('Your purchase prices at level :level', ['level' => $levelName]) }}</li>
                        <li>{{ __('Ready to quote immediately') }}</li>
                    </ul>
                </div>
                <div class="rounded-xl bg-gray-50 p-4">
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('What :brand will see', ['brand' => $account->name]) }}</h3>
                    <ul class="list-disc list-inside text-sm text-gray-700 space-y-1">
                        <li>{{ __('Quotes made with its products: model, options, amounts, discounts, status') }}</li>
                        <li>{{ __('Your dealership and the salesperson who made them') }}</li>
                        <li><strong>{{ __('Never') }}</strong> {{ __('your clients\' name, email, phone or address') }}</li>
                        <li><strong>{{ __('Never') }}</strong> {{ __('your margins or special prices') }}</li>
                    </ul>
                </div>
            </div>

            @if ($access->status === \App\Models\BrandAccess::STATUS_PENDING)
                @if (auth()->user()->isTenantAdmin())
                    <form method="POST" action="{{ route('partner-brands.accept', $access->_id) }}" class="space-y-4">
                        @csrf
                        @if ($duplicates)
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 space-y-2">
                                <h3 class="font-semibold text-gray-900">{{ __('You already have this brand in your catalogue') }}</h3>
                                <p class="text-sm text-gray-700">
                                    @foreach ($duplicates as $d) {{ $d['name'] }} ({{ trans_choice('{1}:count item|[2,*]:count items', $d['count'], ['count' => $d['count']]) }})@if (! $loop->last), @endif @endforeach
                                </p>
                                <label class="flex items-start gap-2 text-sm"><input type="radio" name="duplicates" value="archive" checked class="mt-1 text-primary-800 focus:ring-primary-800">
                                    <span><strong>{{ __('Archive my manual copy') }}</strong> ({{ __('recommended') }}) — {{ __('hidden from new quotes, old quotes untouched, can be restored.') }}</span></label>
                                <label class="flex items-start gap-2 text-sm"><input type="radio" name="duplicates" value="keep" class="mt-1 text-primary-800 focus:ring-primary-800">
                                    <span>{{ __('Keep both') }}</span></label>
                            </div>
                        @endif

                        <label class="flex items-start gap-3 text-sm">
                            <input type="checkbox" name="consent" value="1" required class="mt-0.5 w-5 h-5 rounded border-gray-300 text-primary-800 focus:ring-primary-800">
                            <span>{{ __('I agree that :brand can see statistics on the quotes made with its products, as described above.', ['brand' => $account->name]) }}</span>
                        </label>
                        <x-input-error :messages="$errors->get('consent')" />

                        <div class="flex flex-wrap justify-end gap-2">
                            <button type="submit" form="decline-form" class="px-4 py-2 text-sm font-medium border border-gray-300 hover:bg-gray-50 rounded-lg">{{ __('Decline') }}</button>
                            <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-check-line"></i> {{ __('Accept & import catalogue') }}</button>
                        </div>
                    </form>
                    <form id="decline-form" method="POST" action="{{ route('partner-brands.decline', $access->_id) }}" data-confirm="{{ __('Decline this invitation?') }}">@csrf</form>
                @else
                    <p class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">{{ __('An admin of your dealership needs to accept this invitation.') }}</p>
                @endif
            @endif
        </section>
    </div>
</x-app-layout>
