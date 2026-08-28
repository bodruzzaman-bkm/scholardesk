<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ScholarDesk') }} — An AI-augmented research workspace</title>
        <meta name="description" content="Collect, read, annotate and understand academic papers in one place. Import by DOI, read PDFs in the browser, and ask questions across your whole library.">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-white dark:bg-gray-900 selection:bg-indigo-500 selection:text-white font-sans text-gray-900 dark:text-gray-100">

        <header class="sticky top-0 z-20 backdrop-blur bg-white/80 dark:bg-gray-900/80 border-b border-gray-200/70 dark:border-gray-800">
            <nav class="max-w-6xl mx-auto px-6 h-16 flex justify-between items-center">
                <a href="/" class="flex items-center gap-2 font-bold text-lg text-gray-900 dark:text-gray-100">
                    <span class="w-8 h-8 rounded-lg bg-indigo-600 text-white flex items-center justify-center">
                        <x-icon name="library" class="w-5 h-5" />
                    </span>
                    {{ config('app.name', 'ScholarDesk') }}
                </a>

                @if (Route::has('login'))
                    <div class="flex items-center gap-2">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="btn btn-md btn-primary">Go to dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-md btn-ghost">Log in</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="btn btn-md btn-primary">Get started</a>
                            @endif
                        @endauth
                    </div>
                @endif
            </nav>
        </header>

        {{-- Hero --}}
        <section class="relative overflow-hidden">
            {{-- A soft radial wash rather than a dot grid: the grid read as a
                 placeholder texture behind the headline. --}}
            <div class="pointer-events-none absolute inset-0 -z-10
                        bg-[radial-gradient(60%_50%_at_50%_0%,theme(colors.indigo.100),transparent_70%)]
                        dark:bg-[radial-gradient(60%_50%_at_50%_0%,theme(colors.indigo.950),transparent_70%)]"></div>

            <div class="max-w-4xl mx-auto px-6 pt-20 pb-16 sm:pt-28 sm:pb-24 text-center">
                <p class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium
                          bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300 mb-6">
                    <x-icon name="sparkles" class="w-3.5 h-3.5" />
                    Answers grounded in your own papers
                </p>

                <h1 class="text-5xl sm:text-7xl font-extrabold tracking-tight leading-[1.05]">
                    {{-- The product name, deliberately literal rather than
                         config('app.name'): APP_NAME is an operator setting and
                         a stray value there would rename the headline. --}}
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-violet-600 dark:from-indigo-400 dark:to-violet-400">
                        ScholarDesk
                    </span>
                </h1>

                <p class="mt-6 text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
                    Collect papers by DOI or link, read and annotate them in the browser, and ask
                    questions across everything you have read — with every answer citing the paper
                    it came from.
                </p>

                <div class="mt-9 flex flex-wrap justify-center gap-3">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn btn-lg btn-primary">
                            Go to your dashboard
                            <x-icon name="external" class="w-4 h-4" />
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="btn btn-lg btn-primary">Create a free account</a>
                        <a href="{{ route('login') }}" class="btn btn-lg btn-secondary">Sign in</a>
                    @endauth
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section class="border-t border-gray-200/70 dark:border-gray-800 bg-gray-50/60 dark:bg-gray-900/40">
            <div class="max-w-6xl mx-auto px-6 py-20">
                <div class="text-center max-w-2xl mx-auto mb-14">
                    <h2 class="text-3xl font-bold tracking-tight">Everything a literature review needs</h2>
                    <p class="mt-3 text-gray-600 dark:text-gray-400">
                        From the moment you find a paper to the moment you cite it.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    @php
                        $features = [
                            ['search',      'indigo', 'Import by DOI or link',   'Paste a DOI or an article URL and the title, authors, year, venue and abstract arrive with it. Open-access PDFs download automatically.'],
                            ['book-open',   'violet', 'Read and annotate',       'A PDF reader in the browser with coloured highlights and margin notes that survive a reload, a new session and a change of zoom.'],
                            ['sparkles',    'amber',  'Ask your library',        'Ask a question across every paper you own. Answers are built only from your own text, and cite the papers they drew on.'],
                            ['chart',       'green',  'See the shape of it',     'Papers added over time, and breakdowns by year, venue, tag and reading status.'],
                            ['users',       'blue',   'Work together',           'Share a collection as Editor or Viewer, comment in threads, and follow an activity feed of what changed.'],
                            ['document',    'rose',   'Cite and export',         'BibTeX, APA and plain text for one paper or a whole collection — or the entire collection as one archive with its PDFs.'],
                        ];
                        // Full class strings so Tailwind's scanner keeps them.
                        $chips = [
                            'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
                            'violet' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/15 dark:text-violet-400',
                            'amber'  => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
                            'green'  => 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-400',
                            'blue'   => 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400',
                            'rose'   => 'bg-rose-50 text-rose-600 dark:bg-rose-500/15 dark:text-rose-400',
                        ];
                    @endphp

                    @foreach ($features as [$icon, $accent, $title, $body])
                        <div class="card card-body">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center mb-4 {{ $chips[$accent] }}">
                                <x-icon :name="$icon" class="w-5 h-5" />
                            </div>
                            <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
                            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400 leading-relaxed">{{ $body }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Closing call to action --}}
        @guest
            <section class="max-w-6xl mx-auto px-6 py-20">
                <div class="card card-body sm:p-12 text-center">
                    <h2 class="text-2xl sm:text-3xl font-bold tracking-tight">Start with one paper</h2>
                    <p class="mt-3 text-gray-600 dark:text-gray-400 max-w-xl mx-auto">
                        Upload a PDF or paste a DOI. Everything else — the reader, the search, the
                        assistant — works from there.
                    </p>
                    <div class="mt-7">
                        <a href="{{ route('register') }}" class="btn btn-lg btn-primary">Create a free account</a>
                    </div>
                </div>
            </section>
        @endguest

        <footer class="border-t border-gray-200/70 dark:border-gray-800">
            <div class="max-w-6xl mx-auto px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                &copy; {{ date('Y') }} {{ config('app.name', 'ScholarDesk') }} — built with Laravel for academic research.
            </div>
        </footer>

    </body>
</html>
