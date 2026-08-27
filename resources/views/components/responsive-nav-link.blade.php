@props([
    'active' => false,
    'icon' => null,
])

@php
    $base = 'flex items-center gap-3 w-full ps-4 pe-4 py-2.5 text-start text-base font-medium transition-colors duration-150 border-l-4';

    $classes = ($active ?? false)
        ? $base.' border-indigo-500 text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-900/30'
        : $base.' border-transparent text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 hover:bg-gray-50 dark:hover:bg-gray-700/60';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }} @if($active) aria-current="page" @endif>
    @if ($icon)
        <x-icon :name="$icon" class="w-5 h-5 shrink-0 text-gray-400 dark:text-gray-500" />
    @endif
    {{ $slot }}
</a>
