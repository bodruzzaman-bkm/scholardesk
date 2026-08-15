<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Paper Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <form method="POST" action="{{ route('papers.update', $paper->id) }}">
                        @csrf
                        @method('PUT')

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('Paper Title')" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title', $paper->title)" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Authors -->
                        <div class="mt-4">
                            <x-input-label for="authors" :value="__('Authors')" />
                            <x-text-input id="authors" class="block mt-1 w-full" type="text" name="authors" :value="old('authors', $paper->authors)" />
                            <x-input-error :messages="$errors->get('authors')" class="mt-2" />
                        </div>

                        <!-- Two Column Layout for Year and Venue -->
                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <!-- Year -->
                            <div>
                                <x-input-label for="year" :value="__('Year')" />
                                <x-text-input id="year" class="block mt-1 w-full" type="text" name="year" :value="old('year', $paper->year)" />
                                <x-input-error :messages="$errors->get('year')" class="mt-2" />
                            </div>

                            <!-- Venue -->
                            <div>
                                <x-input-label for="venue" :value="__('Venue (Journal/Conference)')" />
                                <x-text-input id="venue" class="block mt-1 w-full" type="text" name="venue" :value="old('venue', $paper->venue)" />
                                <x-input-error :messages="$errors->get('venue')" class="mt-2" />
                            </div>
                        </div>
<div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">
        Add to Collections
    </h3>

    @if($collections->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            You haven't created any collections yet.
        </p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
            @foreach($collections as $collection)
                <label class="inline-flex items-center">
                    <input
                        type="checkbox"
                        name="collections[]"
                        value="{{ $collection->id }}"
                        class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        {{ $paper->collections->contains($collection->id) ? 'checked' : '' }}
                    >

                    <span class="ml-2 text-gray-700 dark:text-gray-300">
                        {{ $collection->name }}
                    </span>
                </label>
            @endforeach
        </div>
        @endif
    </div>


                        <!-- Reading Status -->
                        <div class="mt-4">
                            <x-input-label for="reading_status" :value="__('Reading Status')" />
                            <select id="reading_status" name="reading_status" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="to read" {{ (old('reading_status', $paper->reading_status) == 'to read') ? 'selected' : '' }}>To Read</option>
                                <option value="reading" {{ (old('reading_status', $paper->reading_status) == 'reading') ? 'selected' : '' }}>Reading</option>
                                <option value="read" {{ (old('reading_status', $paper->reading_status) == 'read') ? 'selected' : '' }}>Read</option>
                            </select>
                            <x-input-error :messages="$errors->get('reading_status')" class="mt-2" />
                        </div>

                        <!-- Organize into Collections -->
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Add to Collections</h3>
                            @if($collections->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">You haven't created any collections yet.</p>
                            @else
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                                    @foreach($collections as $collection)
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="collections[]" value="{{ $collection->id }}"
                                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                {{ $paper->collections->contains($collection->id) ? 'checked' : '' }}>
                                            <span class="ml-2 text-gray-700 dark:text-gray-300">{{ $collection->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Apply Tags -->
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Apply Tags</h3>

                            @if($tags->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">You haven't created any tags yet.</p>
                            @else
                                @php($selectedTags = old('tags', $paper->tags->pluck('id')->toArray()))
                                <div class="flex flex-wrap gap-3 mt-2">
                                    @foreach($tags as $tag)
                                        <label class="inline-flex items-center border border-gray-200 dark:border-gray-600 px-3 py-1 rounded-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                {{ in_array($tag->id, $selectedTags) ? 'checked' : '' }}>
                                            <span class="ml-2 w-3 h-3 rounded-full inline-block" style="background-color: {{ $tag->color }};"></span>
                                            <span class="ml-1 text-sm text-gray-700 dark:text-gray-300">{{ $tag->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('papers.index') }}" class="text-sm text-gray-600 hover:underline mr-4">Cancel</a>
                            <x-primary-button>
                                {{ __('Update Paper') }}
                            </x-primary-button>
                        </div>
                    </form>

                    <!-- Create a New Tag (kept outside the paper form, HTML forbids nested forms) -->
                    <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Create a New Tag</h3>
                        <form method="POST" action="{{ route('tags.store') }}" class="flex items-center space-x-4">
                            @csrf
                            <div>
                                <x-text-input id="name" class="block w-full text-sm" type="text" name="name" placeholder="Tag Name" required />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <input type="color" name="color" value="#4F46E5" class="h-10 w-10 border-0 rounded cursor-pointer" required />
                            </div>
                            <x-primary-button type="submit">{{ __('Add Tag') }}</x-primary-button>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>