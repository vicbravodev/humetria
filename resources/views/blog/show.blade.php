@extends('blog.layout', ['title' => $article->title, 'description' => $article->description, 'current' => 'blog', 'image' => App\Modules\Blog\Support\BlogSeo::imageUrl($article), 'imageAlt' => $coverAlt])

@section('head')
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="{{ $article->publishedAt->toDateString() }}">
    <meta property="article:modified_time" content="{{ $article->updatedAt->toDateString() }}">
    <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! $jsonLd !!}</script>
@endsection

@section('content')
    <div class="family-{{ $article->family }}">
        <header class="mx-auto w-full max-w-[1000px] px-5 pt-6 sm:px-8 sm:pt-8">
            <nav aria-label="Migas">
                <ol class="flex flex-wrap items-center gap-x-1.5 text-meta text-muted-foreground">
                    <li><a href="/" class="inline-flex min-h-11 items-center rounded-sm hover:text-foreground">Inicio</a></li>
                    <li aria-hidden="true">›</li>
                    <li><a href="{{ route('blog.index') }}" class="inline-flex min-h-11 items-center rounded-sm hover:text-foreground">Blog</a></li>
                </ol>
            </nav>
            <p class="mt-2">
                <a href="{{ route($article->toolRoute) }}" class="inline-flex min-h-11 items-center rounded-full bg-family-soft px-3.5 text-caption font-medium text-family hover:opacity-90">{{ $article->toolName }}</a>
            </p>
            <h1 class="mt-3 max-w-[820px] text-[34px] leading-[1.08] font-semibold tracking-[-0.03em] text-balance sm:text-5xl">{{ $article->title }}</h1>
            <p class="mt-5 max-w-[820px] text-lg leading-7 text-pretty text-muted-foreground sm:text-xl sm:leading-8">{{ $article->description }}</p>
            <p class="mt-5 flex flex-wrap gap-x-2 gap-y-1 text-caption text-muted-foreground">
                <span>{{ __('blog.reading', ['minutes' => $article->readingMinutes]) }}</span>
                @if ($article->reviewedAt)
                    <span aria-hidden="true">·</span>
                    <span>{{ __('blog.reviewed', ['date' => $article->reviewedAt->translatedFormat('j \d\e F \d\e Y')]) }}</span>
                @endif
                @if ($article->updatedAt->gt($article->publishedAt))
                    <span aria-hidden="true">·</span>
                    <span>{{ __('blog.updated', ['date' => $article->updatedAt->translatedFormat('j \d\e F \d\e Y')]) }}</span>
                @endif
            </p>
        </header>

        <div class="mx-auto mt-8 w-full max-w-[1000px] px-5 sm:px-8">
            <img src="{{ $article->cover() }}" width="1200" height="630" alt="{{ $coverAlt }}" fetchpriority="high"
                 class="aspect-[1200/630] w-full rounded-2xl border object-cover">
        </div>

        <div class="mx-auto mt-10 grid w-full max-w-[1000px] gap-8 px-5 sm:px-8 lg:grid-cols-[200px_minmax(0,1fr)] lg:gap-14">
            @if (count($article->headings) > 1)
                <aside class="lg:order-first">
                    <details class="rounded-xl border bg-card p-4 lg:hidden">
                        <summary class="min-h-6 cursor-pointer text-meta font-semibold">{{ __('blog.toc') }}</summary>
                        <ol class="mt-3 grid gap-1 text-meta">
                            @foreach ($article->headings as $heading)
                                <li><a href="#{{ $heading['id'] }}" class="inline-flex min-h-11 items-center text-muted-foreground hover:text-foreground">{{ $heading['text'] }}</a></li>
                            @endforeach
                        </ol>
                    </details>
                    <nav aria-label="Contenido" class="sticky top-24 hidden lg:block">
                        <p class="text-caption font-semibold tracking-wide text-muted-foreground uppercase">{{ __('blog.toc') }}</p>
                        <ol class="mt-3 grid gap-0.5 border-s text-meta">
                            @foreach ($article->headings as $heading)
                                <li><a href="#{{ $heading['id'] }}" class="-ms-px block border-s border-transparent py-1.5 ps-3 text-muted-foreground transition-colors hover:border-family hover:text-foreground">{{ $heading['text'] }}</a></li>
                            @endforeach
                        </ol>
                    </nav>
                </aside>
            @endif

            <article class="min-w-0 max-w-[68ch] lg:col-start-2">
                <div class="blog-prose">{!! $article->html !!}</div>

                <section aria-labelledby="sources" class="mt-12 border-t pt-8">
                    <h2 id="sources" class="text-xl font-semibold tracking-[-0.02em]">{{ __('blog.sources') }}</h2>
                    <ul class="mt-4 grid gap-1 text-meta">
                        @foreach ($article->sources as $source)
                            <li><a href="{{ $source }}" rel="noopener" class="inline-flex min-h-11 items-center break-all text-primary underline underline-offset-4">{{ $source }}</a></li>
                        @endforeach
                    </ul>
                </section>
            </article>
        </div>

        <aside data-test="article-cta" class="mx-auto mt-14 w-full max-w-[1000px] px-5 sm:px-8">
            <div class="grid gap-5 rounded-2xl border bg-accent p-7 text-accent-foreground sm:p-10">
                <h2 class="text-2xl font-semibold tracking-[-0.02em] sm:text-3xl">{{ __('blog.cta_title') }}</h2>
                <p class="max-w-[56ch] leading-7 text-pretty">{{ __('blog.cta_text') }}</p>
                <div class="flex flex-wrap gap-3">
                    <a href="/#agenda-una-demo" class="inline-flex min-h-11 items-center rounded-full bg-primary px-6 text-meta font-medium text-primary-foreground transition-opacity hover:opacity-90">{{ __('blog.cta_button') }}</a>
                    <a href="{{ route($article->toolRoute) }}" class="inline-flex min-h-11 items-center rounded-full border bg-background px-6 text-meta font-medium hover:bg-accent">{{ __('blog.cta_tool', ['tool' => $article->toolName]) }}</a>
                </div>
            </div>
        </aside>

        @if ($related !== [])
            <section aria-labelledby="related" class="mx-auto mt-16 w-full max-w-[1200px] px-5 sm:px-8 lg:px-10">
                <h2 id="related" class="text-2xl font-semibold tracking-[-0.02em]">{{ __('blog.related') }}</h2>
                <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $other)
                        <li>@include('blog.partials.card', ['article' => $other, 'heading' => 'h3'])</li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
@endsection
