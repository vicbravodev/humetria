<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta property="csp-nonce" nonce="{{ Vite::cspNonce() }}">
        {{-- Outside the slot, and without data-inertia: the client does not manage it, so it always tells the revision PHP is running. --}}
        <meta name="app-revision" content="{{ config('app.revision') }}">
        @if (str_starts_with($page['component'] ?? '', 'disclosure-public/'))
            <meta name="robots" content="noindex, nofollow">
        @endif

        {{-- Inline script: marks <html> as `js` (what hides the unchosen tool panels), and applies the system dark mode preference before the first paint --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                document.documentElement.classList.add('js');

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
                background-color: #FBFDFC;
            }

            html.dark {
                background-color: #0F1716;
            }
        </style>

        @if (($analyticsToken = App\Http\Analytics::projectToken(request())) !== null)
            @include('partials.analytics', ['token' => $analyticsToken])
        @elseif (($errorToken = App\Http\Analytics::errorToken(request())) !== null)
            {{-- Error reports only (resources/js/lib/error-tracking.ts): the token and the host, never the page's address. --}}
            <script type="application/json" id="error-tracking-config">{!! App\Http\Analytics::errorConfiguration($errorToken) !!}</script>
        @endif

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon-180.png">
        @if (request()->is('me/*') && ($page['component'] ?? '') !== 'error' && is_string($appSlug = request()->route('tenant')))
            {{-- The installable app of the company of the person's pages (WorkerAccess): only under /me/{slug}. --}}
            <link rel="manifest" href="{{ route('worker.app.manifest', $appSlug) }}">
            @if (config('worker_access.pwa_sw_kill'))
                {{-- The service worker is switched off (PWA_SW_KILL): the page neither registers it nor keeps the one that is there. --}}
                <meta name="pwa-sw-kill" content="1">
            @endif
        @endif

        @fonts

        @viteReactRefresh
        {{-- The frame of the panel is a chunk fetched on demand; listing it here has the browser fetch it with the page's own code, not after it. --}}
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx", ...App\Http\PageFrame::layouts($page['component'] ?? '')])
        <x-inertia::head>
            @if (isset($page['props']['seo']))
                @include('partials.seo-fallback')
            @else
                <title>{{ config('app.name') }}</title>
            @endif
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
