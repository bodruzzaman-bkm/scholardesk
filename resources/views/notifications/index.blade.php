<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('app.notifications') }}
            </h2>

            @if ($notifications->total() > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button type="submit"
                            class="text-sm px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Mark all as read
                    </button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                @forelse ($notifications as $notification)
                    @if ($loop->first) <ul class="divide-y divide-gray-100 dark:divide-gray-700"> @endif

                    <li class="{{ $notification->is_read ? '' : 'bg-indigo-50/50 dark:bg-indigo-900/20' }}">
                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                            @csrf
                            <button type="submit" class="w-full text-left p-4 flex gap-3 items-start hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                <span class="text-lg shrink-0" aria-hidden="true">{{ $notification->type->icon() }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm text-gray-800 dark:text-gray-200">
                                        {{ $notification->message }}
                                    </span>
                                    <span class="block text-xs text-gray-400 mt-0.5">
                                        {{ $notification->created_at->diffForHumans() }}
                                    </span>
                                </span>
                                @unless ($notification->is_read)
                                    <span class="w-2 h-2 rounded-full bg-indigo-500 shrink-0 mt-2" aria-label="Unread"></span>
                                @endunless
                            </button>
                        </form>
                    </li>

                    @if ($loop->last) </ul> @endif
                @empty
                    <div class="p-12 text-center">
                        <p class="text-gray-600 dark:text-gray-300 font-medium">No notifications.</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            You'll be told when someone shares a collection with you or comments on one.
                        </p>
                    </div>
                @endforelse
            </div>

            @if ($notifications->hasPages())
                <div>{{ $notifications->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
