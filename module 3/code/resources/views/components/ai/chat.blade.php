@props([
    'scope',              // 'paper' | 'collection' | 'library'
    'model' => null,
    'configured' => false,
    'history' => null,    // previously persisted turns
    'title' => null,
    'ready' => true,      // false when there is nothing indexed to search
    'notReadyMessage' => null,
])

@php
    $askUrl = match ($scope) {
        'paper' => route('ai.paper.ask', $model),
        'collection' => route('ai.collection.ask', $model),
        default => route('ai.ask'),
    };

    $heading = $title ?? match ($scope) {
        'paper' => 'Ask this paper',
        'collection' => 'Ask this collection',
        default => 'Ask your library',
    };

    $placeholder = match ($scope) {
        'paper' => 'What method did the authors use?',
        'collection' => 'What barriers recur across these papers?',
        default => 'What themes recur across my papers?',
    };

    // Persisted turns, reshaped for the client.
    $seed = collect($history ?? [])->map(fn ($m) => [
        'role' => $m['role'] ?? $m->role,
        'content' => $m['content'] ?? $m->content,
        'citations' => $m['citations'] ?? $m->citations ?? [],
    ])->values();
@endphp

<x-ai.runtime />

<div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-5"
     x-data="aiChat({
         url: @js($askUrl),
         seed: @js($seed),
     })"
     x-init="init()">

    <div class="flex items-center justify-between mb-3">
        <h2 class="font-semibold text-gray-900 dark:text-gray-100 text-sm flex items-center gap-2">
            <span aria-hidden="true">💬</span> {{ $heading }}
        </h2>
        <button type="button" x-show="messages.length" x-cloak @click="messages = []"
                class="text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
            Clear
        </button>
    </div>

    @unless ($configured)
        <p class="text-xs text-amber-700 dark:text-amber-300">
            Add an API key to <code class="font-mono">.env</code> to enable questions.
        </p>
    @elseif (! $ready)
        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ $notReadyMessage ?? 'Nothing is indexed yet, so there is nothing to search.' }}
        </p>
    @else
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Answers are grounded in
            {{ $scope === 'paper' ? 'this paper' : ($scope === 'collection' ? 'this collection' : 'your indexed papers') }}
            and cite their source.
        </p>

        {{-- Transcript. Turns accumulate rather than replacing one another, so
             follow-up questions read as a conversation. --}}
        <div x-ref="transcript" x-show="messages.length" x-cloak
             class="space-y-3 mb-3 max-h-80 overflow-y-auto pr-1">
            <template x-for="(m, i) in messages" :key="i">
                <div>
                    <template x-if="m.role === 'user'">
                        <div class="flex justify-end">
                            <p class="inline-block max-w-[85%] text-xs bg-indigo-50 dark:bg-indigo-900/40 text-indigo-900 dark:text-indigo-100 rounded-lg px-3 py-2 text-right"
                               x-text="m.content"></p>
                        </div>
                    </template>

                    <template x-if="m.role === 'assistant'">
                        <div>
                            <div class="prose prose-sm prose-indigo dark:prose-invert max-w-none"
                                 x-html="window.scholardeskAi.markdown(m.content)"></div>

                            {{-- Citation chips: the marginalia motif, linking back. --}}
                            <template x-if="m.citations && m.citations.length">
                                <div class="mt-2 space-y-1">
                                    <template x-for="c in m.citations" :key="c.paper_id">
                                        <a :href="'/papers/' + c.paper_id"
                                           class="block text-xs border-l-4 border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-900/20 pl-2 py-1 rounded-r hover:bg-amber-100 dark:hover:bg-amber-900/40 transition">
                                            <span class="font-mono text-amber-800 dark:text-amber-300" x-text="c.label"></span>
                                            <span class="text-gray-800 dark:text-gray-200" x-text="c.title"></span>
                                        </a>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="m.role === 'error'">
                        <p class="text-xs text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded p-2"
                           x-text="m.content"></p>
                    </template>
                </div>
            </template>
        </div>

        <div x-show="busy" x-cloak class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400 mb-2">
            <svg class="animate-spin h-3 w-3" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"></path>
            </svg>
            Thinking…
        </div>

        <form @submit.prevent="ask()" class="flex gap-2">
            <label for="ai-chat-{{ $scope }}" class="sr-only">{{ $heading }}</label>
            <input id="ai-chat-{{ $scope }}" type="text" x-model="question" :disabled="busy"
                   placeholder="{{ $placeholder }}"
                   class="flex-1 text-xs border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-lg shadow-sm focus:ring-purple-500 focus:border-purple-500">
            <button type="submit" :disabled="busy || question.trim().length < 3"
                    class="px-3 py-2 bg-purple-600 text-white rounded-lg text-xs font-medium hover:bg-purple-700 disabled:opacity-50 transition">
                Ask
            </button>
        </form>
    @endunless
</div>

@once
    @push('scripts')
    <script>
        function aiChat(config) {
            return {
                ...config,
                messages: [],
                question: '',
                busy: false,

                init() {
                    // Restore the persisted conversation, so a reload does not
                    // discard the discussion.
                    this.messages = (this.seed || []).map((m) => ({
                        role: m.role,
                        content: m.content,
                        citations: m.citations || [],
                    }));
                    this.$nextTick(() => this.scrollDown());
                },

                scrollDown() {
                    const box = this.$refs.transcript;
                    if (box) box.scrollTop = box.scrollHeight;
                },

                async ask() {
                    const question = this.question.trim();
                    if (question.length < 3 || this.busy) return;

                    this.messages.push({ role: 'user', content: question, citations: [] });
                    this.question = '';
                    this.busy = true;
                    this.$nextTick(() => this.scrollDown());

                    try {
                        const data = await window.scholardeskAi.post(this.url, { question });
                        this.messages.push({
                            role: 'assistant',
                            content: data.answer,
                            citations: data.citations || [],
                        });
                    } catch (e) {
                        this.messages.push({ role: 'error', content: e.message, citations: [] });
                    } finally {
                        this.busy = false;
                        this.$nextTick(() => this.scrollDown());
                    }
                },
            };
        }
    </script>
    @endpush
@endonce
