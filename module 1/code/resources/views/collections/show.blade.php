@php
    use App\Enums\MemberRole;
    $canEdit = $myRole?->atLeast(MemberRole::Editor) ?? false;
    $isOwner = $myRole === MemberRole::Owner;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center gap-4 flex-wrap">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $collection->name }}
                </h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    Your role: <span class="font-medium">{{ $myRole?->label() ?? '—' }}</span>
                    · {{ $collection->papers->count() }} papers
                    · {{ $collection->members->count() }} {{ Str::plural('member', $collection->members->count()) }}
                </p>
            </div>
            <a href="{{ route('collections.index') }}" class="link text-sm">
                &larr; Back to collections
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Main column --}}
                <div class="lg:col-span-2 space-y-6">

                    {{-- Details (owner only) --}}
                    @if ($isOwner)
                        <div class="card card-body">
                            <form method="POST" action="{{ route('collections.update', $collection) }}" class="space-y-4">
                                @csrf
                                @method('PUT')

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <x-input-label for="name" :value="__('Name')" />
                                        <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                                                      :value="old('name', $collection->name)" required maxlength="255" />
                                    </div>
                                    <div class="md:col-span-2">
                                        <x-input-label for="description" :value="__('Description')" />
                                        <textarea id="description" name="description" rows="2"
                                                  class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('description', $collection->description) }}</textarea>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <x-primary-button>{{ __('Save collection') }}</x-primary-button>
                                </div>
                            </form>
                        </div>
                    @elseif (filled($collection->description))
                        <div class="card card-body">
                            <p class="text-gray-600 dark:text-gray-300">{{ $collection->description }}</p>
                        </div>
                    @endif

                    {{-- Add papers --}}
                    @if ($canEdit)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 space-y-5">
                            <div>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
                                    Add papers already in your library
                                </h3>

                                @if ($available->isEmpty())
                                    <p class="muted">
                                        Every paper you can access is already here.
                                    </p>
                                @else
                                    <form method="POST" action="{{ route('collections.papers.add', $collection) }}" class="space-y-2">
                                        @csrf
                                        {{-- Multi-select: file several papers in one go. --}}
                                        <select name="paper_ids[]" multiple size="5" required
                                                class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm">
                                            @foreach ($available as $option)
                                                <option value="{{ $option->id }}">
                                                    {{ Str::limit($option->title, 90) }}@if($option->year) ({{ $option->year }})@endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            Hold Ctrl (or ⌘) to select more than one.
                                        </p>
                                        <x-primary-button type="submit">{{ __('Add selected') }}</x-primary-button>
                                    </form>
                                @endif
                            </div>

                            <div class="border-t border-gray-200 dark:border-gray-700 pt-5">
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-3">
                                    Or upload new PDFs straight into this collection
                                </h3>
                                <form method="POST" action="{{ route('collections.papers.upload', $collection) }}"
                                      enctype="multipart/form-data" class="flex gap-3 flex-wrap items-start">
                                    @csrf
                                    <input type="file" name="files[]" accept="application/pdf" multiple required
                                           class="flex-1 min-w-[240px] text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm
                                                  file:mr-4 file:py-2 file:px-4 file:rounded-l-md file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:text-sm" />
                                    <x-primary-button type="submit">{{ __('Upload') }}</x-primary-button>
                                </form>
                            </div>
                        </div>
                    @endif

                    {{-- Papers --}}
                    <div class="card card-body">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                            Papers ({{ $collection->papers->count() }})
                        </h3>

                        @forelse ($collection->papers as $paper)
                            @if ($loop->first) <div class="space-y-3"> @endif

                            <div class="flex justify-between items-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50 hover:shadow-sm transition gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-3 mb-1 flex-wrap">
                                        <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                                            <a href="{{ route('papers.show', $paper) }}" class="hover:text-indigo-600 dark:hover:text-indigo-400">
                                                {{ $paper->title }}
                                            </a>
                                        </h4>
                                        <span class="px-2 py-0.5 text-xs rounded-full whitespace-nowrap {{ $paper->reading_status?->badgeClasses() }}">
                                            {{ $paper->reading_status?->label() }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-300 truncate">
                                        {{ $paper->authors ?: 'Unknown authors' }} · {{ $paper->year ?: 'n.d.' }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-4 shrink-0">
                                    <a href="{{ route('papers.show', $paper) }}" class="link text-sm">View</a>

                                    @if ($canEdit)
                                        <form action="{{ route('collections.papers.remove', ['collection' => $collection, 'paper' => $paper]) }}"
                                              method="POST"
                                              onsubmit="return confirm('Remove this paper from the collection? It stays in the library.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 dark:text-red-400 hover:underline">Remove</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            @if ($loop->last) </div> @endif
                        @empty
                            <div class="py-10 text-center">
                                <p class="text-gray-600 dark:text-gray-300 font-medium">No papers in this collection yet.</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $canEdit ? 'Add or upload some above.' : 'The owner has not added any yet.' }}
                                </p>
                            </div>
                        @endforelse
                    </div>

                    {{-- AI over the whole collection: ask across its papers,
                         and draft a literature review from them. --}}
                    @if ($canEdit)
                        @php($indexedInCollection = $collection->papers->where('index_status', 'indexed')->count())

                        <x-ai.chat
                            scope="collection"
                            :model="$collection"
                            :configured="$aiConfigured"
                            :ready="$indexedInCollection > 0"
                            not-ready-message="None of the papers here have indexed text yet, so there is nothing to ask about."
                        />

                        <x-ai.review :collection="$collection" :configured="$aiConfigured" />
                    @endif

                    {{-- Saved literature reviews --}}
                    @if ($collection->reviews->isNotEmpty())
                        <div class="card card-body">
                            <h3 class="section-title mb-4">
                                {{ __('app.literature_review') }} drafts
                            </h3>
                            <div class="space-y-4">
                                @foreach ($collection->reviews as $review)
                                    <details class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <summary class="cursor-pointer text-sm font-medium text-gray-800 dark:text-gray-200">
                                            {{ $review->title }}
                                            <span class="text-xs text-gray-400 ml-2">{{ $review->created_at->diffForHumans() }}</span>
                                        </summary>
                                        <div class="prose prose-sm prose-indigo dark:prose-invert max-w-none mt-3">
                                            {!! \App\Support\Markdown::render($review->content) !!}
                                        </div>
                                    </details>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Discussion --}}
                    <div class="card card-body">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b border-gray-200 dark:border-gray-700 pb-2">
                            {{ __('app.discussion') }} ({{ $comments->count() }})
                        </h3>

                        <div class="space-y-4 mb-6">
                            @forelse ($comments as $comment)
                                <x-comment :comment="$comment" :collection="$collection" :can-reply="$canEdit" />
                            @empty
                                <p class="muted">
                                    No comments yet.
                                    {{ $canEdit ? 'Start the discussion below.' : 'Only editors can post.' }}
                                </p>
                            @endforelse
                        </div>

                        @if ($canEdit)
                            <form method="POST" action="{{ route('comments.store', $collection) }}">
                                @csrf
                                <label for="comment-content" class="sr-only">Comment</label>
                                <textarea id="comment-content" name="content" rows="3" required maxlength="5000"
                                          placeholder="Share a thought with your collaborators…"
                                          class="block w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('content') }}</textarea>
                                <x-input-error :messages="$errors->get('content')" class="mt-2" />
                                <div class="mt-2 text-right">
                                    <x-primary-button type="submit">{{ __('Post comment') }}</x-primary-button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Sidebar --}}
                <div class="space-y-6">

                    {{-- Export --}}
                    <div class="card card-body">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Export</h3>
                        <div class="space-y-2 text-sm">
                            <a href="{{ route('collections.bundle', $collection) }}"
                               class="block px-3 py-2 rounded-md bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition font-medium">
                                <x-icon name="archive" class="w-4 h-4 shrink-0" />
                                <span>Full project bundle (.zip)</span>
                            </a>
                            <p class="text-xs text-gray-500 dark:text-gray-400 pb-2">
                                The PDFs, your notes, a BibTeX bibliography and the latest review draft.
                            </p>
                            <div class="flex gap-3 text-sm border-t border-gray-200 dark:border-gray-700 pt-2">
                                <span class="text-gray-500 dark:text-gray-400">Citations:</span>
                                <a href="{{ route('collections.export', ['collection' => $collection, 'format' => 'bibtex']) }}" class="link">BibTeX</a>
                                <a href="{{ route('collections.export', ['collection' => $collection, 'format' => 'apa']) }}" class="link">APA</a>
                                <a href="{{ route('collections.export', ['collection' => $collection, 'format' => 'text']) }}" class="link">Text</a>
                            </div>
                        </div>
                    </div>

                    {{-- Collaborators --}}
                    <div class="card card-body">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">
                            {{ __('app.collaborators') }}
                        </h3>

                        <div class="space-y-3 mb-4">
                            @foreach ($collection->members as $member)
                                <div class="flex items-center justify-between gap-2 text-sm">
                                    <div class="min-w-0">
                                        <p class="font-medium text-gray-800 dark:text-gray-200 truncate">
                                            {{ $member->user?->name ?? 'Unknown' }}
                                            @if ($member->user_id === auth()->id())
                                                <span class="text-xs text-gray-400">(you)</span>
                                            @endif
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">{{ $member->user?->email }}</p>
                                    </div>

                                    @if ($isOwner && $member->user_id !== $collection->user_id)
                                        <form method="POST" action="{{ route('collections.members.update', [$collection, $member]) }}" class="flex items-center gap-1">
                                            @csrf
                                            @method('PATCH')
                                            <select name="role" onchange="this.form.submit()"
                                                    class="text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 rounded">
                                                @foreach (MemberRole::assignableOptions() as $value => $label)
                                                    <option value="{{ $value }}" @selected($member->role->value === $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </form>

                                        <form method="POST" action="{{ route('collections.members.remove', [$collection, $member]) }}"
                                              onsubmit="return confirm('Remove {{ $member->user?->name }} from this collection?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 rounded text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" aria-label="Remove from collection" title="Remove from collection">
                                                    <x-icon name="x" class="w-4 h-4" />
                                                </button>
                                        </form>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 whitespace-nowrap">
                                            {{ $member->role->label() }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        @if ($isOwner)
                            <form method="POST" action="{{ route('collections.members.add', $collection) }}" class="space-y-2 border-t border-gray-200 dark:border-gray-700 pt-4">
                                @csrf
                                <label for="member-email" class="block text-xs font-medium text-gray-700 dark:text-gray-300">
                                    Invite by email
                                </label>
                                <input id="member-email" type="email" name="email" required placeholder="colleague@university.edu"
                                       class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <select name="role" class="w-full text-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md">
                                    @foreach (MemberRole::assignableOptions() as $value => $label)
                                        <option value="{{ $value }}" @selected($value === MemberRole::Viewer->value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('email')" class="mt-1" />
                                <x-primary-button type="submit" class="w-full justify-center">{{ __('Share') }}</x-primary-button>
                            </form>
                        @endif
                    </div>

                    {{-- Activity feed --}}
                    <div class="card card-body">
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">{{ __('app.activity') }}</h3>

                        @forelse ($activities as $activity)
                            @if ($loop->first) <ol class="space-y-3"> @endif
                            <li class="flex gap-2 text-sm">
                                <span aria-hidden="true">{{ $activity->type->icon() }}</span>
                                <div class="min-w-0">
                                    <p class="text-gray-700 dark:text-gray-300">
                                        <span class="font-medium">{{ $activity->user?->name ?? 'Someone' }}</span>
                                        {{ $activity->describe() }}
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $activity->created_at->diffForHumans() }}</p>
                                </div>
                            </li>
                            @if ($loop->last) </ol> @endif
                        @empty
                            <p class="muted">Nothing has happened here yet.</p>
                        @endforelse
                    </div>

                    {{-- Danger zone --}}
                    @if ($isOwner)
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 border-l-4 border-red-500">
                            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">Delete this collection</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-3">
                                The papers inside stay in their owners' libraries.
                            </p>
                            <form method="POST" action="{{ route('collections.destroy', $collection) }}"
                                  onsubmit="return confirm('Delete “{{ $collection->name }}”? The papers are kept.')">
                                @csrf
                                @method('DELETE')
                                <x-danger-button type="submit">{{ __('Delete collection') }}</x-danger-button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
