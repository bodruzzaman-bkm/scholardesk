<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('app.admin') }} — moderation
            </h2>
            <a href="{{ route('admin.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Overview
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            <p class="text-sm text-gray-500 dark:text-gray-400">
                Hiding a comment leaves a tombstone in the thread so replies keep their context, and can be undone.
            </p>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                @forelse ($comments as $comment)
                    @if ($loop->first) <ul class="divide-y divide-gray-100 dark:divide-gray-700"> @endif

                    <li class="p-4 flex justify-between gap-4 items-start {{ $comment->is_hidden ? 'bg-gray-50 dark:bg-gray-700/30' : '' }}">
                        <div class="min-w-0">
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $comment->user?->name ?? 'Unknown' }}</span>
                                in
                                {{-- A comment anchors to a collection or, since requirement 18,
                                     to a paper alone. Linking collection_id unconditionally
                                     threw a routing error on every paper-only comment. --}}
                                @if ($comment->collection)
                                    <a href="{{ route('collections.show', $comment->collection) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ $comment->collection->name }}
                                    </a>
                                @elseif ($comment->paper)
                                    <a href="{{ route('papers.show', $comment->paper) }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ Str::limit($comment->paper->title, 60) }}
                                    </a>
                                @else
                                    <span class="italic">a deleted item</span>
                                @endif
                                · {{ $comment->created_at->diffForHumans() }}
                            </p>
                            <p class="mt-1 text-sm whitespace-pre-line {{ $comment->is_hidden ? 'italic text-gray-400' : 'text-gray-800 dark:text-gray-200' }}">
                                {{ Str::limit($comment->content, 400) }}
                            </p>
                            @if ($comment->is_hidden)
                                <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded-full bg-amber-100 dark:bg-amber-900/40 text-amber-800 dark:text-amber-300">
                                    Hidden
                                </span>
                            @endif
                        </div>

                        <div class="flex flex-col gap-2 shrink-0">
                            <form method="POST" action="{{ route('admin.comments.visibility', $comment) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="w-full px-3 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    {{ $comment->is_hidden ? 'Restore' : 'Hide' }}
                                </button>
                            </form>

                            <form method="POST" action="{{ route('comments.destroy', $comment) }}"
                                  onsubmit="return confirm('Permanently delete this comment?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full px-3 py-1.5 text-xs text-red-600 dark:text-red-400 hover:underline">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </li>

                    @if ($loop->last) </ul> @endif
                @empty
                    <div class="p-12 text-center">
                        <p class="text-gray-600 dark:text-gray-300 font-medium">No comments anywhere yet.</p>
                    </div>
                @endforelse
            </div>

            @if ($comments->hasPages())
                <div>{{ $comments->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
