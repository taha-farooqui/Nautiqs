<x-app-layout :title="__('Dealers')" :header="__('Dealers')">
    @include('brand.partials.flash')

    <div x-data="{ inviteOpen: {{ $errors->has('emails') || $errors->has('brand_ids') ? 'true' : 'false' }} }">
        <div class="mb-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
            <p class="text-sm text-gray-600 max-w-2xl">{{ __('Only the dealers you invite get your catalogue. Everyone else can still add your brand by hand.') }}</p>
            <button type="button" @click="inviteOpen = true" class="inline-flex items-center gap-1 px-3 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg self-start">
                <i class="ri-user-add-line"></i> {{ __('Invite dealers') }}
            </button>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            @foreach (['all' => __('All'), 'active' => __('Active'), 'waiting' => __('Waiting for answer'), 'invited' => __('Not on Nautiqs yet'), 'ended' => __('Declined / ended')] as $t => $label)
                <a href="{{ route('brand.dealers.index', ['tab' => $t]) }}"
                    class="px-4 py-2 rounded-full text-sm font-semibold {{ $tab === $t ? 'bg-primary-800 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50' }}">
                    {{ $label }} <span class="{{ $tab === $t ? 'text-white/70' : 'text-gray-400' }}">{{ $counts[$t] }}</span>
                </a>
            @endforeach
        </div>

        @if ($accesses->isEmpty())
            <x-app.empty-state icon="ri-store-2-line" :title="__('No dealers here yet')"
                :message="__('Invite your dealers by email. Each one gets your catalogue at the price level you choose.')" size="lg" />
        @else
            <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                            <tr>
                                <th class="px-5 py-3 font-semibold">{{ __('Dealer') }}</th>
                                <th class="px-4 py-3 font-semibold">{{ __('Status') }}</th>
                                <th class="px-4 py-3 font-semibold">{{ __('Level & brands') }}</th>
                                <th class="px-4 py-3 font-semibold text-right">{{ __('Quotes') }}</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($accesses as $a)
                                @php
                                    $co = $a->company_id ? ($companies[(string) $a->company_id] ?? null) : null;
                                    $ended = in_array($a->status, [\App\Models\BrandAccess::STATUS_DECLINED, \App\Models\BrandAccess::STATUS_REVOKED, \App\Models\BrandAccess::STATUS_DISCONNECTED], true);
                                @endphp
                                <tr class="align-top">
                                    <td class="px-5 py-3">
                                        <div class="font-semibold text-gray-900">{{ $co?->name ?? $a->email }}</div>
                                        <div class="text-xs text-gray-500">
                                            {{ $co ? $a->email . ' · ' : '' }}
                                            @if ($a->isActive() && $a->responded_at) {{ __('since :date', ['date' => $a->responded_at->format('d/m/Y')]) }}
                                            @elseif ($ended && $a->ended_at) {{ __('ended :date', ['date' => $a->ended_at->format('d/m/Y')]) }}
                                            @elseif ($a->status === \App\Models\BrandAccess::STATUS_DECLINED && $a->responded_at) {{ __('declined :date', ['date' => $a->responded_at->format('d/m/Y')]) }}
                                            @else {{ __('invited :date', ['date' => $a->invited_at?->format('d/m/Y')]) }} @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3"><x-brand.access-status :status="$a->status" /></td>
                                    <td class="px-4 py-3">
                                        @if ($ended)
                                            <span class="text-gray-400">—</span>
                                        @else
                                            <form method="POST" action="{{ route('brand.dealers.update', $a->_id) }}" x-data="{ edit: false }" class="space-y-2">
                                                @csrf @method('PATCH')
                                                <select name="level" aria-label="{{ __('Price level') }}" onchange="this.form.submit()"
                                                    class="rounded-lg border-gray-300 text-sm py-1.5 focus:border-primary-800 focus:ring-primary-800">
                                                    @foreach ($levels as $n => $lvl)
                                                        <option value="{{ $n }}" @selected((int) $a->level === $n)>{{ $lvl['name'] }}</option>
                                                    @endforeach
                                                </select>
                                                <div class="text-xs text-gray-600">
                                                    <span x-show="!edit">{{ $brands->filter(fn ($b) => in_array((string) $b->_id, $a->brandIds(), true))->pluck('name')->implode(', ') ?: '—' }}</span>
                                                    @if ($brands->count() > 1)
                                                        <button type="button" x-show="!edit" @click="edit = true" class="ml-1 text-primary-800 hover:underline">{{ __('Change') }}</button>
                                                        <div x-show="edit" x-cloak class="flex flex-wrap items-center gap-3">
                                                            @foreach ($brands as $b)
                                                                <label class="inline-flex items-center gap-1"><input type="checkbox" name="brand_ids[]" value="{{ $b->_id }}" @checked(in_array((string) $b->_id, $a->brandIds(), true)) class="rounded border-gray-300 text-primary-800 focus:ring-primary-800"> {{ $b->name }}</label>
                                                            @endforeach
                                                            <button class="px-2 py-1 text-xs font-semibold bg-primary-800 text-white rounded">{{ __('Save') }}</button>
                                                        </div>
                                                    @endif
                                                </div>
                                            </form>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ $a->company_id ? ($quoteCounts[(string) $a->company_id] ?? 0) : '—' }}</td>
                                    <td class="px-5 py-3">
                                        <div class="flex items-center justify-end gap-2">
                                            @if ($a->isOpen())
                                                <form method="POST" action="{{ route('brand.dealers.resend', $a->_id) }}">@csrf
                                                    <button class="px-3 py-1.5 text-xs font-semibold border border-gray-300 hover:bg-gray-50 rounded-lg">{{ __('Resend') }}</button>
                                                </form>
                                            @endif
                                            @if ($a->isActive() || $a->isOpen())
                                                <form method="POST" action="{{ route('brand.dealers.revoke', $a->_id) }}" data-confirm-danger="1"
                                                    data-confirm="{{ $a->isActive() ? __('Revoke this dealer? Your catalogue is hidden from them straight away. Their quotes stay theirs.') : __('Cancel this invitation?') }}">
                                                    @csrf
                                                    <button class="px-3 py-1.5 text-xs font-semibold border border-red-200 text-red-700 hover:bg-red-50 rounded-lg">{{ $a->isActive() ? __('Revoke') : __('Cancel') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Invite modal --}}
        <div x-show="inviteOpen" x-cloak x-transition.opacity @keydown.escape.window="inviteOpen = false"
            class="fixed inset-0 z-50 bg-gray-900/70 flex items-center justify-center p-4">
            <form method="POST" action="{{ route('brand.dealers.invite') }}" @click.outside="inviteOpen = false"
                class="bg-white rounded-2xl w-full max-w-xl overflow-hidden shadow-2xl">
                @csrf
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">{{ __('Invite dealers') }}</h3>
                    <button type="button" @click="inviteOpen = false" class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:bg-gray-100 rounded-lg" aria-label="{{ __('Close') }}"><i class="ri-close-line text-xl"></i></button>
                </div>
                <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    <div>
                        <label for="emails" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Dealer emails') }}</label>
                        <textarea id="emails" name="emails" rows="3" required class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800"
                            placeholder="contact@dealer.fr">{{ old('emails') }}</textarea>
                        <p class="text-xs text-gray-500 mt-1">{{ __('One per line. Someone not on Nautiqs yet receives an invitation to request an account.') }}</p>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-700 mb-2">{{ __('Price level') }}</span>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($levels as $n => $lvl)
                                <label class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm cursor-pointer has-[:checked]:border-primary-800 has-[:checked]:bg-primary-50 border-gray-200">
                                    <input type="radio" name="level" value="{{ $n }}" @checked((int) old('level', 2) === $n) class="text-primary-800 focus:ring-primary-800"> {{ $lvl['name'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <span class="block text-sm font-medium text-gray-700 mb-2">{{ __('Brands to share') }}</span>
                        <div class="flex flex-wrap gap-4">
                            @foreach ($brands as $b)
                                <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="brand_ids[]" value="{{ $b->_id }}"
                                    @checked(in_array((string) $b->_id, old('brand_ids', $brands->map(fn ($x) => (string) $x->_id)->all()), true)) class="rounded border-gray-300 text-primary-800 focus:ring-primary-800"> {{ $b->name }}</label>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <label for="message" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Personal message') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                        <textarea id="message" name="message" rows="2" maxlength="1000" class="w-full rounded-lg border-gray-300 text-sm focus:border-primary-800 focus:ring-primary-800">{{ old('message') }}</textarea>
                    </div>
                </div>
                <div class="px-5 py-4 border-t border-gray-100 flex justify-end gap-2">
                    <button type="button" @click="inviteOpen = false" class="px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 rounded-lg">{{ __('Cancel') }}</button>
                    <button class="inline-flex items-center gap-1 px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg"><i class="ri-send-plane-line"></i> {{ __('Send invitations') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
