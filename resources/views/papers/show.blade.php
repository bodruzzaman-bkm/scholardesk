<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Paper details') }}
            </h2>
            <a href="{{ route('papers.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Back to library
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        {{-- Two columns: the paper itself on the left, AI assistance in a
             right rail — Summary, Ask, Related as separate cards, matching the
             original ScholarDesk reading-desk layout. --}}
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <x-flash />

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2 space-y-6">

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">

                <!-- Title + reading status -->
                <div class="flex justify-between items-start gap-4 border-b border-gray-200 dark:border-gray-700 pb-4 mb-4 flex-wrap">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100 flex-1 min-w-[240px]">
                        {{ $paper->title }}
                    </h1>

                    {{-- Quick status change, so the tracker is usable without
                         opening the full edit form. Same control as the library
                         and the reader. --}}
                    <x-reading-status :paper="$paper" show-label />
                </div>

                <!-- Metadata -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Authors</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $paper->authors ?: 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Year</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $paper->year ?: 'n.d.' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Venue</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $paper->venue ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">DOI / source</p>
                        {{-- A paper imported from a link may have no DOI at all,
                             so the stored URL is offered as the fallback. --}}
                        @if($paper->doi)
                            {{-- rel=noopener because the link opens in a new tab --}}
                            <a href="https://doi.org/{{ $paper->doi }}" target="_blank" rel="noopener noreferrer"
                               class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline break-all">
                                {{ $paper->doi }}
                            </a>
                        @elseif(filled($paper->url))
                            <a href="{{ $paper->url }}" target="_blank" rel="noopener noreferrer"
                               class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline break-all">
                                {{ Str::limit($paper->url, 60) }}
                            </a>
                        @else
                            <p class="font-medium text-gray-900 dark:text-gray-100">—</p>
                        @endif

                        @if(filled($paper->url) && $paper->doi)
                            <a href="{{ $paper->url }}" target="_blank" rel="noopener noreferrer"
                               class="block mt-0.5 text-xs text-gray-500 dark:text-gray-400 hover:underline">
                                View at publisher &rarr;
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Tags + collections -->
                @if ($paper->tags->isNotEmpty() || $paper->collections->isNotEmpty())
                    <div class="flex flex-wrap gap-4 mb-6">
                        @if ($paper->tags->isNotEmpty())
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Tags</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($paper->tags as $tag)
                                        <a href="{{ route('papers.index', ['tag' => $tag->id]) }}"
                                           class="px-2 py-0.5 text-xs font-medium rounded-full"
                                           style="background-color: {{ $tag->color }}; color: {{ $tag->contrastingTextColor() }};">
                                            {{ $tag->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($paper->collections->isNotEmpty())
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-1">Collections</p>
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($paper->collections as $collection)
                                        <a href="{{ route('collections.show', $collection) }}"
                                           class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600">
                                            {{ $collection->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

                <!-- Abstract -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Abstract</h3>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line text-justify">
                        {{ $paper->abstract ?: 'No abstract recorded for this paper.' }}
                    </p>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 mt-8 border-t border-gray-200 dark:border-gray-700 pt-6 flex-wrap items-center">
                    @if($paper->hasPdf())
                        <a href="{{ route('papers.read', $paper) }}"
                           class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                            Read PDF in browser
                        </a>
                    @else
                        <span class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-500 dark:text-gray-400 rounded-md cursor-not-allowed">
                            No PDF uploaded
                        </span>
                    @endif

                    <a href="{{ route('papers.edit', $paper) }}"
                       class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                        Edit details
                    </a>

                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-auto">Cite as:</span>
                    <a href="{{ route('papers.export', ['paper' => $paper, 'format' => 'bibtex']) }}"
                       class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">BibTeX</a>
                    <a href="{{ route('papers.export', ['paper' => $paper, 'format' => 'apa']) }}"
                       class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">APA</a>
                    <a href="{{ route('papers.export', ['paper' => $paper, 'format' => 'text']) }}"
                       class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Text</a>

                    <form method="POST" action="{{ route('papers.destroy', $paper) }}"
                          onsubmit="return confirm('Delete this paper and its PDF? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Delete</button>
                    </form>
                </div>
            </div>

            {{-- Indexing state: a scanned PDF cannot be searched or summarised,
                 which the UI says plainly rather than silently returning nothing. --}}
            @if ($paper->hasPdf() && ! $paper->isIndexed())
                <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-4 flex items-center justify-between gap-4 flex-wrap border-l-4 border-amber-500">
                    <div class="text-sm">
                        @if ($paper->hasNoExtractableText())
                            <p class="font-medium text-gray-900 dark:text-gray-100">No extractable text in this PDF.</p>
                            <p class="text-gray-500 dark:text-gray-400">
                                It is most likely a scanned image. Search and AI need text; OCR is out of scope.
                            </p>
                        @elseif ($paper->index_status === 'error')
                            <p class="font-medium text-gray-900 dark:text-gray-100">Text extraction failed for this PDF.</p>
                            <p class="text-gray-500 dark:text-gray-400">You can retry it below.</p>
                        @else
                            <p class="font-medium text-gray-900 dark:text-gray-100">Not indexed yet.</p>
                            <p class="text-gray-500 dark:text-gray-400">
                                Indexing extracts the text so this paper can be searched semantically.
                            </p>
                        @endif
                    </div>

                    <form method="POST" action="{{ route('papers.reindex', $paper) }}">
                        @csrf
                        <button type="submit"
                                class="px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Index now
                        </button>
                    </form>
                </div>
            @endif

            <!-- Research notes -->
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 border-t-4 border-indigo-500">
                <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                    <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Research notes
                </h3>

                <div class="space-y-6 mb-8">
                    @forelse($paper->notes as $note)
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-600 relative group">
                            <div class="absolute top-4 right-4 flex space-x-3 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
                                <a href="{{ route('notes.edit', $note) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Edit</a>

                                <form action="{{ route('notes.destroy', $note) }}" method="POST"
                                      onsubmit="return confirm('Delete this note?');" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                </form>
                            </div>

                            {{-- Sanitised markdown: raw HTML stripped and unsafe
                                 link schemes neutralised by App\Support\Markdown. --}}
                            <div class="prose prose-indigo dark:prose-invert max-w-none">
                                {!! \App\Support\Markdown::render($note->content) !!}
                            </div>

                            <p class="text-xs text-gray-400 mt-4 block text-right">
                                Added {{ $note->created_at->diffForHumans() }}
                            </p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            No notes yet — write your first one below. Markdown is supported.
                        </p>
                    @endforelse
                </div>

                <form action="{{ route('notes.store', $paper) }}" method="POST">
                    @csrf
                    <div>
                        <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Write a new note (supports Markdown)
                        </label>
                        <textarea id="content" name="content" rows="4" required
                                  class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
                                  placeholder="## Key takeaways&#10;- Point 1&#10;- Point 2&#10;&#10;**Bold** and *italic* supported.">{{ old('content') }}</textarea>
                        <x-input-error :messages="$errors->get('content')" class="mt-2" />
                    </div>

                    <div class="mt-3 text-right">
                        <x-primary-button type="submit">Save note</x-primary-button>
                    </div>
                </form>
            </div>

            </div>{{-- /left column --}}

            {{-- Right rail: AI assistance, one card per capability. Sticky so
                 it stays beside the paper while reading a long abstract or a
                 long list of notes. --}}
            <aside class="space-y-6 lg:sticky lg:top-6">
                <x-ai.summary :paper="$paper" :configured="$aiConfigured" />

                <x-ai.chat
                    scope="paper"
                    :model="$paper"
                    :configured="$aiConfigured"
                    :history="$chatHistory"
                    :ready="$paper->isIndexed()"
                    not-ready-message="This paper has no indexed text yet, so there is nothing to ask about."
                />

                <x-ai.related :paper="$paper" />
            </aside>

            </div>{{-- /grid --}}
        </div>
    </div>
</x-app-layout>
