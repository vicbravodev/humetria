@extends('blog.layout', ['title' => $article->title, 'description' => $article->description, 'current' => 'blog'])

@section('head')
    <meta property="og:type" content="article">
    <meta property="article:published_time" content="{{ $article->publishedAt->toDateString() }}">
    <meta property="article:modified_time" content="{{ $article->updatedAt->toDateString() }}">
    <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! $jsonLd !!}</script>
@endsection

@section('content')
    <article class="mx-auto w-full max-w-[760px] px-5 py-10 sm:px-8 sm:py-14">
        <nav aria-label="Migas" class="text-caption text-muted-foreground">
            <a href="{{ route('blog.index') }}" class="inline-flex min-h-11 items-center rounded-md hover:text-foreground">{{ __('blog.back') }}</a>
        </nav>

        <header>
            <h1 class="mt-2 text-[32px] leading-[1.08] font-semibold tracking-[-0.035em] text-balance sm:text-[44px]">{{ $article->title }}</h1>
            <p class="mt-4 text-lg leading-7 text-pretty text-muted-foreground">{{ $article->description }}</p>
            <p class="mt-5 text-caption text-muted-foreground">
                @if ($article->reviewedAt)
                    {{ __('blog.reviewed', ['date' => $article->reviewedAt->translatedFormat('j \d\e F \d\e Y')]) }} ·
                @endif
                @if ($article->updatedAt->gt($article->publishedAt))
                    {{ __('blog.updated', ['date' => $article->updatedAt->translatedFormat('j \d\e F \d\e Y')]) }} ·
                @endif
                {{ __('blog.reading', ['minutes' => $article->readingMinutes]) }}
            </p>
        </header>

        <div class="blog-prose mt-10">{!! $article->html !!}</div>

        <section aria-labelledby="sources" class="mt-12 border-t pt-8">
            <h2 id="sources" class="text-xl font-semibold tracking-[-0.02em]">{{ __('blog.sources') }}</h2>
            <ul class="mt-4 grid gap-2 text-meta">
                @foreach ($article->sources as $source)
                    <li><a href="{{ $source }}" rel="noopener" class="break-all text-primary underline underline-offset-4">{{ $source }}</a></li>
                @endforeach
            </ul>
        </section>

        <aside class="mt-12 rounded-xl border bg-accent p-6 text-accent-foreground">
            <h2 class="text-xl font-semibold tracking-[-0.02em]">{{ __('blog.cta_title') }}</h2>
            <p class="mt-2 text-meta">{{ __('blog.cta_text') }}</p>
            <a href="/#agenda-una-demo" class="mt-5 inline-flex min-h-11 items-center rounded-full bg-primary px-5 text-meta font-medium text-primary-foreground transition-opacity hover:opacity-90">{{ __('blog.cta_button') }}</a>
        </aside>
    </article>
@endsection
