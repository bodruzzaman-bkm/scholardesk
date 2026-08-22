@props([
    'label',
    'value',
    'accent' => 'indigo',
    'href' => null,
])

@php
    // Full class strings (never interpolated fragments) so Tailwind's content
    // scanner can see them and keep them in the compiled CSS.
    $accents = [
        'indigo' => 'border-indigo-500',
        'green' => 'border-green-500',
        'purple' => 'border-purple-500',
        'amber' => 'border-amber-500',
    ];
    $border = $accents[$accent] ?? $accents['indigo'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge([
        'class' => "block bg-white dark:bg-gray-800 shadow-sm rounded-lg p-4 border-l-4 $border "
            . ($href ? 'hover:shadow-md transition' : ''),
    ]) }}
>
    <div class="text-sm text-gray-500 dark:text-gray-400">{!! $label !!}</div>
    <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100 mt-1">{{ $value }}</div>
</{{ $tag }}>
