{{--
    Consistent flash messaging for success / error / status.

    Controllers redirect with ->with('success', ...) or ->with('error', ...);
    this component is the single place those get rendered, so every page shows
    them the same way.
--}}

@if (session('success'))
    <div role="status"
         class="flex items-start gap-3 p-4 rounded-lg border border-green-200 bg-green-50 text-green-800
                dark:border-green-800 dark:bg-green-900/40 dark:text-green-200">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
        </svg>
        <p class="text-sm">{{ session('success') }}</p>
    </div>
@endif

@if (session('error'))
    <div role="alert"
         class="flex items-start gap-3 p-4 rounded-lg border border-red-200 bg-red-50 text-red-800
                dark:border-red-800 dark:bg-red-900/40 dark:text-red-200">
        <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" />
        </svg>
        <p class="text-sm">{{ session('error') }}</p>
    </div>
@endif

@if ($errors->any())
    <div role="alert"
         class="p-4 rounded-lg border border-red-200 bg-red-50 text-red-800
                dark:border-red-800 dark:bg-red-900/40 dark:text-red-200">
        <p class="text-sm font-medium">Please fix the following:</p>
        <ul class="mt-2 list-disc list-inside text-sm space-y-1">
            {{-- array_unique because per-item rules (tags.0, tags.1, …) often
                 produce the same message more than once. --}}
            @foreach (array_unique($errors->all()) as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
