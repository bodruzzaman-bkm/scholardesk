@props([
    'paper',
    'size' => 'md',      // sm | md
    'showLabel' => false,
])

@php
    use App\Enums\ReadingStatus;

    $current = $paper->reading_status?->value ?? ReadingStatus::ToRead->value;
    $badge = $paper->reading_status?->badgeClasses() ?? '';
    $text = $size === 'sm' ? 'text-xs' : 'text-sm';
    $pad = $size === 'sm' ? 'py-0.5 pl-2 pr-6' : 'py-1 pl-3 pr-7';
@endphp

{{--
    Reading-status tracker (requirement 4).

    A select rather than a badge: the status was previously read-only
    everywhere except the paper detail page, so there was no way to mark a
    paper read from the library or while reading it.

    It carries the status colour so it still reads as a badge at a glance, and
    submits on change. The noscript button keeps it usable without JavaScript.
--}}
<form method="POST" action="{{ route('papers.status', $paper) }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-1']) }}>
    @csrf
    @method('PATCH')

    @if ($showLabel)
        <span class="text-xs text-gray-500 dark:text-gray-400 mr-1">Status</span>
    @endif

    <label for="status-{{ $paper->id }}" class="sr-only">Reading status for {{ $paper->title }}</label>
    <select id="status-{{ $paper->id }}" name="reading_status" onchange="this.form.submit()"
            class="appearance-none cursor-pointer rounded-full border-0 font-medium {{ $text }} {{ $pad }} {{ $badge }}
                   focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1 dark:focus:ring-offset-gray-800">
        @foreach (ReadingStatus::options() as $value => $label)
            <option value="{{ $value }}" @selected($current === $value)>{{ $label }}</option>
        @endforeach
    </select>

    <noscript>
        <button type="submit" class="{{ $text }} underline text-indigo-600 dark:text-indigo-400">Set</button>
    </noscript>
</form>
