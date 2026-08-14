<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Collection: {{ $collection->name }}
            </h2>
            <a href="{{ route('collections.index') }}" class="text-sm text-indigo-600 hover:underline">
                &larr; Back to Collections
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <div class="mb-6">
                    <p class="text-gray-600 dark:text-gray-400">{{ $collection->description ?? 'No description provided for this collection.' }}</p>
                </div>

                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                    Papers in this Collection ({{ $collection->papers->count() }})
                </h3>

                @if($collection->papers->isEmpty())
                    <p class="text-gray-500 dark:text-gray-400">There are no papers in this collection yet. Go to your library to add some!</p>
                @else
                    <div class="space-y-4">
                        @foreach($collection->papers as $paper)
                            <div class="flex justify-between items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700 hover:shadow-sm transition">
                                <div class="pr-4">
                                    <div class="flex items-center space-x-3 mb-1">
                                        <h4 class="font-semibold text-md text-gray-900 dark:text-gray-100">
                                            {{ $paper->title }}
                                        </h4>
                                        
                                        <!-- Reading Status Badge -->
                                        @if($paper->reading_status === 'to read')
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-800 capitalize whitespace-nowrap">To Read</span>
                                        @elseif($paper->reading_status === 'reading')
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 capitalize whitespace-nowrap">Reading</span>
                                        @elseif($paper->reading_status === 'read')
                                            <span class="px-2 py-0.5 text-xs rounded-full bg-green-100 text-green-800 capitalize whitespace-nowrap">Read</span>
                                        @endif
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-300">
                                        {{ $paper->authors ?? 'Unknown Author' }} | {{ $paper->year ?? 'N/A' }}
                                    </p>
                                </div>
                                
                                <div class="flex items-center space-x-4 ml-4">
                                    <a href="{{ route('papers.show', $paper->id) }}" class="text-sm text-indigo-600 hover:underline whitespace-nowrap">View Paper</a>
                                    
                                    <!-- Remove from Collection Form -->
                                    <form action="{{ route('collections.papers.remove', ['collection' => $collection->id, 'paper' => $paper->id]) }}" method="POST" onsubmit="return confirm('Are you sure you want to remove this paper from this collection?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:underline whitespace-nowrap">Remove</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>