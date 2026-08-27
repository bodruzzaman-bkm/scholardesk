@props([
    'title',
    'subtitle' => null,
    'back' => null,
    'backLabel' => null,
])

{{--
    One header shape for every page.

    Each page was building this row itself, so the title size, the gap and the
    placement of the back link drifted between them. Slotting actions in keeps
    the alignment identical whatever the page puts on the right.
--}}
<div class="flex justify-between items-start gap-4 flex-wrap">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 mb-1 transition-colors">
                <span aria-hidden="true">&larr;</span>
                {{ $backLabel ?? __('Back') }}
            </a>
        @endif

        <h2 class="page-title">
            {{ $title }}
            @if ($subtitle)
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">{{ $subtitle }}</span>
            @endif
        </h2>
    </div>

    @if (trim($slot) !== '')
        <div class="flex items-center gap-2 shrink-0">{{ $slot }}</div>
    @endif
</div>
