<x-app-layout :title="__('Team')" :header="__('Team')">
    @include('brand.partials.flash')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <section class="rise bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 flex items-center justify-between"><h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="ri-team-line text-gray-400"></i> {{ __('Members') }}</h3><span class="text-sm text-gray-500">{{ $members->count() }}</span></div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($members as $m)
                        <li class="px-5 py-3.5 flex items-center justify-between gap-3 hover:bg-gray-50/70 {{ $m->is_active === false ? 'opacity-60' : '' }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="relative w-10 h-10 rounded-full bg-gradient-to-br from-primary-800 to-primary-600 text-white text-sm font-bold flex items-center justify-center shrink-0">
                                    {{ mb_strtoupper(collect(explode(' ', trim($m->name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) }}
                                    <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white {{ $m->is_active === false ? 'bg-gray-400' : 'bg-emerald-500' }}"></span>
                                </span>
                                <div class="min-w-0">
                                    <div class="font-medium text-gray-900 truncate">{{ $m->name }} @if ((string) $m->_id === (string) auth()->id()) <span class="text-xs font-semibold text-primary-800 bg-primary-50 rounded-full px-1.5 py-0.5">{{ __('you') }}</span> @endif</div>
                                    <div class="text-sm text-gray-500 truncate">{{ $m->email }}</div>
                                </div>
                            </div>
                            @if ((string) $m->_id !== (string) auth()->id())
                                <form method="POST" action="{{ route('brand.team.toggle', $m->_id) }}"
                                    @if ($m->is_active !== false) data-confirm="{{ __('Deactivate :name? They can no longer sign in.', ['name' => $m->name]) }}" data-confirm-danger="1" @endif>@csrf
                                    <button class="px-3 py-1.5 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50">{{ $m->is_active === false ? __('Reactivate') : __('Deactivate') }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>

            @if ($invites->isNotEmpty())
                <section class="rise bg-white rounded-2xl border border-gray-200 overflow-hidden" style="--d: 1">
                    <div class="px-5 py-4"><h3 class="font-semibold text-gray-900 flex items-center gap-2"><i class="ri-mail-send-line text-gray-400"></i> {{ __('Pending invitations') }}</h3></div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($invites as $inv)
                            <li class="px-5 py-3.5 flex flex-wrap items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="w-10 h-10 rounded-full border-2 border-dashed border-gray-300 text-gray-400 flex items-center justify-center shrink-0"><i class="ri-mail-line"></i></span>
                                    <div class="min-w-0">
                                        <div class="font-medium text-gray-900 truncate">{{ $inv->name }}</div>
                                        <div class="text-sm truncate {{ $inv->isExpired() ? 'text-red-600' : 'text-gray-500' }}">{{ $inv->email }} · {{ $inv->isExpired() ? __('expired') : __('expires :date', ['date' => $inv->expires_at?->format('d/m/Y')]) }}</div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <form method="POST" action="{{ route('brand.team.resend', $inv->_id) }}">@csrf
                                        <button class="px-3 py-1.5 text-sm font-medium border border-gray-300 rounded-lg hover:bg-gray-50">{{ __('Resend') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('brand.team.revoke', $inv->_id) }}" data-confirm="{{ __('Revoke this invitation?') }}" data-confirm-danger="1">@csrf @method('DELETE')
                                        <button class="px-3 py-1.5 text-sm font-medium text-red-600 border border-red-200 rounded-lg hover:bg-red-50">{{ __('Revoke') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        <section class="rise bg-white rounded-2xl border border-gray-200 p-5 self-start" style="--d: 2">
            <span class="w-11 h-11 rounded-xl bg-primary-50 text-primary-800 flex items-center justify-center mb-3"><i class="ri-user-add-line text-xl"></i></span>
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Invite a colleague') }}</h3>
            <p class="text-sm text-gray-500 mb-4">{{ __('They get the same access as you: catalogue, price levels, dealers and quotes.') }}</p>
            <form method="POST" action="{{ route('brand.team.invite') }}" class="space-y-3">@csrf
                <div class="relative"><i class="ri-user-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input name="name" required maxlength="120" value="{{ old('name') }}" placeholder="{{ __('Name') }}" aria-label="{{ __('Name') }}" class="w-full pl-9 rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" /></div>
                <div class="relative"><i class="ri-mail-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input name="email" type="email" required maxlength="200" value="{{ old('email') }}" placeholder="{{ __('Email') }}" aria-label="{{ __('Email') }}" class="w-full pl-9 rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" /></div>
                <button class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-send-plane-fill"></i> {{ __('Send invitation') }}</button>
            </form>
        </section>
    </div>
</x-app-layout>
