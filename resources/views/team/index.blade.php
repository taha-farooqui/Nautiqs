<x-app-layout :title="__('Team')" :header="__('Team')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- LEFT: members + pending invites --}}
        <div class="xl:col-span-2 space-y-6">

            {{-- Active members --}}
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-900">{{ __('Members') }}</h3>
                    <p class="text-xs text-gray-500">{{ __('People with access to this workspace.') }}</p>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach ($members as $m)
                        @php
                            $isSelf = (string) $m->_id === (string) auth()->id();
                            $isActive = $m->isActive();
                            $initials = strtoupper(collect(explode(' ', $m->name))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join(''));
                        @endphp
                        <div class="px-5 py-4 flex items-center gap-3">
                            <span class="w-10 h-10 rounded-full bg-primary-50 text-primary-800 font-semibold text-sm flex items-center justify-center shrink-0 {{ ! $isActive ? 'opacity-40' : '' }}">
                                {{ $initials }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-medium text-gray-900 truncate {{ ! $isActive ? 'opacity-60' : '' }}">
                                        {{ $m->name }}
                                        @if ($isSelf)
                                            <span class="text-xs text-gray-400 font-normal">({{ __('you') }})</span>
                                        @endif
                                    </p>
                                    @if ($m->role === \App\Models\User::ROLE_TENANT_ADMIN)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-primary-50 text-primary-800">{{ __('Admin') }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-gray-100 text-gray-700">{{ __('Salesperson') }}</span>
                                    @endif
                                    @if (! $isActive)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-red-50 text-red-700">{{ __('Deactivated') }}</span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 truncate">{{ $m->email }}</p>
                            </div>
                            @if (! $isSelf)
                                <div x-data="{ open: false }" class="relative">
                                    <button type="button" @click="open = !open" @click.outside="open = false"
                                        class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                                        <i class="ri-more-2-fill"></i>
                                    </button>
                                    <div x-show="open" x-cloak x-transition.opacity
                                        class="absolute right-0 top-full mt-1 w-56 z-20 bg-white rounded-lg border border-gray-200 shadow-lg py-1">
                                        {{-- Role change --}}
                                        @if ($m->role === \App\Models\User::ROLE_TENANT_ADMIN)
                                            <form method="POST" action="{{ route('team.role', $m->_id) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_TENANT_USER }}" />
                                                <button class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                    <i class="ri-user-line"></i> {{ __('Make Salesperson') }}
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('team.role', $m->_id) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="role" value="{{ \App\Models\User::ROLE_TENANT_ADMIN }}" />
                                                <button class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                                    <i class="ri-shield-user-line"></i> {{ __('Make Admin') }}
                                                </button>
                                            </form>
                                        @endif

                                        {{-- Activate / Deactivate --}}
                                        @if ($isActive)
                                            <form method="POST" action="{{ route('team.deactivate', $m->_id) }}"
                                                data-confirm="{{ __('Deactivate :name?', ['name' => $m->name]) }}"
                                                data-confirm-text="{{ __('They will no longer be able to log in. Their quotes stay attributed to them.') }}"
                                                data-confirm-danger="1">
                                                @csrf
                                                <button class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50">
                                                    <i class="ri-user-unfollow-line"></i> {{ __('Deactivate') }}
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('team.activate', $m->_id) }}">
                                                @csrf
                                                <button class="w-full text-left flex items-center gap-2 px-3 py-2 text-sm text-emerald-700 hover:bg-emerald-50">
                                                    <i class="ri-user-follow-line"></i> {{ __('Reactivate') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Pending invites --}}
            @if ($invites->isNotEmpty())
                <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100">
                        <h3 class="font-semibold text-gray-900">{{ __('Pending invitations') }}</h3>
                        <p class="text-xs text-gray-500">{{ __('Sent but not yet accepted. Links expire after 7 days.') }}</p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($invites as $inv)
                            @php $isExpired = $inv->isExpired(); @endphp
                            <div class="px-5 py-3 flex items-center gap-3">
                                <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                                    <i class="ri-mail-send-line"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-medium text-gray-900 truncate">{{ $inv->name }}</p>
                                        @if ($inv->role === \App\Models\User::ROLE_TENANT_ADMIN)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-primary-50 text-primary-800">{{ __('Admin') }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-gray-100 text-gray-700">{{ __('Salesperson') }}</span>
                                        @endif
                                        @if ($isExpired)
                                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide bg-red-50 text-red-700">{{ __('Expired') }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500 truncate">{{ $inv->email }}</p>
                                    <p class="text-[11px] text-gray-400">
                                        {{ __('Invited by') }} {{ $inv->invited_by_name }} ·
                                        {{ $inv->created_at?->diffForHumans() }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-1">
                                    <form method="POST" action="{{ route('team.invite.resend', $inv->_id) }}" class="inline">
                                        @csrf
                                        <button class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg" title="{{ __('Resend invitation') }}">
                                            <i class="ri-refresh-line"></i> {{ __('Resend') }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('team.invite.revoke', $inv->_id) }}" class="inline"
                                        data-confirm="{{ __('Revoke this invitation?') }}"
                                        data-confirm-danger="1">
                                        @csrf @method('DELETE')
                                        <button class="inline-flex items-center justify-center w-8 h-8 text-red-600 hover:bg-red-50 rounded-lg" title="{{ __('Revoke') }}">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- RIGHT: invite form --}}
        <div class="xl:col-span-1">
            <div class="bg-white rounded-2xl border border-gray-200 p-5 sticky top-20">
                <h3 class="font-semibold text-gray-900 mb-1">{{ __('Invite teammate') }}</h3>
                <p class="text-xs text-gray-500 mb-4">{{ __('They get an email link to set a password and join.') }}</p>

                @if ($errors->any())
                    <div class="mb-3 rounded-lg border border-red-200 bg-red-50 text-red-700 px-3 py-2 text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('team.invite') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Full name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}" required
                            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Email') }} <span class="text-red-500">*</span></label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Role') }}</label>
                        <select name="role"
                            class="w-full rounded-lg border-gray-300 focus:border-primary-800 focus:ring-primary-800 text-sm">
                            <option value="{{ \App\Models\User::ROLE_TENANT_USER }}" @selected(old('role', 'tenant_user') === 'tenant_user')>{{ __('Salesperson') }}</option>
                            <option value="{{ \App\Models\User::ROLE_TENANT_ADMIN }}" @selected(old('role') === 'tenant_admin')>{{ __('Admin') }}</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">
                            {{ __('Salespeople can manage quotes and clients. Admins can also invite teammates and edit company settings.') }}
                        </p>
                    </div>
                    <div class="pt-2">
                        <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2.5 rounded-lg text-sm transition">
                            <i class="ri-mail-send-line"></i> {{ __('Send invitation') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
