<x-app-layout :title="__('Partner brands')" :header="__('Partner brands')">
    @include('brand.partials.flash')
    @php
        $pending = $accesses->reject(fn ($a) => $a->isActive());
        $connected = $accesses->filter(fn ($a) => $a->isActive());
        $initials = fn ($name) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode(''));
    @endphp

    <p class="mb-5 text-sm text-gray-600 max-w-3xl">{{ __('Brands that share their official catalogue with you: public prices stay up to date and your purchase prices are filled in from your level.') }}</p>

    @if ($accesses->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200">
            <x-app.empty-state icon="ri-links-line" :title="__('No partner brands yet')" size="lg"
                :message="__('When a brand or importer invites you, the invitation appears here.')" />
        </div>
    @endif

    {{-- Invitations waiting for an answer --}}
    @if ($pending->isNotEmpty())
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">{{ __('Waiting for you') }}</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
            @foreach ($pending as $a)
                @php $acc = $accounts[(string) $a->brand_account_id] ?? null; @endphp
                <div class="rise relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-900 to-primary-700 text-white p-5" style="--d: {{ $loop->index }}">
                    <i class="ri-mail-star-line absolute -right-3 -bottom-4 text-8xl text-white/10"></i>
                    <div class="relative flex items-start gap-4">
                        <span class="w-12 h-12 rounded-xl bg-white text-primary-900 font-bold flex items-center justify-center shrink-0">{{ $initials($acc?->name) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-300 animate-pulse"></span>{{ __('New invitation') }}</div>
                            <div class="text-lg font-bold">{{ $acc?->name ?? '—' }}</div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @foreach ($invitations->brandNames($a) as $n) <span class="text-xs rounded-md bg-white/15 px-2 py-0.5">{{ $n }}</span> @endforeach
                            </div>
                            <a href="{{ route('partner-brands.show', $a->_id) }}" class="mt-4 inline-flex items-center gap-1.5 rounded-lg bg-white text-primary-900 px-3.5 py-2 text-sm font-semibold hover:bg-primary-50 transition">
                                {{ __('Review the invitation') }} <i class="ri-arrow-right-line"></i></a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Connected brands --}}
    @if ($connected->isNotEmpty())
        <h3 class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-3">{{ __('Connected') }}</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach ($connected as $a)
                @php $acc = $accounts[(string) $a->brand_account_id] ?? null; @endphp
                <div class="rise lift bg-white rounded-2xl border border-gray-200 p-5 flex flex-col" style="--d: {{ $loop->index }}">
                    <div class="flex items-start gap-3">
                        <span class="w-12 h-12 rounded-xl bg-primary-50 text-primary-800 font-bold flex items-center justify-center shrink-0">{{ $initials($acc?->name) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="font-semibold text-gray-900 truncate">{{ $acc?->name ?? '—' }}</div>
                            <div class="inline-flex items-center gap-1.5 text-xs text-emerald-700"><span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ __('Connected since :date', ['date' => $a->responded_at?->format('d/m/Y')]) }}</div>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach ($invitations->brandNames($a) as $n) <span class="text-xs rounded-md bg-gray-100 text-gray-700 px-2 py-0.5">{{ $n }}</span> @endforeach
                    </div>
                    @if ($acc)
                        <div class="mt-3"><x-brand.level-badge :n="$a->level" :name="$acc->levelName($a->level)" /></div>
                    @endif
                    <div class="mt-auto pt-4 flex items-center justify-between gap-2 border-t border-gray-100 mt-4">
                        <a href="{{ route($acc?->isEngine() ? 'engines.index' : 'catalogue.models') }}" class="text-sm font-semibold text-primary-800 hover:underline pt-3">{{ __('See the catalogue') }} <i class="ri-arrow-right-line"></i></a>
                        @if (auth()->user()->isTenantAdmin())
                            <form method="POST" action="{{ route('partner-brands.disconnect', $a->_id) }}" data-confirm-danger="1" class="pt-3"
                                data-confirm="{{ __('Disconnect from this brand? Its catalogue is hidden from your quotes; quotes already made are unchanged.') }}">@csrf
                                <button class="text-xs font-semibold text-gray-500 hover:text-red-700"><i class="ri-link-unlink"></i> {{ __('Disconnect') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($archivedBrands->isNotEmpty() || $archivedEngines->isNotEmpty())
        <section class="rise mt-6 bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-start gap-3 mb-3">
                <span class="w-10 h-10 shrink-0 rounded-xl bg-gray-100 text-gray-500 flex items-center justify-center"><i class="ri-archive-line text-lg"></i></span>
                <div>
                    <h3 class="font-semibold text-gray-900">{{ __('Archived manual catalogues') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('Archived when a brand connected, so your quotes don\'t offer the same boats twice. Your old quotes still use them.') }}</p>
                </div>
            </div>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach ($archivedBrands as $b)
                    <li class="py-2.5 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2"><i class="ri-sailboat-line text-gray-400"></i> {{ $b->name }}</span>
                        @if (auth()->user()->isTenantAdmin())
                            <form method="POST" action="{{ route('partner-brands.restore') }}">@csrf
                                <input type="hidden" name="brand_id" value="{{ $b->_id }}">
                                <button class="inline-flex items-center gap-1 text-xs font-semibold text-primary-800 hover:bg-primary-50 px-2 py-1 rounded-lg"><i class="ri-inbox-unarchive-line"></i> {{ __('Restore') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
                @foreach ($archivedEngines as $brandName => $engines)
                    <li class="py-2.5 flex items-center justify-between gap-2">
                        <span class="flex items-center gap-2"><i class="ri-settings-3-line text-gray-400"></i> {{ $brandName }} <span class="text-gray-500">· {{ trans_choice('{1}:count engine|[2,*]:count engines', $engines->count(), ['count' => $engines->count()]) }}</span></span>
                        @if (auth()->user()->isTenantAdmin())
                            <form method="POST" action="{{ route('partner-brands.restore') }}">@csrf
                                <input type="hidden" name="engine_brand" value="{{ $brandName }}">
                                <button class="inline-flex items-center gap-1 text-xs font-semibold text-primary-800 hover:bg-primary-50 px-2 py-1 rounded-lg"><i class="ri-inbox-unarchive-line"></i> {{ __('Restore') }}</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-app-layout>
