@props([
    'collection',
    'configured' => false,
])

{{--
    Literature-review draft.

    Requirement 14 is "from selected papers *or* a whole collection", so the
    papers are listed with checkboxes; sending none means the whole collection.
--}}
<x-ai.runtime />

@php($papers = $collection->papers)

<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5"
     x-data="aiReview({
         url: @js(route('ai.collection.review', $collection)),
         allIds: @js($papers->pluck('id')->all()),
     })"
     x-init="init()">

    <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-1 flex items-center gap-2">
        <x-icon name="note" class="w-4 h-4 text-indigo-500 shrink-0" /> Literature review draft
    </h2>

    @unless ($configured)
        <p class="text-xs text-amber-700 dark:text-amber-300 mt-2">
            Add an API key to <code class="font-mono">.env</code> to enable review drafts.
        </p>
    @elseif ($papers->isEmpty())
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
            Add papers to this collection first.
        </p>
    @else
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Choose which papers to include, or leave all ticked to use the whole collection.
            Saved drafts appear below and in the export bundle.
        </p>

        <div class="flex items-center justify-between mb-2">
            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">
                <span x-text="selected.length"></span> of {{ $papers->count() }} selected
            </span>
            <div class="flex gap-2 text-xs">
                <button type="button" @click="selectAll()" class="link">All</button>
                <button type="button" @click="selected = []" class="text-gray-500 dark:text-gray-400 hover:underline">None</button>
            </div>
        </div>

        <div class="max-h-40 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-md p-2 mb-3 space-y-1">
            @foreach ($papers as $paper)
                <label class="flex items-start gap-2 text-xs cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 rounded p-1">
                    <input type="checkbox" value="{{ $paper->id }}" x-model.number="selected"
                           class="mt-0.5 rounded border-gray-300 dark:border-gray-600 text-purple-600 focus:ring-purple-500">
                    <span class="text-gray-700 dark:text-gray-300">
                        {{ Str::limit($paper->title, 70) }}
                        @if ($paper->year)
                            <span class="text-gray-400">({{ $paper->year }})</span>
                        @endif
                    </span>
                </label>
            @endforeach
        </div>

        <div x-show="answer" x-cloak
             class="prose prose-sm prose-indigo dark:prose-invert max-w-none mb-3 max-h-96 overflow-y-auto">
            <div x-html="answer"></div>
        </div>

        <p x-show="error" x-cloak role="alert"
           class="text-xs text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded p-2 mb-3"
           x-text="error"></p>

        <button type="button" @click="run()" :disabled="busy || selected.length === 0"
                class="w-full py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 disabled:opacity-50 transition">
            <span x-show="!busy">
                Generate draft from <span x-text="selected.length"></span>
                <span x-text="selected.length === 1 ? 'paper' : 'papers'"></span>
            </span>
            <span x-show="busy" x-cloak>Drafting…</span>
        </button>

        {{-- A generated draft is persisted, so the page is reloaded to show it
             in the saved-drafts list rather than leaving two copies on screen. --}}
        <p x-show="saved" x-cloak class="mt-2 text-xs text-green-700 dark:text-green-300">
            Saved. <button type="button" @click="window.location.reload()" class="underline">Reload</button>
            to see it in your saved drafts.
        </p>
    @endunless
</div>

@once
    @push('scripts')
    <script>
        function aiReview(config) {
            return {
                ...config,
                selected: [],
                answer: '',
                error: '',
                busy: false,
                saved: false,

                init() {
                    this.selectAll();
                },

                selectAll() {
                    this.selected = [...this.allIds];
                },

                async run() {
                    if (this.selected.length === 0) return;

                    this.busy = true;
                    this.error = '';
                    this.saved = false;
                    try {
                        // Sending every id is equivalent to "the whole
                        // collection"; the server treats an empty list the same.
                        const data = await window.scholardeskAi.post(this.url, { paper_ids: this.selected });
                        this.answer = window.scholardeskAi.markdown(data.content);
                        this.saved = true;
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.busy = false;
                    }
                },
            };
        }
    </script>
    @endpush
@endonce
