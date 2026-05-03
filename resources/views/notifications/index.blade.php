<x-app-layout title="Notifications" header="Notifications">
    @php
        $colorMap = [
            'primary' => 'bg-primary-50 text-primary-800',
            'emerald' => 'bg-emerald-50 text-emerald-700',
            'amber'   => 'bg-amber-50 text-amber-700',
            'red'     => 'bg-red-50 text-red-700',
            'gray'    => 'bg-gray-100 text-gray-700',
        ];
    @endphp

    {{-- Filter tabs + actions --}}
    <div class="mb-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="inline-flex items-center bg-gray-100 rounded-lg p-1 text-sm w-fit">
            <a href="{{ route('notifications.index', ['filter' => 'all']) }}"
                class="px-4 py-1.5 rounded-md font-medium transition {{ $filter === 'all' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                All <span class="ml-1 text-xs text-gray-400">({{ $totalCount }})</span>
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'unread']) }}"
                class="px-4 py-1.5 rounded-md font-medium transition {{ $filter === 'unread' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                Unread <span class="ml-1 text-xs text-gray-400">({{ $unreadCount }})</span>
            </a>
            <a href="{{ route('notifications.index', ['filter' => 'read']) }}"
                class="px-4 py-1.5 rounded-md font-medium transition {{ $filter === 'read' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-600 hover:text-gray-900' }}">
                Read
            </a>
        </div>

        @if ($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium bg-white border border-gray-300 hover:bg-gray-50 text-gray-800 rounded-lg">
                    <i class="ri-check-double-line"></i> Mark all read
                </button>
            </form>
        @endif
    </div>

    @if ($notifications->isEmpty())
        <x-app.empty-state
            icon="ri-notification-off-line"
            title="No notifications"
            message="You're all caught up. New activity in your workspace will appear here."
            size="lg" />
    @else
        <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
            <ul class="divide-y divide-gray-100">
                @foreach ($notifications as $n)
                    @php $iconBg = $colorMap[$n->color ?? 'primary'] ?? $colorMap['primary']; @endphp
                    <li class="hover:bg-gray-50 transition {{ $n->read_at ? '' : 'bg-primary-50/30' }}">
                        <form method="POST" action="{{ route('notifications.read', $n->_id) }}">
                            @csrf
                            <button type="submit" class="w-full text-left px-5 py-4 flex items-start gap-3">
                                <span class="w-10 h-10 shrink-0 rounded-lg {{ $iconBg }} flex items-center justify-center">
                                    <i class="{{ $n->icon ?? 'ri-notification-3-line' }} text-lg"></i>
                                </span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-semibold text-gray-900 truncate">{{ $n->title }}</p>
                                        @if (! $n->read_at)
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-primary-50 text-primary-800 text-[10px] font-bold uppercase tracking-wide shrink-0">
                                                <span class="w-1.5 h-1.5 rounded-full bg-primary-800"></span>New
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-600 mt-0.5">{{ $n->message }}</p>
                                    <p class="text-xs text-gray-400 mt-1">
                                        {{ $n->created_at?->diffForHumans() }}
                                        @if ($n->created_at)
                                            <span class="text-gray-300">·</span>
                                            {{ $n->created_at->format('d M Y, H:i') }}
                                        @endif
                                    </p>
                                </div>
                                @if ($n->link)
                                    <i class="ri-arrow-right-s-line text-gray-400 mt-1 shrink-0"></i>
                                @endif
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
</x-app-layout>
