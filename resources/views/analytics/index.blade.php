{{--
    Analytics dashboard — requirement 21.

    "papers added over time and breakdowns by year, venue, tag, and reading
    status". All five are on this page, in that order.

    The bars are plain divs with a percentage width rather than a charting
    library: it keeps the page dependency-free, works without JavaScript, and
    reads correctly in both themes.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('app.analytics') }}
            </h2>
            <a href="{{ route('papers.index') }}" class="link text-sm">
                {{ __('app.my_library') }} &rarr;
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Headline counts --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-stat-card icon="library" :label="__('app.papers')" :value="$stats['papers']" accent="indigo" />
                <x-stat-card icon="collection" :label="__('app.collections')" :value="$stats['collections']" accent="green" />
                <x-stat-card icon="tag" :label="__('app.tags')" :value="$stats['tags']" accent="purple" />
                <x-stat-card icon="highlight" :label="__('app.highlights_and_notes')" :value="$stats['highlights'] + $stats['notes']" accent="amber" />
            </div>

            {{-- 1. Papers added over time --}}
            <div class="card card-body">
                <h3 class="section-title mb-4">
                    Papers added over time
                </h3>

                @php($maxMonth = $addedByMonth ? max($addedByMonth) : 0)

                @if ($maxMonth === 0)
                    <p class="muted">
                        Nothing added in the last 12 months.
                    </p>
                @else
                    {{-- Column chart: every month in range is present, so a
                         quiet month renders as a gap rather than being skipped
                         and distorting the timeline. --}}
                    <div class="flex items-end gap-1 h-40">
                        @foreach ($addedByMonth as $month => $count)
                            <div class="flex-1 flex flex-col items-center justify-end h-full group">
                                <span class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ $count ?: '' }}</span>
                                <div class="w-full bg-indigo-500 rounded-t transition-all group-hover:bg-indigo-600"
                                     style="height: {{ $maxMonth > 0 ? max(round($count / $maxMonth * 100), $count > 0 ? 4 : 0) : 0 }}%"
                                     title="{{ $month }}: {{ $count }}"></div>
                                <span class="text-[10px] text-gray-400 mt-1 whitespace-nowrap">
                                    {{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('M') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                {{-- 2. By publication year --}}
                <div class="card card-body">
                    <h3 class="section-title mb-4">
                        By publication year
                    </h3>

                    @if (empty($papersByYear))
                        <p class="muted">
                            No papers with a year yet.
                        </p>
                    @else
                        @php($maxYear = max($papersByYear))
                        <div class="space-y-2">
                            @foreach ($papersByYear as $year => $count)
                                <div class="flex items-center gap-3">
                                    <span class="w-12 text-sm text-gray-600 dark:text-gray-400 shrink-0">{{ $year }}</span>
                                    <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                        <div class="h-full rounded-full bg-indigo-500"
                                             style="width: {{ $maxYear > 0 ? round($count / $maxYear * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="w-8 text-sm text-gray-500 dark:text-gray-400 text-right">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- 3. By venue — the breakdown requirement 21 named that the
                     dashboard never had. --}}
                <div class="card card-body">
                    <h3 class="section-title mb-4">
                        By venue
                    </h3>

                    @if (empty($papersByVenue))
                        <p class="muted">
                            No papers with a venue yet. Importing by DOI usually fills this in.
                        </p>
                    @else
                        @php($maxVenue = max($papersByVenue))
                        <div class="space-y-2">
                            @foreach ($papersByVenue as $venue => $count)
                                <div class="flex items-center gap-3">
                                    <span class="w-40 text-sm text-gray-600 dark:text-gray-400 shrink-0 truncate"
                                          title="{{ $venue }}">{{ $venue }}</span>
                                    <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                        <div class="h-full rounded-full bg-emerald-500"
                                             style="width: {{ $maxVenue > 0 ? round($count / $maxVenue * 100) : 0 }}%"></div>
                                    </div>
                                    <span class="w-8 text-sm text-gray-500 dark:text-gray-400 text-right">{{ $count }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- 4. By tag --}}
                <div class="card card-body">
                    <h3 class="section-title mb-4">
                        By tag
                    </h3>

                    @forelse ($topTags as $tag)
                        <div class="flex items-center justify-between py-1.5">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium"
                                  style="background-color: {{ $tag->color }}; color: {{ $tag->contrastingTextColor() }}">
                                {{ $tag->name }}
                            </span>
                            <span class="muted">{{ $tag->papers_count }}</span>
                        </div>
                    @empty
                        <p class="muted">
                            No tags in use yet.
                        </p>
                    @endforelse
                </div>

                {{-- 5. By reading status --}}
                <div class="card card-body">
                    <h3 class="section-title mb-4">
                        By reading status
                    </h3>

                    @php($totalPapers = array_sum($readingStatus))

                    @if ($totalPapers === 0)
                        <p class="muted">
                            No papers yet.
                        </p>
                    @else
                        <div class="space-y-3">
                            @foreach (\App\Enums\ReadingStatus::cases() as $status)
                                @php($count = $readingStatus[$status->value] ?? 0)
                                @php($pct = $totalPapers > 0 ? round($count / $totalPapers * 100) : 0)
                                <div>
                                    <div class="flex justify-between text-sm mb-1">
                                        <span class="text-gray-600 dark:text-gray-400">{{ $status->label() }}</span>
                                        <span class="text-gray-500 dark:text-gray-400">{{ $count }} ({{ $pct }}%)</span>
                                    </div>
                                    <div class="bg-gray-100 dark:bg-gray-700 rounded-full h-3 overflow-hidden">
                                        <div class="h-full rounded-full bg-indigo-500" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
