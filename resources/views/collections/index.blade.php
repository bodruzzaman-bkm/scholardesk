<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('My Collections') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-3 gap-6">
            
            <!-- Left Side: Form to Create New Collection -->
            <div class="md:col-span-1">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Create New Collection</h3>
                    <form method="POST" action="{{ route('collections.store') }}">
                        @csrf
                        <div>
                            <x-input-label for="name" :value="__('Collection Name')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="mt-4">
                            <x-input-label for="description" :value="__('Description (Optional)')" />
                            <textarea id="description" name="description" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 rounded-md shadow-sm" rows="3"></textarea>
                        </div>
                        <div class="mt-4 flex justify-end">
                            <x-primary-button>
                                {{ __('Create') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Side: List of Collections -->
            <div class="md:col-span-2">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Your Collections</h3>
                    
                    @if($collections->isEmpty())
                        <p class="text-gray-500 dark:text-gray-400">No collections found. Create one to start organizing your papers!</p>
                    @else
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($collections as $collection)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition bg-gray-50 dark:bg-gray-700">
                                    <h4 class="font-semibold text-lg text-indigo-600 dark:text-indigo-400">
                                        {{ $collection->name }}
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 mb-4 line-clamp-2">
                                        {{ $collection->description ?? 'No description provided.' }}
                                    </p>
                                    
                                    <div class="flex justify-between items-center border-t border-gray-200 dark:border-gray-600 pt-3">
                                        <span class="text-xs text-gray-500 dark:text-gray-400 font-medium bg-gray-200 dark:bg-gray-800 px-2 py-1 rounded">
                                            {{ $collection->papers()->count() }} Papers
                                        </span>
                                        <a href="{{ route('collections.show', $collection->id) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                                            View Collection &rarr;
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>