@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
])

{{--
    The state a new user sees first.

    Every list in the app previously fell back to a single grey sentence, which
    tells someone what is absent but not what to do about it. This gives the
    same three things every time: what is empty, why that is normal, and the
    one action that fixes it.
--}}
<div {{ $attributes->merge(['class' => 'text-center py-12 px-6']) }}>
    <div class="mx-auto w-12 h-12 rounded-full bg-gray-100 dark:bg-gray-700/60
                flex items-center justify-center text-gray-400 dark:text-gray-500">
        <x-icon :name="$icon" class="w-6 h-6" />
    </div>

    <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
        {{ $title }}
    </h3>

    @if ($description)
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto">
            {{ $description }}
        </p>
    @endif

    {{-- Either a declared action, or whatever the caller slots in. --}}
    @if ($actionLabel && $actionHref)
        <a href="{{ $actionHref }}" class="btn btn-md btn-primary mt-5">
            <x-icon name="plus" class="w-4 h-4" />
            {{ $actionLabel }}
        </a>
    @endif

    @if (trim($slot) !== '')
        <div class="mt-5">{{ $slot }}</div>
    @endif
</div>
