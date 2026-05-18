<x-admin-layout :title="__('Platform overview')" :header="__('Platform overview')">

    {{-- KPI grid --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 text-xs text-gray-500 uppercase tracking-wider">
                <i class="ri-store-2-line"></i> {{ __('Active dealerships') }}
            </div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $kpis['tenants_active'] }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ $kpis['tenants_total'] }} {{ __('total') }}</div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 text-xs text-gray-500 uppercase tracking-wider">
                <i class="ri-file-list-3-line"></i> {{ __('Quotes this month') }}
            </div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ number_format($kpis['quotes_month'], 0, ',', ' ') }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ now()->translatedFormat('F Y') }}</div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 text-xs text-gray-500 uppercase tracking-wider">
                <i class="ri-team-line"></i> {{ __('Tenant users') }}
            </div>
            <div class="mt-2 text-3xl font-bold text-gray-900">{{ $kpis['users_total'] }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ __('All roles across all tenants') }}</div>
        </div>

        <div class="bg-white rounded-2xl border border-gray-200 p-5">
            <div class="flex items-center gap-2 text-xs text-gray-500 uppercase tracking-wider">
                <i class="ri-pulse-line"></i> {{ __('Quote pipeline') }}
            </div>
            <div class="mt-2 grid grid-cols-4 gap-1 text-center">
                <div>
                    <div class="text-lg font-bold text-gray-900">{{ $quoteStatusCounts['draft'] }}</div>
                    <div class="text-[10px] text-gray-500 uppercase">{{ __('Draft') }}</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-primary-800">{{ $quoteStatusCounts['sent'] }}</div>
                    <div class="text-[10px] text-gray-500 uppercase">{{ __('Sent') }}</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-emerald-600">{{ $quoteStatusCounts['won'] }}</div>
                    <div class="text-[10px] text-gray-500 uppercase">{{ __('Won') }}</div>
                </div>
                <div>
                    <div class="text-lg font-bold text-gray-500">{{ $quoteStatusCounts['lost'] }}</div>
                    <div class="text-[10px] text-gray-500 uppercase">{{ __('Lost') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Top brands --}}
        <div class="lg:col-span-1 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100">
                <h2 class="font-semibold text-gray-900">{{ __('Most activated brands') }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">{{ __('Across all active dealerships') }}</p>
            </div>
            @if ($brandCounts->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-gray-500">
                    {{ __('No brand activations yet.') }}
                </div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($brandCounts as $brand => $count)
                        <li class="px-5 py-3 flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-900 truncate">{{ $brand }}</span>
                            <span class="text-xs font-semibold text-primary-800 bg-primary-50 px-2 py-0.5 rounded-full">
                                {{ $count }} {{ __(\Illuminate\Support\Str::plural('dealer', $count)) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Recent platform activity --}}
        <div class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div>
                    <h2 class="font-semibold text-gray-900">{{ __('Recent platform activity') }}</h2>
                    <p class="text-xs text-gray-500 mt-0.5">{{ __('Audit trail of superadmin actions') }}</p>
                </div>
                <a href="#" class="text-xs font-medium text-primary-800 hover:underline">{{ __('View all') }}</a>
            </div>
            @if ($recentAudit->isEmpty())
                <div class="px-5 py-8 text-center text-sm text-gray-500">
                    <i class="ri-history-line text-2xl text-gray-300"></i>
                    <p class="mt-2">{{ __('No activity yet.') }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ __('Actions you take in the platform area will appear here.') }}</p>
                </div>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach ($recentAudit as $row)
                        <li class="px-5 py-3 flex items-start gap-3">
                            <span class="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
                                <i class="ri-history-line"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm text-gray-900">
                                    <span class="font-medium">{{ $row->actor_email ?? __('Unknown') }}</span>
                                    <span class="text-gray-500">·</span>
                                    <span class="font-mono text-xs">{{ $row->action }}</span>
                                    @if ($row->target_label)
                                        <span class="text-gray-500">·</span>
                                        <span class="text-gray-700">{{ $row->target_label }}</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-gray-400 mt-0.5">
                                    {{ $row->created_at?->diffForHumans() }}
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-admin-layout>
