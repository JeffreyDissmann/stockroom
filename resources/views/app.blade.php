<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        {{-- `viewport-fit=cover` opts into edge-to-edge rendering on iOS so
             `env(safe-area-inset-bottom)` returns the real home-indicator
             height instead of 0. The .bottom-tabs CSS already pads by
             that env() value; without `cover` the inset collapses and the
             tab row sits over the rounded corner.

             `maximum-scale=1` suppresses iOS Safari's automatic zoom when
             focusing an input whose font-size is below 16px (our .field
             inputs are 13px), which wrecked the layout on every dialog.
             Since iOS 10 Safari deliberately ignores this cap for MANUAL
             pinch gestures, so accessibility zooming keeps working. --}}
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">

        <title inertia>{{ config('app.name', 'Stockroom') }}</title>

        {{-- Apply the saved appearance before first paint to avoid a flash of the wrong theme. --}}
        <script>
            (function () {
                const saved = localStorage.getItem('appearance') || 'system';
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (saved === 'dark' || (saved === 'system' && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        <link rel="icon" type="image/svg+xml" href="/icon.svg">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="alternate icon" href="/favicon.ico">

        {{-- PWA — installable to homescreen, offline-capable for the last
             few visited items. theme-color matches the mono design tokens
             so the standalone status bar tints correctly. --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="theme-color" content="#ffffff" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0a0a0a" media="(prefers-color-scheme: dark)">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="Stockroom">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&family=Geist+Mono:wght@400;500;600&display=swap" rel="stylesheet" />

        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
