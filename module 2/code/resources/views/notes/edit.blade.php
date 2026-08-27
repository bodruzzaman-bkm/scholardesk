<x-app-layout>
  <x-slot name="header">
    <div class="flex justify-between items-center">
      <h2
        class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight"
      >
        Edit Note
      </h2>

      <a
        href="{{ route('papers.show', $note->paper_id) }}"
        class="text-sm text-gray-600 hover:underline dark:text-gray-400"
      >
        &larr; Back to Paper
      </a>
    </div>
  </x-slot>

  <div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
      <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">

        <form action="{{ route('notes.update', $note->id) }}" method="POST">
          @csrf
          @method('PUT')

          <div>
            <label
              for="content"
              class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
            >
              Edit your Markdown note
            </label>

            <textarea
              id="content"
              name="content"
              rows="12"
              class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm"
            >{{ old('content', $note->content) }}</textarea>
          </div>

          <div class="mt-4 flex justify-end space-x-3">
            <a
              href="{{ route('papers.show', $note->paper_id) }}"
              class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300"
            >
              Cancel
            </a>

            <x-primary-button type="submit">
              Update Note
            </x-primary-button>
          </div>

        </form>

      </div>
    </div>
  </div>
</x-app-layout>
