{{--
    The moderation queue — requirement 22, "moderate reported content".

    Open reports are the default view because they are the work; the resolved
    and dismissed tabs are the audit trail.
--}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('app.reports') }}
                @if ($openCount > 0)
                    <span class="ml-2 px-2 py-0.5 text-xs rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
                        {{ $openCount }} open
                    </span>
                @endif
            </h2>
            <a href="{{ route('admin.index') }}" class="link text-sm">
                &larr; {{ __('app.admin') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <x-flash />

            {{-- Status tabs --}}
            <div class="flex gap-2 mb-4 text-sm">
                @foreach (\App\Enums\ReportStatus::cases() as $case)
                    <a href="{{ route('admin.reports', ['status' => $case->value]) }}"
                       class="px-3 py-1.5 rounded-md {{ $status === $case->value
                            ? 'bg-indigo-600 text-white'
                            : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                        {{ $case->label() }}
                    </a>
                @endforeach
            </div>

            <div class="card overflow-hidden">
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse ($reports as $report)
                        <li class="p-4">
                            <div class="flex justify-between gap-4 items-start">
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $report->status->badgeClasses() }}">
                                            {{ $report->status->label() }}
                                        </span>
                                        <span class="ml-2 font-medium text-gray-700 dark:text-gray-300">
                                            {{ $report->targetLabel() }}
                                        </span>
                                        reported by
                                        <span class="font-medium text-gray-700 dark:text-gray-300">
                                            {{ $report->user?->name ?? 'a deleted account' }}
                                        </span>
                                        · {{ $report->created_at->diffForHumans() }}
                                    </p>

                                    <p class="mt-2 text-sm text-gray-800 dark:text-gray-200">
                                        <span class="text-gray-500 dark:text-gray-400">Reason:</span>
                                        {{ $report->reason }}
                                    </p>

                                    {{-- The reported item. It can be gone — deleted by its
                                         author after the report — and the queue must still
                                         render rather than throw on a null relation. --}}
                                    <div class="mt-2 pl-3 border-l-2 border-gray-200 dark:border-gray-600">
                                        @if ($report->reportable instanceof \App\Models\Comment)
                                            <p class="text-sm text-gray-600 dark:text-gray-400 italic">
                                                “{{ Str::limit($report->reportable->content, 200) }}”
                                            </p>
                                            <div class="mt-1 flex gap-3 text-xs">
                                                <form method="POST" action="{{ route('admin.comments.visibility', $report->reportable) }}">
                                                    @csrf @method('PATCH')
                                                    <button type="submit" class="text-amber-700 dark:text-amber-400 hover:underline">
                                                        {{ $report->reportable->is_hidden ? 'Restore comment' : 'Hide comment' }}
                                                    </button>
                                                </form>
                                            </div>
                                        @elseif ($report->reportable instanceof \App\Models\Paper)
                                            <a href="{{ route('papers.show', $report->reportable) }}"
                                               class="link text-sm">
                                                {{ Str::limit($report->reportable->title, 100) }}
                                            </a>
                                        @else
                                            <p class="text-sm text-gray-400 italic">
                                                The reported content has since been deleted.
                                            </p>
                                        @endif
                                    </div>

                                    @if (! $report->isOpen())
                                        <p class="mt-2 text-xs text-gray-400">
                                            {{ $report->status->label() }} by
                                            {{ $report->resolver?->name ?? 'an administrator' }}
                                            {{ $report->resolved_at?->diffForHumans() }}
                                        </p>
                                    @endif
                                </div>

                                @if ($report->isOpen())
                                    <div class="flex flex-col gap-2 shrink-0">
                                        <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="resolved">
                                            <button type="submit"
                                                    class="btn btn-sm w-full bg-green-600 text-white shadow-sm hover:bg-green-700">
                                                Resolve
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.reports.resolve', $report) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status" value="dismissed">
                                            <button type="submit"
                                                    class="btn btn-sm btn-secondary w-full">
                                                Dismiss
                                            </button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </li>
                    @empty
                        <li class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">
                            Nothing here. @if ($status === 'open') No open reports — the queue is clear. @endif
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="mt-4">
                {{ $reports->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
