<!DOCTYPE html>
{{--
    The shell of the installed app (WorkerAccess\InstallableAppController::offline): the service worker keeps this page and
    the phone opens it with no network. Static on purpose: no script without a src (the bundle `offline` does what an inline
    one would, dark mode included), no person, no session, no CSRF token and no clock. Not an Inertia page.
--}}
<html lang="es-MX">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
        <meta name="app-revision" content="{{ config('app.revision') }}">
        @if ($swKill)
            <meta name="pwa-sw-kill" content="1">
        @endif

        <title>{{ $company }}</title>

        <style nonce="{{ Vite::cspNonce() }}">
            html {
                background-color: #FBFDFC;
            }

            html.dark {
                background-color: #0F1716;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon-180.png">
        <link rel="manifest" href="{{ route('worker.app.manifest', request()->route('tenant')) }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/offline.tsx'])
    </head>
    <body class="font-sans antialiased">
        <div id="offline-app" data-company="{{ $company }}" data-home-url="{{ $homeUrl }}" data-punch-url="{{ $punchUrl }}" data-deliver-url="{{ $deliverUrl }}" data-tickets-url="{{ $ticketsUrl }}"></div>
    </body>
</html>
