<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="__('Dashboard')">
            <a href="{{ route('papers.create') }}" class="btn btn-md btn-primary">
                <x-icon name="plus" class="w-4 h-4" />
                Add paper
            </a>
        </x-page-header>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Headline stats --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <x-stat-card icon="library" label="Papers" :value="$stats['papers']" accent="indigo" :href="route('papers.index')" />
                <x-stat-card icon="collection" label="Collections" :value="$stats['collections']" accent="green" :href="route('collections.index')" />
                <x-stat-card icon="tag" label="Tags" :value="$stats['tags']" accent="purple" :href="route('tags.index')" />
                {{-- Counts highlights AND notes; the old dashboard counted only
                     highlights while labelling the card "Highlights & notes". --}}
                <x-stat-card icon="highlight" label="Highlights &amp; notes" :value="$stats['highlights'] + $stats['notes']" accent="amber" />
            </div>

            {{-- Ask across the whole library. Placed on the dashboard because
                 gating cross-paper Q&A behind "first create a collection" made
                 the product's headline feature undiscoverable. --}}
            <x-ai.chat
                scope="library"
                :configured="$aiConfigured"
                :ready="$indexedPapers > 0"
                not-ready-message="No papers have indexed text yet, so there is nothing to search. Upload a PDF, or open a paper and choose “Index now”."
            />

            @if ($aiConfigured && $indexedPapers === 0 && $stats['papers'] > 0)
                <div class="text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                    None of your papers have indexed text yet, so the assistant has nothing to search.
                    Open a paper and choose <span class="font-medium">Index now</span>, or upload a PDF —
                    text is extracted automatically.
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                {{-- Continue reading --}}
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                        Continue reading
                    </h3>

                    @if ($continueReading)
                        <div class="bg-indigo-50 dark:bg-indigo-900/30 rounded-lg p-4">
                            <a href="{{ route('papers.show', $continueReading) }}"
                               class="font-semibold text-indigo-900 dark:text-indigo-100 hover:underline">
                                {{ $continueReading->title }}
                            </a>
                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">
                                {{ $continueReading->authors ?: 'Unknown authors' }}
                                @if ($continueReading->year) · {{ $continueReading->year }} @endif
                            </p>
                            <div class="mt-4 flex justify-between items-center">
                                <span class="px-2 py-1 text-xs rounded-full {{ $continueReading->reading_status?->badgeClasses() }}">
                                    {{ $continueReading->reading_status?->label() }}
                                </span>
                                @if ($continueReading->hasPdf())
                                    <a href="{{ route('papers.read', $continueReading) }}"
                                       class="btn btn-md btn-primary">
                                        Open reader &rarr;
                                    </a>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="muted">
                            Nothing in progress. Set a paper's status to <em>Reading</em> and it will appear here.
                        </p>
                    @endif

                    {{-- Recently added --}}
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-6 mb-2">Recently added</h4>
                    @forelse ($recentPapers as $paper)
                        @if ($loop->first) <ul class="divide-y divide-gray-100 dark:divide-gray-700"> @endif
                        <li class="py-2 flex justify-between items-center gap-3">
                            <a href="{{ route('papers.show', $paper) }}"
                               class="text-sm text-gray-800 dark:text-gray-200 hover:text-indigo-600 dark:hover:text-indigo-400 truncate">
                                {{ $paper->title }}
                            </a>
                            <span class="text-xs text-gray-400 whitespace-nowrap">{{ $paper->created_at->diffForHumans() }}</span>
                        </li>
                        @if ($loop->last) </ul> @endif
                    @empty
                        <p class="muted">
                            No papers yet —
                            <a href="{{ route('papers.create') }}" class="link">add your first</a>.
                        </p>
                    @endforelse
                </div>

                {{-- Reading progress --}}
                <div class="card card-body">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                        Reading progress
                    </h3>

                    @php($totalPapers = array_sum($readingStatus))
                    <div class="space-y-3">
                        @foreach (\App\Enums\ReadingStatus::cases() as $status)
                            @php($count = $readingStatus[$status->value] ?? 0)
                            @php($pct = $totalPapers > 0 ? round($count / $totalPapers * 100) : 0)
                            <a href="{{ route('papers.index', ['status' => $status->value]) }}" class="block group">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-700 dark:text-gray-300 group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                        {{ $status->label() }}
                                    </span>
                                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $count }}</span>
                                </div>
                                {{-- Progress bar doubles as the proportion chart --}}
                                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                    <div class="h-full rounded-full bg-indigo-500" style="width: {{ $pct }}%"></div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <a href="{{ route('papers.create') }}"
                       class="mt-6 block text-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 rounded-md hover:bg-gray-200 dark:hover:bg-gray-600 transition text-sm font-medium">
                        + Add new paper
                    </a>
                </div>
            </div>

            {{-- Tags + publication years --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="card card-body">
                    <h3 class="section-title mb-4">Most-used tags</h3>
                    @forelse ($topTags as $tag)
                        @if ($loop->first) <div class="space-y-2"> @endif
                        <a href="{{ route('papers.index', ['tag' => $tag->id]) }}" class="flex items-center gap-3 group">
                            <span class="w-3 h-3 rounded-full shrink-0" style="background-color: {{ $tag->color }}"></span>
                            <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-indigo-600 dark:group-hover:text-indigo-400 flex-1 truncate">
                                {{ $tag->name }}
                            </span>
                            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $tag->papers_count }}</span>
                        </a>
                        @if ($loop->last) </div> @endif
                    @empty
                        <p class="muted">
                            No tags in use yet —
                            <a href="{{ route('tags.index') }}" class="link">create one</a>.
                        </p>
                    @endforelse
                </div>

                <div class="card card-body">
                    <h3 class="section-title mb-4">Papers by publication year</h3>
                    @if (empty($papersByYear))
                        <p class="muted">
                            No publication years recorded yet. Add a DOI and the year is fetched automatically.
                        </p>
                    @else
                        @php($maxYearCount = max($papersByYear))
                        <div class="space-y-2">
                            @foreach ($papersByYear as $year => $count)
                                <a href="{{ route('papers.index', ['year' => $year]) }}" class="flex items-center gap-3 group">
                                    <span class="text-xs text-gray-500 dark:text-gray-400 w-10 shrink-0">{{ $year }}</span>
                                    <div class="flex-1 h-4 rounded bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                        <div class="h-full rounded bg-indigo-400 group-hover:bg-indigo-500 transition"
                                             style="width: {{ $maxYearCount > 0 ? round($count / $maxYearCount * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium text-gray-900 dark:text-gray-100 w-6 text-right">{{ $count }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
