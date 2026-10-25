<x-app-layout :title="__('Team')" :header="__('Team')">
    @include('brand.partials.flash')

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 space-y-4">
            <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4"><h3 class="font-semibold text-gray-900">{{ __('Members') }}</h3></div>
                <ul class="divide-y divide-gray-100">
                    @foreach ($members as $m)
                        <li class="px-5 py-3 flex items-center justify-between gap-3 {{ $m->is_active === false ? 'opacity-60' : '' }}">
                            <div>
                                <div class="font-medium text-gray-900">{{ $m->name }} @if ((string) $m->_id === (string) auth()->id()) <span class="text-xs text-gray-400">({{ __('you') }})</span> @endif</div>
                                <div class="text-sm text-gray-500">{{ $m->email }}</div>
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
                <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4"><h3 class="font-semibold text-gray-900">{{ __('Pending invitations') }}</h3></div>
                    <ul class="divide-y divide-gray-100">
                        @foreach ($invites as $inv)
                            <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="font-medium text-gray-900">{{ $inv->name }}</div>
                                    <div class="text-sm text-gray-500">{{ $inv->email }} · {{ $inv->isExpired() ? __('expired') : __('expires :date', ['date' => $inv->expires_at?->format('d/m/Y')]) }}</div>
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

        <section class="bg-white rounded-2xl border border-gray-200 p-5 self-start">
            <h3 class="font-semibold text-gray-900 mb-1">{{ __('Invite a colleague') }}</h3>
            <p class="text-sm text-gray-500 mb-4">{{ __('They get the same access as you: catalogue, price levels, dealers and quotes.') }}</p>
            <form method="POST" action="{{ route('brand.team.invite') }}" class="space-y-3">@csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Name') }}</label>
                    <input name="name" required maxlength="120" value="{{ old('name') }}" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }}</label>
                    <input name="email" type="email" required maxlength="200" value="{{ old('email') }}" class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800" />
                </div>
                <button class="w-full px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">{{ __('Send invitation') }}</button>
            </form>
        </section>
    </div>
</x-app-layout>
