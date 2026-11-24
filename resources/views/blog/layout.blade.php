{{--
    The frame of the public blog: plain server-rendered HTML, no React and no Node, so a crawler reads the whole article
    from the first response. Same tokens as the app (resources/css/app.css), no script but the theme switch and JSON-LD.
--}}
<!DOCTYPE html>
<html lang="es-MX"@if (($appearance ?? 'system') === 'dark') class="dark"@endif>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ App\Http\DocumentTitle::for($title, config('app.name')) }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">
        @yield('head')
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:locale" content="es_MX">
        <meta property="og:image" content="{{ ($image ?? App\Modules\Blog\Support\BlogSeo::imageUrl()) }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $imageAlt ?? __('blog.image_alt') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ ($image ?? App\Modules\Blog\Support\BlogSeo::imageUrl()) }}">

        <script nonce="{{ Vite::cspNonce() }}">
            // The person's choice in the app (cookie `appearance`) wins; without one the system decides, as in the app.
            if ('{{ $appearance ?? 'system' }}' === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.classList.add('dark');
            }
        </script>
        <style nonce="{{ Vite::cspNonce() }}">
            html { background-color: #FBFDFC; }
            html.dark { background-color: #0F1716; }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon-180.png">

        @fonts
        @vite('resources/css/app.css')
    </head>
    <body class="min-h-screen bg-background font-sans text-foreground antialiased">
        @include('partials.public-header', ['current' => $current ?? ''])

        <main>
            @yield('content')
        </main>

        <footer class="mt-16 border-t py-8">
            <div class="mx-auto flex w-full max-w-[1200px] flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 text-meta text-muted-foreground sm:px-8 lg:px-10">
                <p>© {{ now()->year }} {{ config('app.name') }}</p>
                <nav aria-label="Pie" class="flex flex-wrap gap-x-5">
                    <a href="/" class="inline-flex min-h-11 items-center hover:text-foreground">Inicio</a>
                    <a href="{{ route('blog.index') }}" data-test="footer-blog" class="inline-flex min-h-11 items-center hover:text-foreground">Blog</a>
                    <a href="{{ route('roadmap') }}" data-test="footer-roadmap" class="inline-flex min-h-11 items-center hover:text-foreground">Roadmap</a>
                    <a href="{{ route('privacy-notice') }}" class="inline-flex min-h-11 items-center hover:text-foreground">{{ __('blog.privacy') }}</a>
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center hover:text-foreground">{{ __('blog.login') }}</a>
                    <button type="button" data-theme-toggle class="inline-flex min-h-11 items-center hover:text-foreground">{{ __('blog.theme') }}</button>
                </nav>
            </div>
        </footer>
        <script nonce="{{ Vite::cspNonce() }}">
            // The group Recursos is a native <details>: it also closes with Escape or a click outside, as the React one does.
            const closeMenus = (except) => document.querySelectorAll('details[data-menu][open]').forEach((menu) => { if (menu !== except) menu.removeAttribute('open'); });
            document.addEventListener('click', (event) => closeMenus(event.target.closest('details[data-menu]')));
            document.addEventListener('keydown', (event) => { if (event.key === 'Escape') closeMenus(null); });
            // The theme button: flips light and dark and remembers it in the same cookie the app reads.
            document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.addEventListener('click', () => {
                const dark = document.documentElement.classList.toggle('dark');
                document.cookie = `appearance=${dark ? 'dark' : 'light'};path=/;max-age=31536000;SameSite=Lax`;
                try { localStorage.setItem('appearance', dark ? 'dark' : 'light'); } catch (e) {}
            }));
        </script>
    </body>
</html>
