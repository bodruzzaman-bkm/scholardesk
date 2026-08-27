<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('My collections') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                <!-- Create -->
                <div class="md:col-span-1">
                    <div class="card card-body">
                        <h3 class="section-title mb-4">Create a collection</h3>
                        <form method="POST" action="{{ route('collections.store') }}">
                            @csrf
                            <div>
                                <x-input-label for="name" :value="__('Name')" />
                                <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                                              :value="old('name')" required maxlength="255" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div class="mt-4">
                                <x-input-label for="description" :value="__('Description (optional)')" />
                                <textarea id="description" name="description" rows="3"
                                          class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                            <div class="mt-4 flex justify-end">
                                <x-primary-button>{{ __('Create') }}</x-primary-button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- List -->
                <div class="md:col-span-2 space-y-4">
                    @forelse ($collections as $collection)
                        @if ($loop->first)
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        @endif

                        @php($role = $collection->roleFor(auth()->user()))
                        <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition bg-white dark:bg-gray-800 flex flex-col">
                            <div class="flex justify-between items-start gap-2">
                                <h4 class="font-semibold text-lg text-indigo-600 dark:text-indigo-400">
                                    <a href="{{ route('collections.show', $collection) }}" class="hover:underline">
                                        {{ $collection->name }}
                                    </a>
                                </h4>

                                {{-- Shared collections are labelled so it is obvious
                                     which are yours and what you can do in them. --}}
                                @if ($collection->user_id !== auth()->id())
                                    <span class="shrink-0 text-[10px] px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 whitespace-nowrap">
                                        Shared · {{ $role?->label() }}
                                    </span>
                                @endif
                            </div>

                            <p class="text-sm text-gray-600 dark:text-gray-300 mt-1 mb-4 flex-1">
                                {{ $collection->description ?: 'No description.' }}
                            </p>

                            <div class="flex justify-between items-center border-t border-gray-200 dark:border-gray-600 pt-3">
                                {{-- papers_count comes from withCount() in the controller,
                                     which avoids a COUNT query per collection. --}}
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-medium bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                    {{ $collection->papers_count }} {{ Str::plural('paper', $collection->papers_count) }}
                                    @if ($collection->members_count > 1)
                                        · {{ $collection->members_count }} members
                                    @endif
                                </span>
                                <a href="{{ route('collections.show', $collection) }}"
                                   class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline font-semibold">
                                    Open &rarr;
                                </a>
                            </div>
                        </div>

                        @if ($loop->last)
                            </div>
                        @endif
                    @empty
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-10 text-center">
                            <p class="text-gray-600 dark:text-gray-300 font-medium">No collections yet.</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 max-w-md mx-auto">
                                A collection groups related papers. Creating one unlocks features that work across
                                a set of papers rather than a single one:
                            </p>
                            <ul class="mt-4 text-sm text-gray-600 dark:text-gray-300 space-y-1 inline-block text-left">
                                <li>&bull; Ask a question across the whole set, with citations</li>
                                <li>&bull; Generate a literature-review draft</li>
                                <li>&bull; Share it with collaborators as editor or viewer</li>
                                <li>&bull; Export papers, notes and a bibliography as one file</li>
                            </ul>
                            <p class="text-xs text-gray-400 mt-4">
                                You can already ask across your entire library from the
                                <a href="{{ route('dashboard') }}" class="link">dashboard</a>.
                            </p>
                        </div>
                    @endforelse

                    @if ($collections->hasPages())
                        <div>{{ $collections->links() }}</div>
                    @endif
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
