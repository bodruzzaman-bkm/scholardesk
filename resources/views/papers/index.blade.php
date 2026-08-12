<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('My Library') }}
            </h2>
            <a href="{{ route('papers.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                + Add Paper
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    @if($papers->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-500 dark:text-gray-400">Your library is empty. Start by adding some papers!</p>
                        </div>
                    @else
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-300 dark:border-gray-700">
                                    <th class="py-3 px-4">Title & Authors</th>
                                    <th class="py-3 px-4">Year</th>
                                    <th class="py-3 px-4">Status</th>
                                    <th class="py-3 px-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($papers as $paper)
                                    <tr class="border-b border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="py-3 px-4">
                                            <div class="font-semibold">{{ $paper->title }}</div>
                                            <div class="text-sm text-gray-500">{{ $paper->authors ?? 'Unknown Author' }}</div>
                                        </td>
                                        <td class="py-3 px-4">{{ $paper->year ?? 'N/A' }}</td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800 capitalize">
                                                {{ $paper->reading_status }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-right flex justify-end space-x-3">
                                        <!-- View & Edit buttons (Not active yet) -->
                                        <a href="#" class="text-sm text-indigo-600 hover:underline">View</a>
                                        <a href="{{ route('papers.edit', $paper->id) }}" class="text-sm text-green-600 hover:underline">Edit</a>
                                        <!-- Delete Form -->
                                        <form action="{{ route('papers.destroy', $paper->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this paper?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>