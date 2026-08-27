<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('app.search') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Mode switch. Keyword matches the words you type; semantic
                 matches meaning via the local embedding index. --}}
            <form method="GET" action="{{ route('search') }}" class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 space-y-4">
                <div class="flex gap-2">
                    <label for="q" class="sr-only">Search</label>
                    <input id="q" type="search" name="q" value="{{ $query }}" autofocus
                           placeholder="{{ $mode === 'semantic' ? 'Describe an idea, e.g. “barriers to rural adoption”' : 'Words in the title, authors, abstract, venue or DOI' }}"
                           class="field flex-1">
                    <button type="submit" class="px-5 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm font-medium transition">
                        Search
                    </button>
                </div>

                <div class="flex gap-4 items-center flex-wrap">
                    <div class="flex gap-1 bg-gray-100 dark:bg-gray-700 p-1 rounded-md">
                        <label class="cursor-pointer">
                            <input type="radio" name="mode" value="keyword" class="sr-only peer" @checked($mode === 'keyword')>
                            <span class="block px-3 py-1 text-sm rounded peer-checked:bg-white dark:peer-checked:bg-gray-800 peer-checked:shadow-sm text-gray-700 dark:text-gray-300">
                                Keyword
                            </span>
                        </label>
                        <label class="cursor-pointer">
                            <input type="radio" name="mode" value="semantic" class="sr-only peer" @checked($mode === 'semantic')>
                            <span class="block px-3 py-1 text-sm rounded peer-checked:bg-white dark:peer-checked:bg-gray-800 peer-checked:shadow-sm text-gray-700 dark:text-gray-300">
                                Semantic
                            </span>
                        </label>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        @if ($mode === 'semantic')
                            Searches the extracted full text of indexed PDFs by meaning. Runs locally — no API key needed.
                        @else
                            Matches the words you typed against paper metadata. Filters below apply.
                        @endif
                    </p>
                </div>

                {{-- Filters only meaningfully apply to keyword mode. --}}
                @if ($mode !== 'semantic')
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 border-t border-gray-200 dark:border-gray-700 pt-3">
                        <select name="tag" class="field">
                            <option value="">All tags</option>
                            @foreach ($tags as $tag)
                                <option value="{{ $tag->id }}" @selected(($filters['tag'] ?? null) == $tag->id)>{{ $tag->name }}</option>
                            @endforeach
                        </select>
                        <select name="status" class="field">
                            <option value="">Any status</option>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="year" class="field">
                            <option value="">Any year</option>
                            @foreach ($years as $year)
                                <option value="{{ $year }}" @selected(($filters['year'] ?? null) == $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                        <select name="author" class="field">
                            <option value="">Any author</option>
                            @foreach ($authors as $author)
                                <option value="{{ $author }}" @selected(($filters['author'] ?? null) === $author)>
                                    {{ Str::limit($author, 28) }}
                                </option>
                            @endforeach
                        </select>
                        <select name="collection" class="field">
                            <option value="">Any collection</option>
                            @foreach ($collections as $collection)
                                <option value="{{ $collection->id }}" @selected(($filters['collection'] ?? null) == $collection->id)>{{ $collection->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </form>

            {{-- Results --}}
            @if ($query === '')
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-12 text-center">
                    <p class="text-gray-600 dark:text-gray-300 font-medium">Search your library.</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        Keyword search finds exact words. Semantic search finds papers about a concept, even when
                        they use different wording.
                    </p>
                </div>
            @elseif ($mode === 'semantic')
                @forelse ($semanticResults as $hit)
                    @if ($loop->first)
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                    @endif

                    <div class="p-4">
                        <div class="flex justify-between items-start gap-4">
                            <a href="{{ route('papers.show', $hit['paper']) }}"
                               class="font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $hit['paper']->title }}
                            </a>
                            @php($weak = \App\Services\VectorSearchService::isWeak($hit['score']))
                            <span class="shrink-0 flex items-center gap-2">
                                {{-- Similarity is lexical, so a thin match is
                                     labelled rather than hidden: the user can
                                     judge it, and a real paper is never lost. --}}
                                @if ($weak)
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"
                                          title="This result shares only a little wording with your query">
                                        weak match
                                    </span>
                                @endif
                                <span class="text-xs font-mono {{ $weak ? 'text-gray-400' : 'text-indigo-600 dark:text-indigo-400' }}"
                                      title="Similarity score">
                                    {{ number_format($hit['score'], 3) }}
                                </span>
                            </span>
                        </div>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $hit['paper']->authors ?: 'Unknown authors' }}
                            @if ($hit['paper']->year) · {{ $hit['paper']->year }} @endif
                        </p>
                        {{-- The matched passage, so the ranking is explainable. --}}
                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300 border-l-4 border-amber-300 dark:border-amber-600 pl-3 bg-amber-50/60 dark:bg-amber-900/20 py-2 rounded-r">
                            {{ $hit['snippet'] }}
                        </p>
                    </div>

                    @if ($loop->last) </div> @endif
                @empty
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-12 text-center">
                        <p class="text-gray-600 dark:text-gray-300 font-medium">No semantic matches.</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Semantic search only covers papers whose PDF text has been indexed. Upload PDFs, or try
                            <a href="{{ route('search', ['q' => $query, 'mode' => 'keyword']) }}" class="link">keyword search</a>.
                        </p>
                    </div>
                @endforelse
            @else
                @forelse ($keywordResults as $paper)
                    @if ($loop->first)
                        <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                    @endif

                    <div class="p-4 flex justify-between items-start gap-4">
                        <div class="min-w-0">
                            <a href="{{ route('papers.show', $paper) }}"
                               class="font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                                {{ $paper->title }}
                            </a>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5 truncate">
                                {{ $paper->authors ?: 'Unknown authors' }}
                                @if ($paper->year) · {{ $paper->year }} @endif
                                @if ($paper->venue) · {{ $paper->venue }} @endif
                            </p>
                            @if ($paper->tags->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach ($paper->tags as $tag)
                                        <span class="px-2 py-0.5 text-[10px] font-medium rounded-full"
                                              style="background-color: {{ $tag->color }}; color: {{ $tag->contrastingTextColor() }};">
                                            {{ $tag->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <span class="px-2 py-1 text-xs rounded-full shrink-0 {{ $paper->reading_status?->badgeClasses() }}">
                            {{ $paper->reading_status?->label() }}
                        </span>
                    </div>

                    @if ($loop->last) </div> @endif
                @empty
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-12 text-center">
                        <p class="text-gray-600 dark:text-gray-300 font-medium">No papers matched “{{ $query }}”.</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Try
                            <a href="{{ route('search', ['q' => $query, 'mode' => 'semantic']) }}" class="link">semantic search</a>,
                            which matches meaning rather than exact words.
                        </p>
                    </div>
                @endforelse

                @if ($keywordResults && $keywordResults->hasPages())
                    <div>{{ $keywordResults->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
