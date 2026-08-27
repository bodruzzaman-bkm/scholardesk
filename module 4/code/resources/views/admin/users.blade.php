<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('app.admin') }} — users
            </h2>
            <a href="{{ route('admin.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Overview
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            <form method="GET" action="{{ route('admin.users') }}" class="flex gap-2">
                <label for="q" class="sr-only">Search users</label>
                <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="Search by name or email"
                       class="flex-1 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 text-sm">Search</button>
            </form>

            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-left">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Name</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Email</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300 text-right">Papers</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300 text-right">Collections</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Joined</th>
                            <th scope="col" class="px-4 py-3 font-medium text-gray-700 dark:text-gray-300">Role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($users as $user)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3 text-gray-900 dark:text-gray-100">
                                    {{ $user->name }}
                                    @if ($user->id === auth()->id())
                                        <span class="text-xs text-gray-400">(you)</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ $user->papers_count }}</td>
                                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ $user->collections_count }}</td>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $user->created_at->format('j M Y') }}</td>
                                <td class="px-4 py-3">
                                    @if ($user->id === auth()->id())
                                        {{-- No self-demotion: that could lock the last admin out. --}}
                                        <span class="text-xs px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                            {{ $user->role?->label() }}
                                        </span>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.role', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" onchange="this.form.submit()"
                                                    class="text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 rounded">
                                                @foreach ($roles as $value => $label)
                                                    <option value="{{ $value }}" @selected($user->role?->value === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-12 text-center text-gray-500 dark:text-gray-400">
                                    No users matched.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div>{{ $users->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
