@props([
    'paper',
    'configured' => false,
])

{{-- AI summary of one paper: TL;DR, contributions, method, limitations. --}}
<x-ai.runtime />

<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5"
     x-data="aiSummary({ url: @js(route('ai.paper.summary', $paper)), indexed: @js($paper->isIndexed()) })">

    <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm mb-3 flex items-center gap-2">
        <span aria-hidden="true">✨</span> AI summary
    </h2>

    @unless ($configured)
        <p class="text-xs text-amber-700 dark:text-amber-300">
            Add an API key to <code class="font-mono">.env</code> to enable summaries.
        </p>
    @else
        @if (! $paper->isIndexed())
            {{-- Summarising needs extracted text, so say why the button is absent. --}}
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if ($paper->hasNoExtractableText())
                    This PDF has no extractable text (likely a scan), so it cannot be summarised.
                @elseif (! $paper->hasPdf())
                    Upload a PDF to enable summaries.
                @else
                    Index this paper first — see the notice above.
                @endif
            </p>
        @else
            <div x-show="answer" x-cloak
                 class="prose prose-sm prose-indigo dark:prose-invert max-w-none mb-3 max-h-96 overflow-y-auto">
                <div x-html="answer"></div>
            </div>

            <p x-show="error" x-cloak role="alert"
               class="text-xs text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded p-2 mb-3"
               x-text="error"></p>

            <button type="button" @click="run()" :disabled="busy"
                    class="w-full py-2 bg-purple-600 text-white rounded-lg text-sm font-medium hover:bg-purple-700 disabled:opacity-50 transition">
                <span x-show="!busy" x-text="answer ? 'Regenerate summary' : 'Generate summary'"></span>
                <span x-show="busy" x-cloak>Summarising…</span>
            </button>
        @endif
    @endunless
</div>

@once
    @push('scripts')
    <script>
        function aiSummary(config) {
            return {
                ...config,
                answer: '',
                error: '',
                busy: false,

                async run() {
                    this.busy = true;
                    this.error = '';
                    try {
                        const data = await window.scholardeskAi.post(this.url, {});
                        this.answer = window.scholardeskAi.markdown(data.summary);
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
