@props([
    'name',
    'class' => 'w-5 h-5',
])

{{--
    Inline SVG icons, one consistent stroke weight and viewBox.

    These replace the emoji that were being used as interface icons (📦 for
    export, ⚑ for report, ✨ for the AI panel). Emoji render differently on
    every platform, cannot inherit colour, and read as placeholder art — which
    is exactly the impression an academic tool should not give.

    Drawn inline rather than pulled from an icon package: there are a dozen of
    them, and a dependency plus a build step for that is not a trade worth
    making.
--}}

@php
    $paths = [
        // Navigation
        'home'          => 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3v-6h6v6h3a1 1 0 001-1V10',
        'library'       => 'M12 6.25v13m0-13C10.83 5.48 9.25 5 7.5 5S4.17 5.48 3 6.25v13C4.17 18.48 5.75 18 7.5 18s3.33.48 4.5 1.25m0-13C13.17 5.48 14.75 5 16.5 5S19.83 5.48 21 6.25v13C19.83 18.48 18.25 18 16.5 18s-3.33.48-4.5 1.25',
        'collection'    => 'M3 7a2 2 0 012-2h3.6a1 1 0 01.8.4L10.5 7H19a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V7z',
        'tag'           => 'M7 7h.01M3 5.4V9.6a2 2 0 00.59 1.42l8.4 8.4a2 2 0 002.83 0l4.6-4.6a2 2 0 000-2.83l-8.4-8.4A2 2 0 009.6 3H5.4A2.4 2.4 0 003 5.4z',
        'search'        => 'M21 21l-4.35-4.35M17 10.5a6.5 6.5 0 11-13 0 6.5 6.5 0 0113 0z',
        'chart'         => 'M4 19h16M7 16V9m5 7V5m5 11v-4',
        'shield'        => 'M12 3l7.5 3v5.25c0 4.35-3.1 8.4-7.5 9.75-4.4-1.35-7.5-5.4-7.5-9.75V6L12 3z',

        // Actions
        'plus'          => 'M12 5v14M5 12h14',
        'download'      => 'M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2',
        'archive'       => 'M3 7h18M5 7v12a2 2 0 002 2h10a2 2 0 002-2V7M9 11h6M8 7V5a2 2 0 012-2h4a2 2 0 012 2v2',
        'flag'          => 'M4 21V4m0 0h11l-1.5 4L15 12H4',
        'edit'          => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.4-9.4a2 2 0 112.8 2.8L11.8 15H9v-2.8l8.6-8.6z',
        'trash'         => 'M4 7h16M10 11v6M14 11v6M5 7l1 13a2 2 0 002 2h8a2 2 0 002-2l1-13M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3',
        'upload'        => 'M12 15V3m0 0L8 7m4-4l4 4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2',
        'external'      => 'M14 5h5v5M19 5l-7 7M18 14v5a1 1 0 01-1 1H6a1 1 0 01-1-1V8a1 1 0 011-1h5',
        'refresh'       => 'M4 4v6h6M20 20v-6h-6M20 9a8 8 0 00-14.5-3M4 15a8 8 0 0014.5 3',

        // Content
        'document'      => 'M9 13h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z',
        'note'          => 'M9 12h6m-6 4h4M8 3h8a2 2 0 012 2v14a2 2 0 01-2 2H8a2 2 0 01-2-2V5a2 2 0 012-2z',
        'highlight'     => 'M4 20h16M6 16l8.5-8.5a2 2 0 012.8 0l1.2 1.2a2 2 0 010 2.8L10 20H6v-4z',
        'chat'          => 'M8 12h8m-8-4h5m-6 9l-3 3V6a2 2 0 012-2h14a2 2 0 012 2v9a2 2 0 01-2 2H7z',
        'sparkles'      => 'M12 3l1.6 4.4L18 9l-4.4 1.6L12 15l-1.6-4.4L6 9l4.4-1.6L12 3zM18 15l.8 2.2L21 18l-2.2.8L18 21l-.8-2.2L15 18l2.2-.8L18 15z',
        'bell'          => 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
        'users'         => 'M17 20h5v-1a4 4 0 00-3-3.87M9 20H2v-1a5 5 0 015-5h2a5 5 0 015 5v1H9zm0-9a3 3 0 100-6 3 3 0 000 6zm7 0a3 3 0 100-6',
        'link'          => 'M10 13a5 5 0 007.5.5l2-2a5 5 0 00-7-7l-1 1M14 11a5 5 0 00-7.5-.5l-2 2a5 5 0 007 7l1-1',

        // Status
        'check'         => 'M5 13l4 4L19 7',
        'check-circle'  => 'M9 12l2 2 4-4M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'x'             => 'M6 18L18 6M6 6l12 12',
        'warning'       => 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
        'info'          => 'M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
        'clock'         => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'inbox'         => 'M3 12h5l2 3h4l2-3h5M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z',
        'book-open'     => 'M12 6.5C10.5 5.5 8.5 5 6 5H3v13h3c2.5 0 4.5.5 6 1.5m0-13c1.5-1 3.5-1.5 6-1.5h3v13h-3c-2.5 0-4.5.5-6 1.5m0-13v13',
    ];

    $d = $paths[$name] ?? $paths['info'];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }}
     fill="none" stroke="currentColor" stroke-width="1.75"
     stroke-linecap="round" stroke-linejoin="round"
     viewBox="0 0 24 24" aria-hidden="true">
    <path d="{{ $d }}" />
</svg>
