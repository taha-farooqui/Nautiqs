{{-- Sales leads — a lightweight pipeline: filter chips, flag/highlight, export. --}}
<x-app-layout :title="__('Sales leads')" :header="__('Sales leads')">
    @php
        $card = 'bg-white border border-nq-border rounded-xl';
        $btn = 'h-9 inline-flex items-center gap-2 px-4 rounded-lg text-sm leading-[22px] font-medium transition-colors whitespace-nowrap';
        $btnSecondary = $btn . ' bg-white border border-nq-border text-nq-text hover:bg-nq-subtle';
        $iconBtn = 'w-7 h-7 inline-flex items-center justify-center rounded-lg transition-colors';
        $initials = fn ($s) => mb_strtoupper(collect(preg_split('/\s+/', trim((string) $s)))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')) ?: '—';
        $S = \App\Models\SalesLead::class;
        // status => [tag classes, dot, left accent, soft avatar tone]
        $meta = [
            $S::STATUS_NEW       => ['bg-nq-muted border-nq-border text-nq-text-2',          'bg-nq-text-3', 'bg-nq-text-3',  'bg-nq-muted text-nq-text-2'],
            $S::STATUS_CONTACTED => ['bg-nq-info-bg border-[#d4e9fb] text-[#1d6aa5]',          'bg-nq-chart2', 'bg-nq-chart2',  'bg-nq-info-bg text-[#1d6aa5]'],
            $S::STATUS_QUALIFIED => ['bg-nq-success-bg border-nq-success-border text-nq-success-text', 'bg-nq-success', 'bg-nq-success', 'bg-nq-success-bg text-nq-success-text'],
            $S::STATUS_LOST      => ['bg-nq-error-bg border-[#fbd5d6] text-nq-error-text',     'bg-nq-error',  'bg-nq-error',   'bg-nq-error-bg text-nq-error-text'],
        ];
        $chips = ['all' => __('All')] + $S::statusLabels();
        $q = fn (array $over) => route('leads.index', array_filter(array_merge(['status' => $status, 'q' => request('q')], $over), fn ($v) => $v !== null && $v !== ''));
        $exportUrl = route('leads.export', array_filter(['status' => $status, 'q' => request('q')]));
    @endphp

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-nq-success-border bg-nq-success-bg px-4 py-2.5 text-sm text-nq-success-text">{{ session('status') }}</div>
    @endif

    <div class="flex flex-col gap-4">
        {{-- Heading --}}
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex flex-col gap-1">
                <h2 class="text-2xl leading-8 font-semibold tracking-[-0.24px] text-nq-text">{{ __('Sales leads') }}</h2>
                <p class="text-sm leading-[22px] text-nq-text-2">{{ __('People you are following up with, before they become clients.') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ $exportUrl }}" class="{{ $btnSecondary }}"><i class="ph-bold ph-export text-base"></i> {{ __('Export') }}</a>
                <a href="{{ route('leads.create') }}" class="{{ $btn }} bg-primary-800 text-white hover:bg-nq-active"><i class="ph-bold ph-plus text-base"></i> {{ __('Add lead') }}</a>
            </div>
        </div>

        {{-- Pipeline filter chips --}}
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($chips as $key => $label)
                @php
                    $on = ($key === 'all' && ! $status) || $key === $status;
                    $n = $counts[$key] ?? 0;
                    $dot = $key === 'all' ? 'bg-nq-text-3' : ($meta[$key][1] ?? 'bg-nq-text-3');
                @endphp
                <a href="{{ $q(['status' => $key === 'all' ? null : $key]) }}"
                    class="h-8 inline-flex items-center gap-2 pl-2.5 pr-2 rounded-lg border text-sm leading-[22px] font-medium transition-colors {{ $on ? 'bg-nq-active border-nq-active text-white' : 'bg-white border-nq-border text-nq-text-2 hover:bg-nq-subtle' }}">
                    @if ($key !== 'all')<span class="w-1.5 h-1.5 rounded-full {{ $on ? 'bg-white' : $dot }}"></span>@endif
                    {{ $label }}
                    <span class="min-w-[20px] h-5 px-1.5 inline-flex items-center justify-center rounded-md text-xs font-semibold {{ $on ? 'bg-white/20 text-white' : 'bg-nq-muted text-nq-text-2' }}">{{ $n }}</span>
                </a>
            @endforeach
            <form method="GET" action="{{ route('leads.index') }}" class="ml-auto w-full sm:w-auto">
                @if ($status) <input type="hidden" name="status" value="{{ $status }}"> @endif
                <label class="w-full sm:w-[280px] h-9 flex items-center gap-2 px-3 bg-white border border-nq-border rounded-lg focus-within:border-primary-800 transition-colors">
                    <i class="ph-bold ph-magnifying-glass text-base text-nq-text-3"></i>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search name, company, email, phone…') }}"
                        class="flex-1 min-w-0 p-0 border-0 bg-transparent text-sm leading-[22px] text-nq-text placeholder:text-nq-text-3 focus:ring-0" />
                </label>
            </form>
        </div>

        @if ($leads->isEmpty())
            <div class="{{ $card }}">
                @if (request('q') || $status)
                    <x-app.empty-state icon="ri-search-line" :title="__('No results')" :message="__('Try another search or filter.')" />
                @else
                    <x-app.empty-state icon="ri-user-add-line" :title="__('No leads yet')" size="lg"
                        :message="__('Add people you are talking to but who are not clients yet. Convert a lead when it turns into a real deal.')"
                        :ctaLabel="__('Add lead')" ctaHref="{{ route('leads.create') }}" />
                @endif
            </div>
        @else
            <section class="{{ $card }} overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[880px] text-sm">
                        <thead>
                            <tr class="bg-nq-subtle text-left text-xs leading-5 text-nq-text-2">
                                <th class="h-9 w-10 border-y border-nq-border-2"></th>
                                <th class="h-9 px-3 font-normal border-y border-nq-border-2">{{ __('Name') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[240px]">{{ __('Contact') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[140px]">{{ __('Status') }}</th>
                                <th class="h-9 px-4 font-normal border-y border-nq-border-2 w-[110px]">{{ __('Added') }}</th>
                                <th class="h-9 px-4 border-y border-nq-border-2 w-[120px]"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($leads as $lead)
                                @php
                                    $m = $meta[$lead->status] ?? $meta[$S::STATUS_NEW];
                                    $rowBorder = $loop->last ? '' : 'border-b border-nq-border-2';
                                    $flagged = (bool) $lead->is_flagged;
                                @endphp
                                <tr class="group relative {{ $flagged ? 'bg-nq-warning-bg/40' : 'hover:bg-nq-subtle' }}">
                                    {{-- Status left accent + flag --}}
                                    <td class="h-16 pl-3 relative {{ $rowBorder }}">
                                        <span class="absolute left-0 top-2 bottom-2 w-1 rounded-r {{ $m[2] }}"></span>
                                        <form method="POST" action="{{ route('leads.flag', $lead->_id) }}">
                                            @csrf
                                            <button type="submit" class="{{ $iconBtn }} {{ $flagged ? 'text-nq-chart3' : 'text-nq-border-strong hover:text-nq-text-3' }}" title="{{ $flagged ? __('Remove highlight') : __('Highlight') }}"><i class="ph-bold ph-star text-base"></i></button>
                                        </form>
                                    </td>
                                    <td class="h-16 px-3 {{ $rowBorder }}">
                                        <div class="flex items-center gap-2.5">
                                            <span class="w-8 h-8 shrink-0 rounded-full text-xs font-semibold flex items-center justify-center {{ $m[3] }}">{{ $initials($lead->displayName()) }}</span>
                                            <div class="min-w-0">
                                                <div class="text-sm leading-[22px] font-medium text-nq-text truncate">{{ $lead->displayName() }}</div>
                                                @if ($lead->company_name && trim(($lead->first_name ?? '') . ($lead->last_name ?? '')) !== '')
                                                    <div class="text-xs leading-5 text-nq-text-3 truncate">{{ $lead->company_name }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="h-16 px-4 {{ $rowBorder }}">
                                        <div class="flex flex-col gap-0.5">
                                            @if ($lead->email)
                                                <a href="mailto:{{ $lead->email }}" class="inline-flex items-center gap-1.5 text-sm leading-5 text-nq-text hover:text-primary-800 truncate"><i class="ph-bold ph-envelope-simple text-sm text-nq-text-3"></i>{{ $lead->email }}</a>
                                            @endif
                                            @if ($lead->phone)
                                                <a href="tel:{{ $lead->phone }}" class="inline-flex items-center gap-1.5 text-xs leading-5 text-nq-text-2 hover:text-primary-800"><i class="ph-bold ph-phone text-sm text-nq-text-3"></i>{{ $lead->phone }}</a>
                                            @endif
                                            @if (! $lead->email && ! $lead->phone)
                                                <span class="text-sm text-nq-text-3">—</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="h-16 px-4 {{ $rowBorder }}">
                                        <span class="h-6 inline-flex items-center gap-1.5 px-2 rounded-md border text-xs leading-5 font-medium whitespace-nowrap {{ $m[0] }}"><span class="w-1.5 h-1.5 rounded-full {{ $m[1] }}"></span>{{ $lead->statusLabel() }}</span>
                                    </td>
                                    <td class="h-16 px-4 text-xs leading-5 text-nq-text-3 whitespace-nowrap {{ $rowBorder }}">{{ $lead->created_at?->translatedFormat('d/m/Y') ?? '—' }}</td>
                                    <td class="h-16 px-4 text-right whitespace-nowrap {{ $rowBorder }}">
                                        <form method="POST" action="{{ route('leads.convert', $lead->_id) }}" class="inline" data-confirm="{{ __('Convert «:name» to a client?', ['name' => $lead->displayName()]) }}">
                                            @csrf
                                            <button type="submit" class="{{ $iconBtn }} text-nq-text-3 hover:bg-nq-success-bg hover:text-nq-success" title="{{ __('Convert to client') }}"><i class="ph-bold ph-user-switch text-base"></i></button>
                                        </form>
                                        <a href="{{ route('leads.edit', $lead->_id) }}" class="{{ $iconBtn }} text-nq-text-3 hover:bg-nq-muted hover:text-nq-text" title="{{ __('Edit') }}"><i class="ph-bold ph-pencil-simple text-base"></i></a>
                                        <form method="POST" action="{{ route('leads.destroy', $lead->_id) }}" class="inline" data-confirm="{{ __('Delete this lead?') }}" data-confirm-danger="1">
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
