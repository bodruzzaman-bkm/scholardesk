<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Quick Stats Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Total Papers (Clickable) -->
                <a href="{{ route('papers.index') }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6 flex items-center space-x-4 border-l-4 border-indigo-500 hover:bg-indigo-50 dark:hover:bg-gray-700 transition duration-200 cursor-pointer group block">
                    <div class="p-3 bg-indigo-100 dark:bg-indigo-900 rounded-full group-hover:scale-110 transition duration-200">
                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Papers</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_papers'] }}</p>
                    </div>
                </a>

                <!-- Total Collections (Clickable) -->
                <a href="{{ route('collections.index') }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6 flex items-center space-x-4 border-l-4 border-green-500 hover:bg-green-50 dark:hover:bg-gray-700 transition duration-200 cursor-pointer group block">
                    <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full group-hover:scale-110 transition duration-200">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Collections</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_collections'] }}</p>
                    </div>
                </a>

                <!-- Total Notes (Non-clickable) -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6 flex items-center space-x-4 border-l-4 border-yellow-500">
                    <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full">
                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Highlights & Notes</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $stats['total_notes'] }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Continue Reading Section -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b dark:border-gray-700 pb-2">Continue Reading</h3>
                    
                    @if($continueReading)
                        <div class="p-4 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg border border-indigo-100 dark:border-indigo-800">
                            <h4 class="font-semibold text-indigo-900 dark:text-indigo-200 line-clamp-1">{{ $continueReading->title }}</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $continueReading->authors ?? 'Unknown Author' }} | {{ $continueReading->year }}</p>
                            <div class="mt-4 flex justify-between items-center">
                                <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Reading</span>
                                @if($continueReading->file_path)
                                    <a href="{{ route('papers.read', $continueReading->id) }}" class="text-sm bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded transition">
                                        Open Reader &rarr;
                                    </a>
                                @else
                                    <span class="text-sm text-red-500">No PDF uploaded</span>
                                @endif
                            </div>
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-center py-6">You are not reading any papers right now.</p>
                        <div class="text-center">
                            <a href="{{ route('papers.index') }}" class="text-indigo-600 hover:underline">Go to Library</a>
                        </div>
                    @endif
                </div>

                <!-- Reading Status Overview -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4 border-b dark:border-gray-700 pb-2">Reading Progress</h3>
                    
                    <div class="space-y-4 mt-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center"><span class="w-3 h-3 rounded-full bg-yellow-400 mr-2"></span> To Read</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $readingStatus['to_read'] }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center"><span class="w-3 h-3 rounded-full bg-blue-500 mr-2"></span> Currently Reading</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $readingStatus['reading'] }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center"><span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span> Read Completed</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $readingStatus['read'] }}</span>
                        </div>
                    </div>
                    
                    <div class="mt-6 pt-4 border-t dark:border-gray-700">
                        <a href="{{ route('papers.create') }}" class="w-full block text-center bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-gray-200 font-semibold py-2 px-4 rounded transition">
                            + Add New Paper
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>