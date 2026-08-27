<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Tags') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Create --}}
            <div class="card card-body">
                <h3 class="section-title mb-4">Create a tag</h3>

                <form method="POST" action="{{ route('tags.store') }}" class="flex items-end gap-4 flex-wrap">
                    @csrf
                    <div class="flex-1 min-w-[200px]">
                        <x-input-label for="name" :value="__('Name')" />
                        <x-text-input id="name" class="block w-full mt-1" type="text" name="name"
                                      :value="old('name')" placeholder="e.g. Methodology" required maxlength="50" />
                    </div>
                    <div>
                        <x-input-label for="color" :value="__('Colour')" />
                        <input id="color" type="color" name="color" value="{{ old('color', '#4F46E5') }}"
                               class="mt-1 h-10 w-14 border-0 rounded cursor-pointer bg-transparent" required />
                    </div>
                    <x-primary-button type="submit">{{ __('Add tag') }}</x-primary-button>
                </form>
            </div>

            {{-- List --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
                @forelse ($tags as $tag)
                    @if ($loop->first)
                        <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @endif

                    <div class="p-4 flex items-center gap-4 flex-wrap">
                        {{-- Rename / recolour --}}
                        <form method="POST" action="{{ route('tags.update', $tag) }}" class="flex items-center gap-3 flex-1 min-w-[280px]">
                            @csrf
                            @method('PUT')

                            <input type="color" name="color" value="{{ $tag->color }}"
                                   class="h-9 w-12 border-0 rounded cursor-pointer bg-transparent shrink-0"
                                   aria-label="Colour for {{ $tag->name }}" required />

                            <input type="text" name="name" value="{{ $tag->name }}" maxlength="50" required
                                   class="flex-1 text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"
                                   aria-label="Name for {{ $tag->name }}" />

                            <button type="submit"
                                    class="btn btn-sm btn-secondary">
                                Save
                            </button>
                        </form>

                        <a href="{{ route('papers.index', ['tag' => $tag->id]) }}"
                           class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 whitespace-nowrap">
                            {{ $tag->papers_count }} {{ Str::plural('paper', $tag->papers_count) }}
                        </a>

                        <form method="POST" action="{{ route('tags.destroy', $tag) }}"
                              onsubmit="return confirm('Delete the tag &quot;{{ $tag->name }}&quot;? The papers themselves are kept.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-1.5 text-sm text-red-600 dark:text-red-400 hover:underline">
                                Delete
                            </button>
                        </form>
                    </div>

                    @if ($loop->last)
                        </div>
                    @endif
                @empty
                    <x-empty-state icon="tag"
                                   title="No tags yet."
                                   description="Create one above, then apply it from any paper's edit page." />
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
