<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Paper Details') }}
            </h2>
            <a href="{{ route('papers.index') }}" class="text-sm text-indigo-600 hover:underline">
                &larr; Back to Library
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                
                <!-- Paper Title & Reading Status -->
                <div class="flex justify-between items-start border-b border-gray-200 dark:border-gray-700 pb-4 mb-4">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ $paper->title }}
                    </h1>
                    <span class="px-3 py-1 text-sm rounded-full bg-blue-100 text-blue-800 capitalize">
                        {{ $paper->reading_status }}
                    </span>
                </div>

                <!-- Paper Metadata (Authors, Year, Venue) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Authors</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $paper->authors ?? 'Unknown' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Year</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $paper->year ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">Venue</p>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $paper->venue ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500 dark:text-gray-400">DOI / URL</p>
                        @if($paper->doi)
                            <a href="{{ Str::startsWith($paper->doi, 'http') ? $paper->doi : 'https://doi.org/' . $paper->doi }}" target="_blank" class="font-medium text-indigo-600 hover:underline">
                                {{ $paper->doi }}
                            </a>
                        @else
                            <p class="font-medium text-gray-900 dark:text-gray-100">N/A</p>
                        @endif
                    </div>
                </div>

                <!-- Abstract -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2">Abstract</h3>
                    <p class="text-gray-700 dark:text-gray-300 whitespace-pre-line text-justify">
                        {{ $paper->abstract ? preg_replace('/^Abstract\s*/i', '', strip_tags($paper->abstract)) : 'No abstract available for this paper.' }}
                    </p>
                </div>

                <!-- Actions (Read PDF & Edit) -->
                <div class="flex space-x-4 mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                    @if($paper->file_path)
                        <!-- Link to view the PDF file stored in public storage -->
                        <a href="{{ route('papers.read', $paper->id) }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 transition">
                            Read PDF in Browser
                        </a>
                    @else
                        <button disabled class="px-4 py-2 bg-gray-400 text-white rounded-md cursor-not-allowed">
                            No PDF Uploaded
                        </button>
                    @endif
                    
                    <a href="{{ route('papers.edit', $paper->id) }}" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                        Edit Details
                    </a>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>