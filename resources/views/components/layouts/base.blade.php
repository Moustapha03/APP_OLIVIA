{{--
    HTML skeleton shared by every space layout. Not used directly in pages.
--}}
@props([
    'title' => null,
    'bodyClass' => 'bg-slate-100 text-slate-900',
    'themeColor' => '#f0f0ef',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="{{ $themeColor }}">
        <meta name="format-detection" content="telephone=no">

        {{-- PWA: installable app (public/manifest.webmanifest, public/sw.js) --}}
        <link rel="manifest" href="/manifest.webmanifest">
        <link rel="icon" href="/icons/icon.svg" type="image/svg+xml">
        <link rel="icon" href="/icons/favicon-32.png" sizes="32x32" type="image/png">
        <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Transport">

        <title>{{ $title ? $title.' · ' : '' }}{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{--
            Instant navigation (Chrome, Edge, Android): a link is fetched in
            advance when hovered or pressed. HTML only (prefetch, never
            prerender: no camera nor print started). Links with an effect are
            excluded: downloads, exports, WhatsApp, print, logout.
        --}}
        <script type="speculationrules" nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            {"prefetch": [{"where": {"and": [
                {"href_matches": "/*"},
                {"not": {"href_matches": "/*?*imprimer*"}},
                {"not": {"href_matches": "/*/pdf"}},
                {"not": {"href_matches": "/*/pdf?*"}},
                {"not": {"href_matches": "/*/whatsapp"}},
                {"not": {"href_matches": "/*/imprimer"}},
                {"not": {"href_matches": "/*/export"}},
                {"not": {"href_matches": "/*/export?*"}},
                {"not": {"href_matches": "/*/modele"}},
                {"not": {"href_matches": "/recu/*"}},
                {"not": {"href_matches": "/deconnexion"}},
                {"not": {"selector_matches": "[download], [target=_blank], [data-no-prefetch]"}}
            ]}, "eagerness": "moderate"}]}
        </script>
        {{ $head ?? '' }}
    </head>
    {{--
        data-user-id: offline copies are erased when the account changes (resources/js/pwa.js).
        Left out when the account is unknown: offline page (served from the cache), errors outside a session.
    --}}
    <body {{ $attributes->class(['min-h-dvh font-sans antialiased', $bodyClass]) }} @if (request()->hasSession() && ! request()->routeIs('offline')) data-user-id="{{ auth()->id() }}" @endif>
        <a href="#main" class="sr-only z-[60] rounded-full bg-slate-950 px-5 py-3 font-medium text-white focus:not-sr-only focus:fixed focus:left-4 focus:top-4">
            Aller au contenu
        </a>

        {{ $slot }}
    </body>
</html>
