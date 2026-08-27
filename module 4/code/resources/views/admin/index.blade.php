<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('app.admin') }} — system overview
            </h2>
            <div class="flex gap-3 text-sm">
                <a href="{{ route('admin.users') }}" class="link">Users</a>
                <a href="{{ route('admin.comments') }}" class="link">Moderation</a>
                <a href="{{ route('admin.reports') }}" class="link">
                    Reports @if ($stats['open_reports'] > 0)<span class="ml-1 px-1.5 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ $stats['open_reports'] }}</span>@endif
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <x-stat-card label="Users" :value="$stats['users']" accent="indigo" :href="route('admin.users')" />
                <x-stat-card label="Administrators" :value="$stats['admins']" accent="purple" />
                <x-stat-card label="Papers" :value="$stats['papers']" accent="green" />
                <x-stat-card label="Collections" :value="$stats['collections']" accent="amber" />
                <x-stat-card label="Comments" :value="$stats['comments']" accent="indigo" :href="route('admin.comments')" />
                <x-stat-card label="Open reports" :value="$stats['open_reports']" accent="amber" :href="route('admin.reports')" />
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Retrieval health --}}
                <div class="card card-body">
                    <h3 class="section-title mb-4">Index &amp; storage</h3>
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Papers with an indexed PDF</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $stats['indexed_papers'] }} / {{ $stats['papers'] }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Embedded text chunks</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($stats['chunks']) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Uploaded PDFs on disk</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $stats['storage_mb'] }} MB</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Notes / highlights</dt>
                            <dd class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $stats['notes'] }} / {{ $stats['highlights'] }}
                            </dd>
                        </div>
                    </dl>

                    @if ($stats['papers'] > 0)
                        @php($pct = (int) round($stats['indexed_papers'] / max($stats['papers'], 1) * 100))
                        <div class="mt-4">
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                <span>Indexed</span><span>{{ $pct }}%</span>
                            </div>
                            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                                <div class="h-full rounded-full bg-indigo-500" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Most active researchers --}}
                <div class="card card-body">
                    <h3 class="section-title mb-4">Most papers</h3>
                    @forelse ($topUsers as $user)
                        @if ($loop->first) <ul class="divide-y divide-gray-100 dark:divide-gray-700"> @endif
                        <li class="py-2 flex justify-between items-center text-sm">
                            <span class="text-gray-800 dark:text-gray-200 truncate">{{ $user->name }}</span>
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $user->papers_count }}</span>
                        </li>
                        @if ($loop->last) </ul> @endif
                    @empty
                        <p class="muted">No users yet.</p>
                    @endforelse

                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mt-6 mb-2">Newest accounts</h4>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @foreach ($recentUsers as $user)
                            <li class="py-2 flex justify-between items-center text-sm">
                                <span class="text-gray-800 dark:text-gray-200 truncate">{{ $user->name }}</span>
                                <span class="text-xs text-gray-400">{{ $user->created_at->diffForHumans() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
