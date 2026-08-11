<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Upload New Research Paper') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    <!-- Paper Upload Form -->
                    <form method="POST" action="{{ route('papers.store') }}" enctype="multipart/form-data">
                        @csrf

                        <!-- Title -->
                        <div>
                            <x-input-label for="title" :value="__('Paper Title (Optional if DOI/URL is provided)')" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title')" placeholder="Enter title or leave blank to auto-fetch via DOI" autofocus />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <!-- Abstract -->
                        <div class="mt-4">
                            <x-input-label for="abstract" :value="__('Abstract (Optional)')" />
                            <textarea id="abstract" name="abstract" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" rows="4">{{ old('abstract') }}</textarea>
                            <x-input-error :messages="$errors->get('abstract')" class="mt-2" />
                        </div>

                        <!-- DOI or URL Link -->
                        <div class="mt-4">
                            <x-input-label for="doi" :value="__('DOI or Article URL (Optional if PDF is uploaded)')" />
                            <x-text-input id="doi" class="block mt-1 w-full" type="text" name="doi" :value="old('doi')" placeholder="e.g. 10.1000/xyz123 or https://..." />
                            <x-input-error :messages="$errors->get('doi')" class="mt-2" />
                        </div>

                        <!-- Divider -->
                        <div class="flex items-center my-4">
                            <div class="flex-grow border-t border-gray-300 dark:border-gray-700"></div>
                            <span class="px-3 text-gray-500 bg-white dark:bg-gray-800 text-sm">OR</span>
                            <div class="flex-grow border-t border-gray-300 dark:border-gray-700"></div>
                        </div>

                        <!-- PDF File Upload -->
                        <div class="mt-4">
                            <x-input-label for="file" :value="__('Upload PDF Document (Optional if DOI/URL is provided)')" />
                            <input id="file" type="file" name="file" accept="application/pdf" class="block mt-1 w-full text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm focus:ring focus:ring-indigo-200 focus:ring-opacity-50" />
                            <x-input-error :messages="$errors->get('file')" class="mt-2" />
                        </div>

                        <!-- Submit Button -->
                        <div class="flex items-center justify-end mt-6">
                            <x-primary-button>
                                {{ __('Upload Paper') }}
                            </x-primary-button>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>
    </div>
</x-app-layout>