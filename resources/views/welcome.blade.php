<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'ScholarDesk') }} - Your Personal Academic Library</title>
        
        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        
        <!-- Scripts (Tailwind via Vite) -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased bg-gray-50 dark:bg-gray-900 selection:bg-indigo-500 selection:text-white font-sans text-gray-900 dark:text-gray-100">
        
        <!-- Navbar -->
        <nav class="absolute top-0 w-full p-6 flex justify-between items-center z-10">
            <div class="font-bold text-2xl text-indigo-600 dark:text-indigo-400 flex items-center gap-2">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                {{ config('app.name', 'ScholarDesk') }}
            </div>
            
            <div class="space-x-4 font-semibold">
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" class="text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700 transition shadow-sm">Register</a>
                        @endif
                    @endauth
                @endif
            </div>
        </nav>

        <!-- Hero Section -->
        <div class="relative pt-32 pb-20 lg:pt-48 lg:pb-32 overflow-hidden flex items-center justify-center min-h-screen">
            <!-- Background Decoration -->
            <div class="absolute inset-y-0 w-full h-full -z-10 opacity-30 dark:opacity-20" style="background-image: radial-gradient(#4f46e5 1px, transparent 1px); background-size: 32px 32px;"></div>
            
            <div class="max-w-7xl mx-auto px-6 text-center">
                <h1 class="text-5xl md:text-7xl font-extrabold tracking-tight mb-6">
                    Smart Paper Management <br> for <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-500 to-purple-600">Modern Researchers</span>
                </h1>
                <p class="text-lg md:text-xl text-gray-600 dark:text-gray-400 max-w-2xl mx-auto mb-10">
                    Organize your academic papers, fetch metadata instantly via DOI, read PDFs directly in your browser, and save color-coded margin notes that persist forever.
                </p>
                <div class="flex justify-center gap-4">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="px-8 py-3 bg-indigo-600 text-white rounded-full font-semibold hover:bg-indigo-700 transition shadow-lg hover:shadow-indigo-500/30 text-lg">Go to Dashboard &rarr;</a>
                    @else
                        <a href="{{ route('register') }}" class="px-8 py-3 bg-indigo-600 text-white rounded-full font-semibold hover:bg-indigo-700 transition shadow-lg hover:shadow-indigo-500/30 text-lg">Start for Free</a>
                        <a href="{{ route('login') }}" class="px-8 py-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-white border border-gray-200 dark:border-gray-700 rounded-full font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 transition shadow-sm text-lg">Sign In</a>
                    @endauth
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="bg-white dark:bg-gray-800 py-24">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-3xl font-bold">Everything you need for your literature review</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-12">
                    <!-- Feature 1 -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto bg-indigo-100 dark:bg-indigo-900/50 rounded-2xl flex items-center justify-center mb-6 text-indigo-600 dark:text-indigo-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold mb-3">DOI Auto-Fetch</h3>
                        <p class="text-gray-600 dark:text-gray-400">Add papers in seconds. Just paste a DOI and we will fetch the title, authors, venue, and year automatically.</p>
                    </div>
                    
                    <!-- Feature 2 -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto bg-purple-100 dark:bg-purple-900/50 rounded-2xl flex items-center justify-center mb-6 text-purple-600 dark:text-purple-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold mb-3">Collections & Tags</h3>
                        <p class="text-gray-600 dark:text-gray-400">Keep your library organized. Group papers into named collections and use color-coded tags for easy filtering.</p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="text-center">
                        <div class="w-16 h-16 mx-auto bg-yellow-100 dark:bg-yellow-900/50 rounded-2xl flex items-center justify-center mb-6 text-yellow-600 dark:text-yellow-400">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        </div>
                        <h3 class="text-xl font-bold mb-3">Built-in PDF Reader</h3>
                        <p class="text-gray-600 dark:text-gray-400">Read PDFs right in the browser. Highlight important text and add permanent margin notes linked to the exact page.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-gray-50 dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 py-8 text-center text-gray-500 dark:text-gray-400 text-sm">
            <p>&copy; {{ date('Y') }} {{ config('app.name', 'ScholarDesk') }}. Built with Laravel for academic research.</p>
        </footer>

    </body>
</html>