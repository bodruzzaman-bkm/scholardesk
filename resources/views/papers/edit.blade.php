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

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end mt-6">
                            <a href="{{ route('papers.index') }}" class="text-sm text-gray-600 hover:underline mr-4">Cancel</a>
                            <x-primary-button>
                                {{ __('Update Paper') }}
                            </x-primary-button>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</x-app-layout>