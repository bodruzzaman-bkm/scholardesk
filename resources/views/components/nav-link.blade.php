@props([
    'active' => false,
    'icon' => null,
])

{{--
    A pill rather than a bottom-border tab.

    Seven top-level destinations at a 16px header height left the underline
    style cramped, and the active item was hard to pick out at a glance. A
    filled pill reads clearly at this density and gives the hover state
    somewhere to live.
--}}
@php
    $base = 'inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium transition-colors duration-150';

    $classes = ($active ?? false)
        ? $base.' bg-indigo-50 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300'
        : $base.' text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-100 dark:hover:bg-gray-700/60';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active) aria-current="page" @endif>
    @if ($icon)
        <x-icon :name="$icon" class="w-4 h-4 shrink-0" />
    @endif
    {{ $slot }}
</a>
