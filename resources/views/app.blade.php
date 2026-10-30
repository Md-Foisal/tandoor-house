<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style nonce="{{ Vite::cspNonce() }}">
            html {
                background-color: #f7f1e8;
            }

            html.dark {
                background-color: #16110d;
            }
        </style>

        <noscript>
            <style nonce="{{ Vite::cspNonce() }}">
                [data-reveal] { opacity: 1 !important; transform: none !important; filter: none !important; clip-path: none !important; }
            </style>
        </noscript>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        <meta name="theme-color" content="#f7f1e8" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#16110d" media="(prefers-color-scheme: dark)">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        @php($seo = $page['props']['seo'] ?? null)
        @if ($seo)
            <meta name="description" content="{{ $seo['description'] }}" inertia="description">
            <meta name="robots" content="{{ $seo['robots'] }}">
            <link rel="canonical" href="{{ $seo['canonical'] }}">
            <meta property="og:site_name" content="{{ config('restaurant.name') }}">
            <meta property="og:locale" content="en_GB">
            <meta property="og:type" content="{{ $seo['type'] }}">
            <meta property="og:title" content="{{ $seo['title'] }}">
            <meta property="og:description" content="{{ $seo['description'] }}">
            <meta property="og:url" content="{{ $seo['canonical'] }}">
            <meta property="og:image" content="{{ $seo['image'] }}">
            <meta property="og:image:width" content="1200">
            <meta property="og:image:height" content="630">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:title" content="{{ $seo['title'] }}">
            <meta name="twitter:description" content="{{ $seo['description'] }}">
            <meta name="twitter:image" content="{{ $seo['image'] }}">
            @foreach ($seo['jsonld'] as $document)
                <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! \App\Support\StructuredData::encode($document) !!}</script>
            @endforeach
        @endif
        <x-inertia::head>
            <title>{{ $seo['title'] ?? config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
