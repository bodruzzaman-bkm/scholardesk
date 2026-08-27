@props([
    'label',
    'value',
    'accent' => 'indigo',
    'href' => null,
    'icon' => null,
])

@php
    /*
     * Full class strings (never interpolated fragments) so Tailwind's content
     * scanner can see them and keep them in the compiled CSS.
     *
     * The accent now tints the icon rather than painting a left border. A row
     * of four cards each with a different coloured bar read as four warnings;
     * a quiet icon chip carries the same distinction without the alarm.
     */
    $accents = [
        'indigo' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-500/15 dark:text-indigo-400',
        'green'  => 'bg-green-50 text-green-600 dark:bg-green-500/15 dark:text-green-400',
        'purple' => 'bg-purple-50 text-purple-600 dark:bg-purple-500/15 dark:text-purple-400',
        'amber'  => 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
    ];
    $chip = $accents[$accent] ?? $accents['indigo'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => 'card card-body flex items-center gap-4 '.($href ? 'card-interactive' : ''),
    ]) }}
>
    @if ($icon)
        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 {{ $chip }}">
            <x-icon :name="$icon" class="w-5 h-5" />
        </div>
    @endif

    <div class="min-w-0">
        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100 leading-none tabular-nums">
            {{ $value }}
        </div>
        {{-- {!! !!} because callers pass entities such as "Highlights &amp; notes". --}}
        <div class="mt-1.5 text-sm text-gray-500 dark:text-gray-400 truncate">{!! $label !!}</div>
    </div>
</{{ $tag }}>
