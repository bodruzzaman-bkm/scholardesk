<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit paper details') }}
            </h2>
            <a href="{{ route('papers.show', $paper) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                &larr; Back to paper
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    <form method="POST" action="{{ route('papers.update', $paper) }}">
                        @csrf
                        @method('PUT')

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('Paper title')" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title', $paper->title)" required autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Authors -->
                        <div class="mt-4">
                            <x-input-label for="authors" :value="__('Authors')" />
                            <x-text-input id="authors" class="block mt-1 w-full" type="text" name="authors" :value="old('authors', $paper->authors)" placeholder="Comma-separated, e.g. Ada Lovelace, Alan Turing" />
                            <x-input-error :messages="$errors->get('authors')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-2 gap-4 mt-4">
                            <div>
                                <x-input-label for="year" :value="__('Year')" />
                                <x-text-input id="year" class="block mt-1 w-full" type="number" min="1500" max="{{ date('Y') + 1 }}" name="year" :value="old('year', $paper->year)" />
                                <x-input-error :messages="$errors->get('year')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="venue" :value="__('Venue (journal or conference)')" />
                                <x-text-input id="venue" class="block mt-1 w-full" type="text" name="venue" :value="old('venue', $paper->venue)" />
                                <x-input-error :messages="$errors->get('venue')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Abstract: present so that saving the form cannot wipe it -->
                        <div class="mt-4">
                            <x-input-label for="abstract" :value="__('Abstract')" />
                            <textarea id="abstract" name="abstract" rows="5"
                                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                            >{{ old('abstract', $paper->abstract) }}</textarea>
                            <x-input-error :messages="$errors->get('abstract')" class="mt-2" />
                        </div>

                        <!-- Reading Status -->
                        <div class="mt-4">
                            <x-input-label for="reading_status" :value="__('Reading status')" />
                            @php($currentStatus = old('reading_status', $paper->reading_status?->value))
                            <select id="reading_status" name="reading_status"
                                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('reading_status')" class="mt-2" />
                        </div>

                        <!-- Organize into Collections -->
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Add to collections</h3>
                            @if($collections->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    No collections yet — <a href="{{ route('collections.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">create one</a>.
                                </p>
                            @else
                                @php($selectedCollections = old('collections', $paper->collections->pluck('id')->all()))
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
                                    @foreach($collections as $collection)
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="collections[]" value="{{ $collection->id }}"
                                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                @checked(in_array($collection->id, $selectedCollections))>
                                            <span class="ml-2 text-gray-700 dark:text-gray-300">{{ $collection->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('collections.0')" class="mt-2" />
                        </div>

                        <!-- Apply Tags -->
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Apply tags</h3>

                            @if($tags->isEmpty())
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    No tags yet — create one below or on the
                                    <a href="{{ route('tags.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">tags page</a>.
                                </p>
                            @else
                                @php($selectedTags = old('tags', $paper->tags->pluck('id')->all()))
                                <div class="flex flex-wrap gap-3 mt-2">
                                    @foreach($tags as $tag)
                                        <label class="inline-flex items-center border border-gray-200 dark:border-gray-600 px-3 py-1 rounded-full cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                                class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                @checked(in_array($tag->id, $selectedTags))>
                                            <span class="ml-2 w-3 h-3 rounded-full inline-block" style="background-color: {{ $tag->color }};"></span>
                                            <span class="ml-1 text-sm text-gray-700 dark:text-gray-300">{{ $tag->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @endif
                            <x-input-error :messages="$errors->get('tags.0')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('papers.show', $paper) }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancel</a>
                            <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                        </div>
                    </form>

                    <!-- Create a new tag (outside the paper form — HTML forbids nested forms) -->
                    <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Create a new tag</h3>
                        <form method="POST" action="{{ route('tags.store') }}" class="flex items-end gap-4 flex-wrap">
                            @csrf
                            <div>
                                <x-input-label for="tag_name" :value="__('Name')" />
                                <x-text-input id="tag_name" class="block w-full text-sm" type="text" name="name" placeholder="e.g. Methodology" required />
                            </div>
                            <div>
                                <x-input-label for="tag_color" :value="__('Colour')" />
                                <input id="tag_color" type="color" name="color" value="#4F46E5" class="h-10 w-14 border-0 rounded cursor-pointer bg-transparent" required />
                            </div>
                            <x-primary-button type="submit">{{ __('Add tag') }}</x-primary-button>
                        </form>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        <x-input-error :messages="$errors->get('color')" class="mt-2" />
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
