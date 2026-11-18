{{--
    The frame of the public blog: plain server-rendered HTML, no React and no Node, so a crawler reads the whole article
    from the first response. Same tokens as the app (resources/css/app.css), no script but the theme switch and JSON-LD.
--}}
<!DOCTYPE html>
<html lang="es-MX">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <title>{{ $title }} - {{ config('app.name') }}</title>
        <meta name="description" content="{{ $description }}">
        <link rel="canonical" href="{{ $canonical }}">
        @yield('head')
        <meta property="og:url" content="{{ $canonical }}">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:locale" content="es_MX">
        <meta property="og:image" content="{{ App\Modules\Blog\Support\BlogSeo::imageUrl() }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ __('blog.image_alt') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $title }}">
        <meta name="twitter:description" content="{{ $description }}">
        <meta name="twitter:image" content="{{ App\Modules\Blog\Support\BlogSeo::imageUrl() }}">

        <script nonce="{{ Vite::cspNonce() }}">
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
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
        <header class="sticky top-0 z-10 border-b bg-background/80 backdrop-blur-xl backdrop-saturate-150">
            <div class="mx-auto flex h-16 w-full max-w-[1200px] items-center justify-between gap-3 px-5 sm:px-8 lg:px-10">
                <a href="/" aria-label="Humetría, inicio" class="flex min-h-11 items-center rounded-md">
                    <img src="/brand/humetria-horizontal-color.svg" alt="Humetría" class="h-7 w-auto dark:hidden">
                    <img src="/brand/humetria-horizontal-color-oscuro.svg" alt="Humetría" class="hidden h-7 w-auto dark:block">
                </a>
                <nav aria-label="Principal" class="flex items-center gap-1">
                    <a href="{{ route('blog.index') }}" class="inline-flex min-h-11 items-center rounded-md px-3 text-meta text-muted-foreground transition-colors hover:text-foreground">Blog</a>
                    <a href="{{ route('login') }}" class="hidden min-h-11 items-center rounded-md px-3 text-meta text-muted-foreground transition-colors hover:text-foreground sm:inline-flex">{{ __('blog.login') }}</a>
                    <a href="/#agenda-una-demo" class="ml-1 inline-flex min-h-11 items-center rounded-full bg-primary px-4 text-meta font-medium text-primary-foreground transition-opacity hover:opacity-90">{{ __('blog.cta_button') }}</a>
                </nav>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="mt-16 border-t py-8">
            <div class="mx-auto flex w-full max-w-[1200px] flex-wrap items-center justify-between gap-x-6 gap-y-2 px-5 text-meta text-muted-foreground sm:px-8 lg:px-10">
                <p>© {{ now()->year }} {{ config('app.name') }}</p>
                <nav aria-label="Pie" class="flex flex-wrap gap-x-5">
                    <a href="/" class="inline-flex min-h-11 items-center hover:text-foreground">Inicio</a>
                    <a href="{{ route('privacy-notice') }}" class="inline-flex min-h-11 items-center hover:text-foreground">{{ __('blog.privacy') }}</a>
                    <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center hover:text-foreground">{{ __('blog.login') }}</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
