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
            <div
  class="mt-8 bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 border-t-4 border-indigo-500"
>
<h3
    class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center"
>
    <svg
        class="w-6 h-6 mr-2"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
    >
        <path
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2"
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
        ></path>
    </svg>

    Research Notes
</h3>
<!-- List Existing Notes -->
<div class="space-y-6 mb-8">
    @forelse($paper->notes as $note)

    <div
        class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-5 border border-gray-200 dark:border-gray-600 relative group"
    >

        <!-- Note Actions (Edit/Delete) -->
        <div
            class="absolute top-4 right-4 flex space-x-3 opacity-0 group-hover:opacity-100 transition-opacity"
        >
            <a
                href="{{ route('notes.edit', $note->id) }}"
                class="text-sm text-blue-600 hover:text-blue-800"
            >
                Edit
            </a>

            <form
                action="{{ route('notes.destroy', $note->id) }}"
                method="POST"
                onsubmit="return confirm('Delete this note?');"
                class="inline"
            >
                @csrf
                @method('DELETE')

                <button
                    type="submit"
                    class="text-sm text-red-600 hover:text-red-800"
                >
                    Delete
                </button>
            </form>
        </div>

        <!-- Render Markdown -->
        <div class="prose prose-indigo dark:prose-invert max-w-none">
            {!! Str::markdown($note->content, ['html_input' => 'strip']) !!}
        </div>

        <p class="text-xs text-gray-400 mt-4 block text-right">
            Added {{ $note->created_at->diffForHumans() }}
        </p>

    </div>

    @empty

    <p class="text-sm text-gray-500 dark:text-gray-400 italic">
        No notes added yet. Write your first markdown note below!
    </p>

    @endforelse
</div>
<!-- Add New Note Form -->
<form action="{{ route('notes.store', $paper->id) }}" method="POST">
    @csrf

    <div>
        <label
            for="content"
            class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
        >
            Write a new note (Supports Markdown)
        </label>

        <textarea
            id="content"
            name="content"
            rows="4"
            class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
            placeholder="## Key Takeaways&#10;- Point 1&#10;- Point 2&#10;&#10;**Bold text** and *italic* supported."
        ></textarea>
    </div>

    <div class="mt-3 text-right">
        <x-primary-button type="submit">
            Save Note
        </x-primary-button>
    </div>
</form>

        </div>
    </div>
</x-app-layout>