@props([
    // 'comment' or 'paper' — the allowlist ReportController accepts.
    'type',
    'id',
])

{{--
    Flag content for an administrator (requirement 22).

    A <details> rather than a modal: no JavaScript, and the reason field is
    required, so reporting is always a deliberate two-step action rather than
    a single mis-click.
--}}
<details {{ $attributes->merge(['class' => 'inline-block text-left']) }}>
    <summary class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-red-600 dark:hover:text-red-400 cursor-pointer list-none transition-colors">
        <x-icon name="flag" class="w-3.5 h-3.5" />
        {{ __('app.report') }}
    </summary>

    <form method="POST" action="{{ route('reports.store') }}" class="mt-2 w-64 p-3 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="id" value="{{ $id }}">

        <label for="reason-{{ $type }}-{{ $id }}" class="block text-xs text-gray-600 dark:text-gray-400 mb-1">
            Why are you reporting this?
        </label>
        <textarea id="reason-{{ $type }}-{{ $id }}" name="reason" rows="2" required maxlength="500"
                  placeholder="Spam, abuse, wrong content…"
                  class="block w-full text-xs border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 rounded-md shadow-sm"></textarea>

        <button type="submit" class="mt-2 w-full px-3 py-1 text-xs rounded-md bg-red-600 text-white hover:bg-red-700">
            Submit report
        </button>
    </form>
</details>
