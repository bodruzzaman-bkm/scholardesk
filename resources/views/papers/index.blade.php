<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('My library') }}
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">
                    {{ $papers->total() }} {{ Str::plural('paper', $papers->total()) }}
                </span>
            </h2>

            <a href="{{ route('papers.create') }}"
               class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-sm font-medium">
                + Add paper
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Reading-status summary, doubling as a one-click filter --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <a href="{{ route('papers.index', array_merge(request()->except(['status', 'page']), [])) }}"
                   class="px-4 py-3 rounded-lg border text-center transition
                          {{ blank($filters['status'] ?? null) ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-indigo-300' }}">
                    <div class="text-xs text-gray-500 dark:text-gray-400">All</div>
                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ array_sum($statusCounts) }}</div>
                </a>

                @foreach ($statuses as $value => $label)
                    <a href="{{ route('papers.index', array_merge(request()->except('page'), ['status' => $value])) }}"
                       class="px-4 py-3 rounded-lg border text-center transition
                              {{ ($filters['status'] ?? null) === $value ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 hover:border-indigo-300' }}">
                        <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $statusCounts[$value] ?? 0 }}</div>
                    </a>
                @endforeach
            </div>

            {{-- Search + filters --}}
            <form method="GET" action="{{ route('papers.index') }}"
                  class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 space-y-3">

                <div class="flex gap-3 flex-wrap">
                    <div class="flex-1 min-w-[240px]">
                        <label for="q" class="sr-only">Search</label>
                        <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                               placeholder="Search title, authors, abstract, venue or DOI…"
                               class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition text-sm font-medium">
                        Search
                    </button>

                    @if (collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty())
                        <a href="{{ route('papers.index') }}"
                           class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Clear
                        </a>
                    @endif
                </div>

                <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                    <select name="tag" class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">All tags</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->id }}" @selected(($filters['tag'] ?? null) == $tag->id)>{{ $tag->name }}</option>
                        @endforeach
                    </select>

                    <select name="collection" class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">All collections</option>
                        @foreach ($collections as $collection)
                            <option value="{{ $collection->id }}" @selected(($filters['collection'] ?? null) == $collection->id)>{{ $collection->name }}</option>
                        @endforeach
                    </select>

                    <select name="year" class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">Any year</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? null) == $year)>{{ $year }}</option>
                        @endforeach
                    </select>

                    {{-- Author filter (requirement 12). Names are split out of
                         the comma-separated `authors` column. --}}
                    <select name="author" class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">Any author</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author }}" @selected(($filters['author'] ?? null) === $author)>
                                {{ Str::limit($author, 28) }}
                            </option>
                        @endforeach
                    </select>

                    <select name="venue" class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">Any venue</option>
                        @foreach ($venues as $venue)
                            <option value="{{ $venue }}" @selected(($filters['venue'] ?? null) === $venue)>{{ Str::limit($venue, 30) }}</option>
                        @endforeach
                    </select>

                    <select name="sort" class="text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm">
                        <option value="">Newest first</option>
                        <option value="oldest" @selected(($filters['sort'] ?? null) === 'oldest')>Oldest first</option>
                        <option value="title" @selected(($filters['sort'] ?? null) === 'title')>Title A–Z</option>
                        <option value="year" @selected(($filters['sort'] ?? null) === 'year')>Publication year</option>
                    </select>
                </div>

                {{-- Preserve the status filter chosen from the cards above --}}
                @if (filled($filters['status'] ?? null))
                    <input type="hidden" name="status" value="{{ $filters['status'] }}">
                @endif
            </form>

            {{-- Results --}}
            @forelse ($papers as $paper)
                @if ($loop->first)
                    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg divide-y divide-gray-100 dark:divide-gray-700">
                @endif

                <div class="p-4 flex gap-4 items-start hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('papers.show', $paper) }}"
                           class="font-medium text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400">
                            {{ $paper->title }}
                        </a>

                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400 truncate">
                            {{ $paper->authors ?: 'Unknown authors' }}
                            @if ($paper->year) · {{ $paper->year }} @endif
                            @if ($paper->venue) · {{ $paper->venue }} @endif
                        </div>

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

                    <div class="flex items-center gap-2 shrink-0">
                        {{-- Settable here, not just on the detail page. --}}
                        <x-reading-status :paper="$paper" size="sm" />

                        @if ($paper->hasPdf())
                            {{-- Labelled "Open PDF", not "Read": a button reading
                                 "Read" beside a status that can also say "Read"
                                 looked like it would mark the paper read. --}}
                            <a href="{{ route('papers.read', $paper) }}"
                               class="text-xs px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition whitespace-nowrap">
                                Open PDF
                            </a>
                        @endif
                    </div>
                </div>

                @if ($loop->last)
                    </div>
                @endif
            @empty
                {{-- Empty state: distinguishes "no papers at all" from "no matches" --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-12 text-center">
                    @if (collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty())
                        <p class="text-gray-600 dark:text-gray-300 font-medium">No papers match these filters.</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Try a broader search or clear the filters.</p>
                        <a href="{{ route('papers.index') }}" class="inline-block mt-4 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Clear filters
                        </a>
                    @else
                        <p class="text-gray-600 dark:text-gray-300 font-medium">No papers yet.</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Add one with a PDF or a DOI to get started.</p>
                        <a href="{{ route('papers.create') }}" class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">
                            + Add your first paper
                        </a>
                    @endif
                </div>
            @endforelse

            @if ($papers->hasPages())
                <div>{{ $papers->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
