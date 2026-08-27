<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Add papers') }}
        </h2>
    </x-slot>

    <div x-data="{ tab: '{{ $errors->has('files') ? 'batch' : 'single' }}' }" class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <x-flash />

            {{-- Mode switch: one detailed paper, or many PDFs at once. --}}
            <div class="flex gap-2 bg-white dark:bg-gray-800 p-1 rounded-lg shadow-sm">
                <button type="button" @click="tab = 'single'"
                        :class="tab === 'single' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="flex-1 px-4 py-2 rounded-md text-sm font-medium transition">
                    One paper
                </button>
                <button type="button" @click="tab = 'batch'"
                        :class="tab === 'batch' ? 'bg-indigo-600 text-white' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700'"
                        class="flex-1 px-4 py-2 rounded-md text-sm font-medium transition">
                    Many PDFs at once
                </button>
            </div>

            {{-- Single paper: DOI lookup or one PDF, with editable metadata --}}
            <div x-show="tab === 'single'" x-cloak class="card card-body">
                <form method="POST" action="{{ route('papers.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div>
                        <x-input-label for="identifier" :value="__('DOI or article link')" />
                        <x-text-input id="identifier" class="block mt-1 w-full" type="text" name="identifier"
                                      :value="old('identifier', old('doi'))"
                                      placeholder="10.1000/xyz123 · arxiv.org/abs/1706.03762 · or any article page" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Paste either a DOI or a link to the article. Title, authors, year, venue and abstract are
                            fetched automatically — from Crossref, OpenAlex, DataCite or arXiv, or from the page itself
                            when it has no DOI.
                        </p>
                        <x-input-error :messages="$errors->get('identifier')" class="mt-2" />
                        <x-input-error :messages="$errors->get('doi')" class="mt-2" />
                    </div>

                    <div class="flex items-center my-5">
                        <div class="flex-grow border-t border-gray-300 dark:border-gray-700"></div>
                        <span class="px-3 text-gray-500 text-sm">and / or</span>
                        <div class="flex-grow border-t border-gray-300 dark:border-gray-700"></div>
                    </div>

                    <div>
                        <x-input-label for="file" :value="__('PDF file')" />
                        <input id="file" type="file" name="file" accept="application/pdf"
                               class="block mt-1 w-full text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm
                                      file:mr-4 file:py-2 file:px-4 file:rounded-l-md file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:text-sm file:font-medium" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            PDF, up to 10 MB. Text is extracted automatically so the paper becomes searchable.
                        </p>
                        <x-input-error :messages="$errors->get('file')" class="mt-2" />
                    </div>

                    <div class="mt-5 pt-5 border-t border-gray-200 dark:border-gray-700">
                        <x-input-label for="title" :value="__('Title (optional — overrides the DOI lookup)')" />
                        <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title')" />
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div class="mt-4">
                        <x-input-label for="abstract" :value="__('Abstract (optional)')" />
                        <textarea id="abstract" name="abstract" rows="4"
                                  class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">{{ old('abstract') }}</textarea>
                        <x-input-error :messages="$errors->get('abstract')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ route('papers.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancel</a>
                        <x-primary-button>{{ __('Add paper') }}</x-primary-button>
                    </div>
                </form>
            </div>

            {{-- Bulk upload --}}
            <div x-show="tab === 'batch'" x-cloak class="card card-body">
                {{-- Limits come from PHP's own configuration, so the form can
                     never promise more than the server will accept. --}}
                <form method="POST" action="{{ route('papers.storeBatch') }}" enctype="multipart/form-data"
                      x-data="batchUpload({
                          maxFiles: {{ \App\Support\UploadLimits::effectiveMaxFiles() }},
                          perFileKb: {{ \App\Support\UploadLimits::perFileKb() }},
                          perRequestKb: {{ \App\Support\UploadLimits::perRequestKb() }},
                      })"
                      @submit="if (!ok) $event.preventDefault()">
                    @csrf

                    <x-input-label for="files" :value="__('Select PDFs')" />
                    <input id="files" type="file" name="files[]" accept="application/pdf" multiple required
                           @change="pick"
                           class="block mt-1 w-full text-sm text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm
                                  file:mr-4 file:py-2 file:px-4 file:rounded-l-md file:border-0 file:bg-gray-100 dark:file:bg-gray-700 file:text-sm file:font-medium" />

                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Up to {{ \App\Support\UploadLimits::effectiveMaxFiles() }} PDFs,
                        {{ \App\Support\UploadLimits::perFileLabel() }} each,
                        {{ \App\Support\UploadLimits::perRequestLabel() }} per upload.
                        Each becomes a paper titled from its filename — edit the details afterwards.
                        Files that fail are reported rather than stopping the batch.
                    </p>

                    {{-- Selection summary, with the total so the ceiling is visible
                         before submitting rather than after a rejected request. --}}
                    <template x-if="files.length">
                        <div class="mt-4 border border-gray-200 dark:border-gray-700 rounded-md p-3">
                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                                <span x-text="files.length"></span> file(s),
                                <span x-text="human(totalKb)"></span> total
                                <span class="text-gray-400">/ {{ \App\Support\UploadLimits::perRequestLabel() }} allowed</span>
                            </p>

                            <ul class="text-xs space-y-1 max-h-40 overflow-y-auto">
                                <template x-for="f in files" :key="f.name">
                                    <li class="flex justify-between gap-2"
                                        :class="f.tooBig ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400'">
                                        <span class="truncate" x-text="f.name"></span>
                                        <span class="shrink-0" x-text="human(f.kb) + (f.tooBig ? ' — too large' : '')"></span>
                                    </li>
                                </template>
                            </ul>

                            <template x-if="problems.length">
                                <div class="mt-3 text-xs text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded p-2">
                                    <template x-for="p in problems" :key="p">
                                        <p x-text="p"></p>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                    <x-input-error :messages="$errors->get('files')" class="mt-2" />
                    <x-input-error :messages="$errors->get('files.0')" class="mt-2" />

                    <div class="flex items-center justify-end mt-6">
                        <a href="{{ route('papers.index') }}" class="text-sm text-gray-600 dark:text-gray-400 hover:underline mr-4">Cancel</a>
                        <x-primary-button ::disabled="!ok" ::class="!ok ? 'opacity-50 cursor-not-allowed' : ''">
                            {{ __('Upload all') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>

            @push('scripts')
            <script>
                /**
                 * Client-side guard for the batch upload.
                 *
                 * Server-side validation still enforces all of this — this only
                 * saves the user a slow round trip that PHP would reject before
                 * Laravel could produce a readable message.
                 */
                function batchUpload(limits) {
                    return {
                        ...limits,
                        files: [],
                        problems: [],

                        pick(e) {
                            this.files = Array.from(e.target.files).map((f) => ({
                                name: f.name,
                                kb: Math.round(f.size / 1024),
                                tooBig: f.size / 1024 > this.perFileKb,
                            }));
                            this.validate();
                        },

                        get totalKb() {
                            return this.files.reduce((sum, f) => sum + f.kb, 0);
                        },

                        get ok() {
                            return this.files.length > 0 && this.problems.length === 0;
                        },

                        validate() {
                            const problems = [];

                            if (this.files.length > this.maxFiles) {
                                problems.push(`Too many files: ${this.files.length} selected, ${this.maxFiles} allowed.`);
                            }
                            const oversized = this.files.filter((f) => f.tooBig);
                            if (oversized.length) {
                                problems.push(`${oversized.length} file(s) exceed the ${this.human(this.perFileKb)} per-file limit.`);
                            }
                            // Leave headroom for multipart overhead.
                            if (this.totalKb > this.perRequestKb * 0.95) {
                                problems.push(`Total ${this.human(this.totalKb)} exceeds the ${this.human(this.perRequestKb)} limit for one upload. Upload in smaller batches.`);
                            }

                            this.problems = problems;
                        },

                        human(kb) {
                            return kb >= 1024 ? (kb / 1024).toFixed(1) + ' MB' : kb + ' KB';
                        },
                    };
                }
            </script>
            @endpush
        </div>
    </div>
</x-app-layout>
