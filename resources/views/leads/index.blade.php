{{-- Sales leads: potential clients, in the Figma dashboard style. --}}
<x-app-layout :title="__('Sales leads')" :header="__('Sales leads')">
    @php
        $card = 'bg-white border border-nq-border rounded-xl';
        $btn = 'h-9 inline-flex items-center gap-2 px-4 rounded-lg text-sm leading-[22px] font-medium transition-colors whitespace-nowrap';
        $btnSecondary = $btn . ' bg-white border border-nq-border text-nq-text hover:bg-nq-subtle';
        $iconBtn = 'w-7 h-7 inline-flex items-center justify-center rounded-lg transition-colors';
        $initials = fn ($s) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $s)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) ?: '—';
        $statusTag = fn ($s) => match ($s) {
            \App\Models\SalesLead::STATUS_QUALIFIED => 'bg-nq-success-bg border-nq-success-border text-nq-success-text',
            \App\Models\SalesLead::STATUS_CONTACTED => 'bg-nq-info-bg border-[#d4e9fb] text-[#1d6aa5]',
            \App\Models\SalesLead::STATUS_LOST      => 'bg-nq-error-bg border-[#fbd5d6] text-nq-error-text',
            default                                 => 'bg-nq-muted border-nq-border text-nq-text-2',
        };
        $statusDot = fn ($s) => match ($s) {
            \App\Models\SalesLead::STATUS_QUALIFIED => 'bg-nq-success',
            \App\Models\SalesLead::STATUS_CONTACTED => 'bg-nq-chart2',
            \App\Models\SalesLead::STATUS_LOST      => 'bg-nq-error',
            default                                 => 'bg-nq-text-3',
        };
    @endphp

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-nq-success-border bg-nq-success-bg px-4 py-2.5 text-sm text-nq-success-text">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-4">
        {{-- Heading --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-3">
                    <h2 class="text-2xl leading-8 font-semibold tracking-[-0.24px] text-nq-text">{{ __('Sales leads') }}</h2>
                    <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md bg-nq-primary-bg border border-[#d9e6ef] text-xs leading-5 font-medium text-primary-800">
                        {{ trans_choice('{0}no lead|{1}:count lead|[2,*]:count leads', $leads->total(), ['count' => $leads->total()]) }}
                    </span>
                </div>
                <p class="text-sm leading-[22px] text-nq-text-2">{{ __('People you are following up with, before they become clients.') }}</p>
            </div>
            <a href="{{ route('leads.create') }}" class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active"><i class="ph-bold ph-plus text-base"></i> {{ __('Add lead') }}</a>
        </div>

        {{-- Search --}}
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('leads.index') }}" class="w-full sm:w-auto">
                <label class="w-full sm:w-[300px] h-9 flex items-center gap-2 px-3 bg-white border border-nq-border rounded-lg focus-within:border-primary-800 transition-colors">
                    <i class="ph-bold ph-magnifying-glass text-base text-nq-text-3"></i>
                    <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search name, company, email, phone…') }}"
                        class="flex-1 min-w-0 p-0 border-0 bg-transparent text-sm leading-[22px] text-nq-text placeholder:text-nq-text-3 focus:ring-0" />
                </label>
            </form>
            @if ($q !== '')
                <a href="{{ route('leads.index') }}" class="h-9 inline-flex items-center gap-1 px-2 text-sm text-nq-text-2 hover:text-nq-text"><i class="ph-bold ph-x text-sm"></i> {{ __('Clear') }}</a>
            @endif
        </div>

        @if ($leads->isEmpty())
            <div class="{{ $card }}">
                @if ($q !== '')
                    <x-app.empty-state icon="ri-search-line" :title="__('No results')" :message="__('Try another search.')" />
                @else
                    <x-app.empty-state icon="ri-user-add-line" :title="__('No leads yet')" size="lg"
                        :message="__('Add people you are talking to but who are not clients yet. Convert a lead when it turns into a real deal.')"
                        :ctaLabel="__('Add lead')" ctaHref="{{ route('leads.create') }}" />
                @endif
            </div>
        @else
            <section class="{{ $card }} overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
                        <thead>
                            <tr class="bg-nq-subtle text-left text-xs leading-5 text-nq-text-2">
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2">{{ __('Name') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[200px]">{{ __('Email') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[150px]">{{ __('Phone') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[130px]">{{ __('Status') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[120px]">{{ __('Added') }}</th>
                                <th class="h-9 px-4 border-y border-nq-border-2 w-[120px]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leads as $lead)
                                @php $rowBorder = $loop->last ? '' : 'border-b border-nq-border-2'; @endphp
                                <tr class="hover:bg-nq-subtle">
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <div class="flex items-center gap-2">
                                            <span class="w-6 h-6 shrink-0 rounded-full bg-nq-primary-bg text-primary-800 text-[10px] font-semibold flex items-center justify-center">{{ $initials($lead->displayName()) }}</span>
                                            <div class="min-w-0">
                                                <div class="text-sm leading-[22px] font-medium text-nq-text truncate">{{ $lead->displayName() }}</div>
                                                @if ($lead->company_name && trim(($lead->first_name ?? '') . ($lead->last_name ?? '')) !== '')
                                                    <div class="text-xs leading-5 text-nq-text-3 truncate">{{ $lead->company_name }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="h-14 px-4 text-sm leading-[22px] text-nq-text truncate {{ $rowBorder }}">{{ $lead->email ?: '—' }}</td>
                                    <td class="h-14 px-4 text-sm leading-[22px] text-nq-text whitespace-nowrap {{ $rowBorder }}">{{ $lead->phone ?: '—' }}</td>
                                    <td class="h-14 px-4 {{ $rowBorder }}">
                                        <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md border text-xs leading-5 font-medium whitespace-nowrap {{ $statusTag($lead->status) }}"><span class="w-1.5 h-1.5 rounded-full {{ $statusDot($lead->status) }}"></span>{{ $lead->statusLabel() }}</span>
                                    </td>
                                    <td class="h-14 px-4 text-xs leading-5 text-nq-text-3 whitespace-nowrap {{ $rowBorder }}">{{ $lead->created_at?->translatedFormat('d/m/Y') ?? '—' }}</td>
                                    <td class="h-14 px-4 text-right whitespace-nowrap {{ $rowBorder }}">
                                        <form method="POST" action="{{ route('leads.convert', $lead->_id) }}" class="inline"
                                            data-confirm="{{ __('Convert «:name» to a client?', ['name' => $lead->displayName()]) }}">
                                            @csrf
                                            <button type="submit" class="{{ $iconBtn }} text-nq-text-3 hover:bg-nq-success-bg hover:text-nq-success" title="{{ __('Convert to client') }}"><i class="ph-bold ph-user-switch text-base"></i></button>
                                        </form>
                                        <a href="{{ route('leads.edit', $lead->_id) }}" class="{{ $iconBtn }} text-nq-text-3 hover:bg-nq-muted hover:text-nq-text" title="{{ __('Edit') }}"><i class="ph-bold ph-pencil-simple text-base"></i></a>
                                        <form method="POST" action="{{ route('leads.destroy', $lead->_id) }}" class="inline"
                                            data-confirm="{{ __('Delete this lead?') }}" data-confirm-danger="1">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="{{ $iconBtn }} text-nq-text-3 hover:bg-nq-error-bg hover:text-nq-error" title="{{ __('Delete') }}"><i class="ph-bold ph-trash text-base"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
            <div>{{ $leads->links() }}</div>
        @endif
    </div>
</x-app-layout>
