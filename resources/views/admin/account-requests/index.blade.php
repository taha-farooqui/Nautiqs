<x-admin-layout :title="__('Account requests')" :header="__('Account requests')">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            <i class="ri-checkbox-circle-line"></i> {{ session('status') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3 text-sm">
            <i class="ri-error-warning-line"></i> {{ $errors->first() }}
        </div>
    @endif

    {{-- ============================ PENDING ============================ --}}
    <div class="mb-2 flex items-center gap-2">
        <h3 class="font-semibold text-gray-900">{{ __('Pending requests') }}</h3>
        @if ($pending->count() > 0)
            <span class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-red-500 text-white text-[11px] font-bold inline-flex items-center justify-center">
                {{ $pending->count() }}
            </span>
        @endif
    </div>

    @if ($pending->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-8 text-center text-sm text-gray-500 mb-8">
            <i class="ri-inbox-line text-3xl text-gray-300 block mb-2"></i>
            {{ __('No pending requests. New requests from the login page will appear here.') }}
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden mb-8">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Dealership') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Contact') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Message') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Requested') }}</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($pending as $req)
                        <tr class="hover:bg-gray-50 align-top">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $req->company_name }}</td>
                            <td class="px-5 py-3">
                                <p class="text-gray-900">{{ $req->name }}</p>
                                <p class="text-xs text-gray-500">{{ $req->email }}</p>
                                @if ($req->phone)
                                    <p class="text-xs text-gray-500">{{ $req->phone }}</p>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-600 max-w-xs">
                                <p class="text-xs leading-relaxed">{{ $req->message ?: '—' }}</p>
                            </td>
                            <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $req->created_at?->diffForHumans() }}</td>
                            <td class="px-5 py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.account-requests.approve', $req->_id) }}" class="inline"
                                    data-confirm="{{ __('Approve «:name»?', ['name' => $req->company_name]) }}"
                                    data-confirm-text="{{ __('The dealership account will be created and a setup link emailed to :email.', ['email' => $req->email]) }}">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-semibold bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg">
                                        <i class="ri-check-line"></i> {{ __('Approve') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.account-requests.reject', $req->_id) }}" class="inline ml-1"
                                    data-confirm="{{ __('Reject the request from :email?', ['email' => $req->email]) }}"
                                    data-confirm-danger="1">
                                    @csrf
                                    <button type="submit"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium bg-red-50 hover:bg-red-100 text-red-700 rounded-lg">
                                        <i class="ri-close-line"></i> {{ __('Reject') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ============================ HANDLED ============================ --}}
    <h3 class="font-semibold text-gray-900 mb-2">{{ __('Recently handled') }}</h3>
    @if ($handled->isEmpty())
        <div class="bg-white rounded-2xl border border-gray-200 p-6 text-center text-sm text-gray-500">
            {{ __('Nothing handled yet.') }}
        </div>
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 tracking-wide">
                    <tr>
                        <th class="px-5 py-3 font-semibold">{{ __('Dealership') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Contact') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Status') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Handled by') }}</th>
                        <th class="px-5 py-3 font-semibold">{{ __('Date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($handled as $req)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-900">
                                @if ($req->status === \App\Models\AccountRequest::STATUS_APPROVED && $req->created_company_id)
                                    <a href="{{ route('admin.dealers.show', $req->created_company_id) }}" class="text-primary-800 hover:underline">
                                        {{ $req->company_name }}
                                    </a>
                                @else
                                    {{ $req->company_name }}
                                @endif
                            </td>
                            <td class="px-5 py-3">
                                <p class="text-gray-900">{{ $req->name }}</p>
                                <p class="text-xs text-gray-500">{{ $req->email }}</p>
                            </td>
                            <td class="px-5 py-3">
                                @if ($req->status === \App\Models\AccountRequest::STATUS_APPROVED)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[11px] font-semibold">
                                        <i class="ri-checkbox-circle-fill"></i> {{ __('Approved') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-[11px] font-semibold">
                                        <i class="ri-close-circle-fill"></i> {{ __('Rejected') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-700">{{ $req->handled_by_name ?? '—' }}</td>
                            <td class="px-5 py-3 text-xs text-gray-500 whitespace-nowrap">{{ $req->handled_at?->translatedFormat('d M Y, H:i') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-admin-layout>
