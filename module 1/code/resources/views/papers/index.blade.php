<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('My library')"
                       :subtitle="$papers->total().' '.Str::plural('paper', $papers->total())">
            <a href="{{ route('papers.create') }}" class="btn btn-md btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                Add paper
            </a>
        </x-page-header>
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
            <form method="GET" action="{{ route('papers.index') }}" class="card card-body space-y-3">

                <div class="flex gap-3 flex-wrap">
                    <div class="flex-1 min-w-[240px]">
                        <label for="q" class="sr-only">Search</label>
                        <input id="q" type="search" name="q" value="{{ $filters['q'] ?? '' }}"
                               placeholder="Search title, authors, abstract, venue or DOI…"
                               class="field">
                    </div>

                    <button type="submit" class="btn btn-md btn-primary">
                        <x-icon name="search" class="w-4 h-4" />
                        Search
                    </button>

                    @if (collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty())
                        <a href="{{ route('papers.index') }}" class="btn btn-md btn-secondary">Clear</a>
                    @endif
                </div>

                <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                    <select name="tag" class="field">
                        <option value="">All tags</option>
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->id }}" @selected(($filters['tag'] ?? null) == $tag->id)>{{ $tag->name }}</option>
                        @endforeach
                    </select>

                    <select name="collection" class="field">
                        <option value="">All collections</option>
                        @foreach ($collections as $collection)
                            <option value="{{ $collection->id }}" @selected(($filters['collection'] ?? null) == $collection->id)>{{ $collection->name }}</option>
                        @endforeach
                    </select>

                    <select name="year" class="field">
                        <option value="">Any year</option>
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected(($filters['year'] ?? null) == $year)>{{ $year }}</option>
                        @endforeach
                    </select>

                    {{-- Author filter (requirement 12). Names are split out of
                         the comma-separated `authors` column. --}}
                    <select name="author" class="field">
                        <option value="">Any author</option>
                        @foreach ($authors as $author)
                            <option value="{{ $author }}" @selected(($filters['author'] ?? null) === $author)>
                                {{ Str::limit($author, 28) }}
                            </option>
                        @endforeach
                    </select>

                    <select name="venue" class="field">
                        <option value="">Any venue</option>
                        @foreach ($venues as $venue)
                            <option value="{{ $venue }}" @selected(($filters['venue'] ?? null) === $venue)>{{ Str::limit($venue, 30) }}</option>
                        @endforeach
                    </select>

                    <select name="sort" class="field">
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
                    <div class="card divide-y divide-gray-100 dark:divide-gray-700/60 overflow-hidden">
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
                            <a href="{{ route('papers.read', $paper) }}" class="btn btn-sm btn-primary whitespace-nowrap">
                                <x-icon name="book-open" class="w-3.5 h-3.5" />
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
                <div class="card">
                    @if (collect($filters)->filter(fn ($v) => filled($v))->isNotEmpty())
                        <x-empty-state icon="search"
                                       title="No papers match these filters."
                                       description="Try a broader search, or clear the filters to see your whole library.">
                            <a href="{{ route('papers.index') }}" class="btn btn-md btn-secondary">Clear filters</a>
                        </x-empty-state>
                    @else
                        <x-empty-state icon="library"
                                       title="No papers yet."
                                       description="Add one with a PDF, a DOI, or a link to the article page."
                                       action-label="Add your first paper"
                                       :action-href="route('papers.create')" />
                    @endif
                </div>
            @endforelse

            @if ($papers->hasPages())
                <div>{{ $papers->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
