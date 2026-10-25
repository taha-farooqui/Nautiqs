<x-app-layout :title="$title" :header="$title">
    @include('brand.partials.flash')
    @php $eur = fn ($v) => $v === null ? '—' : number_format((float) $v, 2, ',', ' ') . ' €'; @endphp

    <div class="mb-4 text-sm"><a href="{{ $back }}" class="text-gray-500 hover:text-primary-800"><i class="ri-arrow-left-line"></i> {{ __('Back') }}</a></div>

    <div class="mb-4 rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 text-sm text-primary-900 flex items-start gap-2 max-w-4xl">
        <i class="ri-links-line mt-0.5"></i>
        <div>
            {{ __('Managed by :brand. Prices, versions and options are kept up to date by the brand.', ['brand' => $account->name ?? __('the brand')]) }}
            @if ($levelName) {{ __('Your level: :level.', ['level' => $levelName]) }} @endif
            <span class="text-primary-800/80">{{ __('You can set your own special price; leave it empty to use your level price.') }}</span>
        </div>
    </div>

    <form method="POST" action="{{ $action }}" class="space-y-4 max-w-4xl">
        @csrf @method('PATCH')
        @foreach ($groups as $label => $rows)
            @continue($rows->isEmpty())
            <section class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-5 py-4"><h3 class="font-semibold text-gray-900">{{ $label }}</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                            <tr>
                                <th class="px-5 py-2.5">{{ __('Item') }}</th>
                                <th class="px-4 py-2.5 text-right">{{ __('Public HT') }} <i class="ri-lock-line"></i></th>
                                <th class="px-4 py-2.5 text-right">{{ __('Your level price') }}</th>
                                <th class="px-5 py-2.5 text-right w-48">{{ __('Special price') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows as $r)
                                <tr>
                                    <td class="px-5 py-2.5">
                                        @if ($r->category) <div class="text-[11px] font-semibold uppercase text-gray-400">{{ $r->category }}</div> @endif
                                        <span class="text-gray-900">{{ $r->name }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-700">{{ $eur($r->public) }}</td>
                                    <td class="px-4 py-2.5 text-right {{ $r->override ? 'text-gray-400 line-through' : 'font-semibold text-gray-900' }}">{{ $eur($r->level) }}</td>
                                    <td class="px-5 py-2.5 text-right">
                                        <input type="number" step="0.01" min="0" name="special[{{ $r->id }}]" value="{{ $r->override ? $r->cost : '' }}"
                                            placeholder="{{ $r->level !== null ? number_format($r->level, 2, '.', '') : '' }}"
                                            class="w-36 rounded-lg border-gray-300 text-right text-sm focus:border-primary-800 focus:ring-primary-800 {{ $r->override ? 'bg-amber-50 border-amber-300' : '' }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
        <div class="flex justify-end">
            <button class="px-4 py-2 text-sm font-semibold bg-primary-800 hover:bg-primary-900 text-white rounded-lg">{{ __('Save special prices') }}</button>
        </div>
    </form>
</x-app-layout>
