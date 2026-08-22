@props([
    'comment',
    'collection',
    'canReply' => false,
    'isReply' => false,
])

<div {{ $attributes->merge(['class' => 'border border-gray-200 dark:border-gray-700 rounded-lg p-4 '.($isReply ? 'bg-white dark:bg-gray-800' : 'bg-gray-50 dark:bg-gray-700/40')]) }}>
    <div class="flex justify-between items-start gap-3">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                {{ $comment->user?->name ?? 'Unknown' }}
                <span class="text-xs font-normal text-gray-400 ml-1">{{ $comment->created_at->diffForHumans() }}</span>
                @if ($comment->created_at != $comment->updated_at && ! $comment->is_hidden)
                    <span class="text-xs font-normal text-gray-400">· edited</span>
                @endif
            </p>
        </div>

        {{-- Author-only controls; @can defers to CommentPolicy. --}}
        <div class="flex gap-2 shrink-0">
            @can('delete', $comment)
                <form method="POST" action="{{ route('comments.destroy', $comment) }}"
                      onsubmit="return confirm('Delete this comment?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- A hidden comment leaves a tombstone so replies keep their context. --}}
    <p class="mt-2 text-sm whitespace-pre-line {{ $comment->is_hidden ? 'italic text-gray-400' : 'text-gray-700 dark:text-gray-300' }}">
        {{ $comment->displayContent() }}
    </p>

    @can('update', $comment)
        @unless ($comment->is_hidden)
            <details class="mt-2">
                <summary class="text-xs text-gray-500 dark:text-gray-400 cursor-pointer hover:underline">Edit</summary>
                <form method="POST" action="{{ route('comments.update', $comment) }}" class="mt-2">
                    @csrf
                    @method('PUT')
                    <textarea name="content" rows="2" required maxlength="5000"
                              class="block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm">{{ $comment->content }}</textarea>
                    <button type="submit" class="mt-2 px-3 py-1 text-xs bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-800 rounded-md">Save</button>
                </form>
            </details>
        @endunless
    @endcan

    {{-- Replies, one level deep: threads stay readable and cannot nest forever. --}}
    @if ($comment->replies->isNotEmpty())
        <div class="mt-3 ml-4 pl-4 border-l-2 border-gray-200 dark:border-gray-600 space-y-3">
            @foreach ($comment->replies as $reply)
                <x-comment :comment="$reply" :collection="$collection" :can-reply="false" :is-reply="true" />
            @endforeach
        </div>
    @endif

    @if ($canReply && ! $isReply)
        <details class="mt-3">
            <summary class="text-xs text-indigo-600 dark:text-indigo-400 cursor-pointer hover:underline">Reply</summary>
            <form method="POST" action="{{ route('comments.store', $collection) }}" class="mt-2">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <textarea name="content" rows="2" required maxlength="5000" placeholder="Write a reply…"
                          class="block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm"></textarea>
                <button type="submit" class="mt-2 px-3 py-1 text-xs bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Reply</button>
            </form>
        </details>
    @endif
</div>
