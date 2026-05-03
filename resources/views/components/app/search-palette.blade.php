<div
    x-data="searchPalette()"
    x-on:open-search.window="open()"
    x-on:keydown.window.prevent.cmd.k="open()"
    x-on:keydown.window.prevent.ctrl.k="open()"
    x-on:keydown.escape.window="close()"
    x-show="isOpen"
    x-cloak
    class="fixed inset-0 z-[60] bg-gray-900/60 flex items-start justify-center pt-[10vh] px-4"
    @click.self="close()">

    <div class="w-full max-w-2xl bg-white rounded-2xl shadow-2xl overflow-hidden border border-gray-200"
        x-trap.inert.noscroll="isOpen">

        {{-- Search input --}}
        <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-100">
            <i class="ri-search-line text-xl text-gray-400"></i>
            <input
                x-ref="input"
                x-model="query"
                @input.debounce.200ms="search()"
                @keydown.down.prevent="navigate(1)"
                @keydown.up.prevent="navigate(-1)"
                @keydown.enter.prevent="goToActive()"
                type="text"
                placeholder="Search quotes, clients, models..."
                class="flex-1 border-0 focus:ring-0 text-base placeholder:text-gray-400 outline-none" />
            <kbd class="text-[10px] font-semibold text-gray-500 bg-gray-100 border border-gray-200 rounded px-1.5 py-0.5">ESC</kbd>
        </div>

        {{-- Results --}}
        <div class="max-h-[60vh] overflow-y-auto">
            {{-- Loading --}}
            <div x-show="loading" class="p-6 text-center text-sm text-gray-500">
                <i class="ri-loader-4-line animate-spin"></i> Searching…
            </div>

            {{-- Empty / prompt states --}}
            <template x-if="! loading && query.length < 2 && ! hasResults()">
                <div class="p-8 text-center">
                    <i class="ri-search-line text-4xl text-gray-300"></i>
                    <p class="text-sm text-gray-600 mt-3">Start typing to search across quotes &amp; clients.</p>
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-2 text-xs text-gray-500">
                        <span><kbd class="font-mono px-1.5 py-0.5 bg-gray-100 border border-gray-200 rounded">↑</kbd> <kbd class="font-mono px-1.5 py-0.5 bg-gray-100 border border-gray-200 rounded">↓</kbd> navigate</span>
                        <span><kbd class="font-mono px-1.5 py-0.5 bg-gray-100 border border-gray-200 rounded">Enter</kbd> open</span>
                        <span><kbd class="font-mono px-1.5 py-0.5 bg-gray-100 border border-gray-200 rounded">Esc</kbd> close</span>
                    </div>
                </div>
            </template>

            <template x-if="! loading && query.length >= 2 && ! hasResults()">
                <div class="p-8 text-center">
                    <i class="ri-search-eye-line text-4xl text-gray-300"></i>
                    <p class="text-sm text-gray-600 mt-3">No matches for <span class="font-medium" x-text="'“' + query + '”'"></span></p>
                </div>
            </template>

            {{-- Quotes group --}}
            <template x-if="! loading && results.quotes.length > 0">
                <div>
                    <div class="px-5 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 bg-gray-50 border-y border-gray-100">
                        Quotes
                    </div>
                    <template x-for="(q, i) in results.quotes" :key="'q-' + q.id">
                        <a :href="q.url"
                            @mouseenter="activeIndex = i"
                            :class="activeIndex === i ? 'bg-primary-50' : 'hover:bg-gray-50'"
                            class="flex items-center gap-3 px-5 py-3 cursor-pointer">
                            <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                                <i class="ri-file-list-3-line"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700" x-text="q.number"></span>
                                    <span class="text-sm font-medium text-gray-900 truncate" x-text="q.client || '—'"></span>
                                </div>
                                <p class="text-xs text-gray-500 truncate" x-text="q.model"></p>
                            </div>
                            <div class="text-right shrink-0">
                                <div class="text-sm font-semibold text-gray-900" x-text="q.amount"></div>
                                <div class="text-xs text-gray-500 capitalize" x-text="q.status"></div>
                            </div>
                            <i class="ri-corner-down-left-line text-gray-300"
                                x-show="activeIndex === i"></i>
                        </a>
                    </template>
                </div>
            </template>

            {{-- Clients group --}}
            <template x-if="! loading && results.clients.length > 0">
                <div>
                    <div class="px-5 py-2 text-[11px] font-semibold uppercase tracking-wider text-gray-500 bg-gray-50 border-y border-gray-100">
                        Clients
                    </div>
                    <template x-for="(c, i) in results.clients" :key="'c-' + c.id">
                        <a :href="c.url"
                            @mouseenter="activeIndex = results.quotes.length + i"
                            :class="activeIndex === results.quotes.length + i ? 'bg-primary-50' : 'hover:bg-gray-50'"
                            class="flex items-center gap-3 px-5 py-3 cursor-pointer">
                            <span class="w-9 h-9 rounded-lg bg-primary-50 text-primary-800 flex items-center justify-center shrink-0">
                                <i class="ri-user-smile-line"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate" x-text="c.name"></p>
                                <p class="text-xs text-gray-500 truncate" x-text="c.sub"></p>
                            </div>
                            <i class="ri-corner-down-left-line text-gray-300"
                                x-show="activeIndex === results.quotes.length + i"></i>
                        </a>
                    </template>
                </div>
            </template>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function searchPalette() {
        return {
            isOpen: false,
            query: '',
            loading: false,
            activeIndex: 0,
            results: { quotes: [], clients: [] },

            open() {
                this.isOpen = true;
                this.query = '';
                this.results = { quotes: [], clients: [] };
                this.activeIndex = 0;
                this.$nextTick(() => this.$refs.input?.focus());
            },

            close() {
                this.isOpen = false;
            },

            hasResults() {
                return this.results.quotes.length > 0 || this.results.clients.length > 0;
            },

            allHits() {
                return [...this.results.quotes, ...this.results.clients];
            },

            navigate(delta) {
                const total = this.allHits().length;
                if (! total) return;
                this.activeIndex = (this.activeIndex + delta + total) % total;
            },

            goToActive() {
                const hit = this.allHits()[this.activeIndex];
                if (hit?.url) window.location.href = hit.url;
            },

            async search() {
                if (this.query.trim().length < 2) {
                    this.results = { quotes: [], clients: [] };
                    return;
                }
                this.loading = true;
                try {
                    const res = await fetch('{{ route('search') }}?q=' + encodeURIComponent(this.query), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin',
                    });
                    const data = await res.json();
                    this.results = data;
                    this.activeIndex = 0;
                } catch (e) {
                    console.error('search failed', e);
                } finally {
                    this.loading = false;
                }
            },
        };
    }
</script>
@endpush
