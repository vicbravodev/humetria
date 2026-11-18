@extends('blog.layout', ['title' => __('blog.title'), 'description' => __('blog.description')])

@section('head')
    <meta property="og:type" content="website">
    @if ($previous)
        <link rel="prev" href="{{ $previous }}">
    @endif
    @if ($next)
        <link rel="next" href="{{ $next }}">
    @endif
@endsection

@section('content')
    <div class="mx-auto w-full max-w-[1200px] px-5 py-12 sm:px-8 sm:py-16 lg:px-10">
        <h1 class="text-[32px] leading-[1.06] font-semibold tracking-[-0.035em] text-balance sm:text-[44px]">{{ __('blog.title') }}</h1>
        <p class="mt-4 max-w-[56ch] text-lg leading-7 text-pretty text-muted-foreground">{{ __('blog.lead') }}</p>

        @if ($articles === [])
            <p class="mt-10 rounded-xl border bg-muted p-6 text-muted-foreground">{{ __('blog.empty') }}</p>
        @else
            <ul class="mt-10 grid gap-5 md:grid-cols-2">
                @foreach ($articles as $article)
                    <li class="rounded-xl border bg-card p-6 text-card-foreground">
                        <p class="text-caption text-muted-foreground">
                            <time datetime="{{ $article->publishedAt->toDateString() }}">{{ $article->publishedAt->translatedFormat('j \d\e F \d\e Y') }}</time>
                            · {{ __('blog.reading', ['minutes' => $article->readingMinutes]) }}
                        </p>
                        <h2 class="mt-2 text-xl leading-snug font-semibold tracking-[-0.02em] text-balance">
                            <a href="{{ route('blog.show', $article->slug) }}" class="rounded-md hover:text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ $article->title }}</a>
                        </h2>
                        <p class="mt-3 text-meta text-muted-foreground">{{ $article->description }}</p>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($previous || $next)
            <nav aria-label="Páginas" class="mt-10 flex justify-between gap-4">
                @if ($next)
                    <a rel="next" href="{{ $next }}" class="inline-flex min-h-11 items-center rounded-full border px-5 text-meta hover:bg-accent">{{ __('blog.previous') }}</a>
                @else
                    <span></span>
                @endif
                @if ($previous)
                    <a rel="prev" href="{{ $previous }}" class="inline-flex min-h-11 items-center rounded-full border px-5 text-meta hover:bg-accent">{{ __('blog.next') }}</a>
                @endif
            </nav>
        @endif
    </div>
@endsection
