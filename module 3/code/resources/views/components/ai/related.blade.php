@props(['paper'])

{{--
    Related papers by content similarity.

    Needs no API key: similarity is computed locally by EmbeddingService, so
    this card works even when the language model is unconfigured.
--}}
<x-ai.runtime />

<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5"
     x-data="aiRelated({ url: @js(route('ai.paper.related', $paper)) })"
     x-init="load()">

    <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-3 flex items-center gap-2">
        <span aria-hidden="true">🔗</span> Related in your library
    </h2>

    <p x-show="loading" x-cloak class="text-xs text-gray-500 dark:text-gray-400">Loading…</p>

    <template x-if="!loading && items.length === 0">
        <p class="text-xs text-gray-500 dark:text-gray-400" x-text="message"></p>
    </template>

    <div class="space-y-2">
        <template x-for="r in items" :key="r.id">
            <a :href="r.url"
               class="block p-2 -mx-2 rounded hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <span class="block text-xs font-medium text-gray-800 dark:text-gray-200" x-text="r.title"></span>
                <span class="block text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                    <span x-text="r.authors || 'Unknown authors'"></span>
                    <template x-if="r.year"><span> · <span x-text="r.year"></span></span></template>
                </span>
                {{-- Similarity is shown so the ranking is explainable. --}}
                <span class="block mt-1 h-1 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    <span class="block h-full rounded-full bg-indigo-400"
                          :style="'width: ' + Math.round(Math.max(0, Math.min(1, r.score)) * 100) + '%'"></span>
                </span>
            </a>
        </template>
    </div>
</div>

@once
    @push('scripts')
    <script>
        function aiRelated(config) {
            return {
                ...config,
                items: [],
                loading: false,
                message: 'Nothing related found yet.',

                async load() {
                    this.loading = true;
                    try {
                        const res = await fetch(this.url, { headers: { Accept: 'application/json' } });
                        if (!res.ok) throw new Error();
                        const data = await res.json();
                        this.items = data.related || [];
                        if (this.items.length === 0) {
                            this.message = data.indexed
                                ? 'No similar papers in your library yet — add a few more.'
                                : 'This paper has no indexed text, so similarity cannot be computed.';
                        }
                    } catch (_) {
                        this.message = 'Could not load related papers.';
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
    @endpush
@endonce
