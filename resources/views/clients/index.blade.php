<x-app-layout title="Clients" header="Clients">

    @if (session('status'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex flex-col sm:flex-row gap-3 sm:items-center">
            <form action="{{ route('clients.index') }}" method="GET" class="flex-1 max-w-md">
                <div class="relative">
                    <i class="ri-search-line absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input type="search" name="q" value="{{ $q }}"
                        placeholder="Search name, email, phone, city..."
                        class="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:border-primary-800 focus:ring-primary-800 focus:bg-white" />
                </div>
            </form>
            <a href="{{ route('clients.create') }}"
                class="inline-flex items-center gap-2 bg-primary-800 hover:bg-primary-900 text-white font-semibold px-4 py-2 rounded-lg text-sm transition sm:ml-auto">
                <i class="ri-add-line"></i> New client
            </a>
        </div>

        @if ($clients->isEmpty())
            @if ($q)
                <x-app.empty-state
                    icon="ri-search-line"
                    title="No matches"
                    :message="'No clients matched \"' . $q . '\". Try a different search term.'" />
            @else
                <x-app.empty-state
                    icon="ri-user-smile-line"
                    title="No clients yet"
                    message="Add your first client to start building quotes for them."
                    ctaLabel="Add your first client"
                    ctaHref="{{ route('clients.create') }}"
                    size="lg" />
            @endif
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3 text-left font-semibold">Name</th>
                            <th class="px-5 py-3 text-left font-semibold">Email</th>
                            <th class="px-5 py-3 text-left font-semibold">Phone</th>
                            <th class="px-5 py-3 text-left font-semibold">City</th>
                            <th class="px-5 py-3 text-right font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($clients as $c)
                            <tr class="hover:bg-gray-50">
                                <td class="px-5 py-3">
                                    <a href="{{ route('clients.show', $c->_id) }}" class="font-medium text-gray-900 hover:text-primary-800">
                                        {{ $c->full_name }}
                                    </a>
                                    @if ($c->company_name)
                                        <div class="text-xs text-gray-500">{{ $c->company_name }}</div>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-700">{{ $c->email ?: '—' }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $c->phone ?: '—' }}</td>
                                <td class="px-5 py-3 text-gray-700">{{ $c->city ?: '—' }}</td>
                                <td class="px-5 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('clients.show', $c->_id) }}" title="View"
                                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg">
                                            <i class="ri-eye-line"></i>
                                        </a>
                                        <a href="{{ route('clients.edit', $c->_id) }}" title="Edit"
                                            class="w-8 h-8 inline-flex items-center justify-center text-gray-500 hover:text-primary-800 hover:bg-gray-100 rounded-lg">
                                            <i class="ri-pencil-line"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-3 border-t border-gray-100">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
