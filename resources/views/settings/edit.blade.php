<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('app.settings') }}
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Language --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('app.language') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1 mb-4">
                    Changes the interface language. Your papers, notes and comments are always shown as written.
                </p>

                <form method="POST" action="{{ route('settings.locale') }}" class="flex gap-3 items-end flex-wrap">
                    @csrf
                    @method('PATCH')

                    <div class="flex-1 min-w-[200px]">
                        <label for="locale" class="sr-only">{{ __('app.language') }}</label>
                        <select id="locale" name="locale"
                                class="block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach ($locales as $value => $label)
                                <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-primary-button type="submit">{{ __('app.save') }}</x-primary-button>
                </form>
                <x-input-error :messages="$errors->get('locale')" class="mt-2" />
            </div>

            {{-- AI status: honest about what is and is not available. --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('app.ai_assistant') }}</h3>

                @if ($aiConfigured)
                    <p class="mt-2 text-sm text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-md p-3">
                        Configured via <span class="font-medium">{{ $aiProvider }}</span>
                        (<code class="font-mono text-xs">{{ $aiModel }}</code>).
                        Summaries, Q&amp;A and literature-review drafts are available.
                    </p>
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Free tiers meter tokens per minute. If a request is refused as "too large", ask a narrower
                        question or use a smaller collection &mdash; the app sizes its context to
                        {{ number_format(config('services.ai.token_budget')) }} tokens per request.
                    </p>
                @else
                    <p class="mt-2 text-sm text-amber-700 dark:text-amber-300 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-md p-3">
                        Not configured. Set <code class="font-mono text-xs">GROQ_API_KEY</code> (or
                        <code class="font-mono text-xs">GEMINI_API_KEY</code> with
                        <code class="font-mono text-xs">AI_PROVIDER=gemini</code>) in
                        <code class="font-mono text-xs">.env</code> to enable summaries, Q&amp;A and review drafts.
                    </p>
                @endif

                <div class="mt-4 text-sm text-gray-600 dark:text-gray-300 space-y-1">
                    <p class="font-medium text-gray-900 dark:text-gray-100">Works without an API key:</p>
                    <ul class="list-disc list-inside text-gray-500 dark:text-gray-400 space-y-0.5">
                        <li>Semantic search across your library</li>
                        <li>Related-paper suggestions</li>
                        <li>Everything else — library, reader, notes, collections, export</li>
                    </ul>
                </div>
            </div>

            {{-- Account --}}
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Account</h3>
                <dl class="mt-3 text-sm space-y-2">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Name</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ auth()->user()->email }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Role</dt>
                        <dd class="text-gray-900 dark:text-gray-100">{{ auth()->user()->role?->label() }}</dd>
                    </div>
                </dl>
                <a href="{{ route('profile.edit') }}" class="inline-block mt-4 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                    Edit profile &rarr;
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
