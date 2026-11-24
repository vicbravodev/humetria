@extends('blog.layout', ['title' => __('blog.title'), 'description' => __('blog.description'), 'current' => 'blog'])

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
    <div class="mx-auto w-full max-w-[1200px] px-5 pt-10 pb-4 sm:px-8 sm:pt-12 lg:px-10 lg:pt-16 min-[1700px]:max-w-[1360px]">
        <h1 class="text-[32px] leading-[1.06] font-semibold tracking-[-0.035em] text-balance sm:text-[44px] lg:text-[56px]">{{ __('blog.title') }}</h1>
        <p class="mt-4 max-w-[56ch] text-lg leading-7 text-pretty text-muted-foreground lg:text-xl lg:leading-8">{{ __('blog.lead') }}</p>

        @if ($articles === [])
            <p class="mt-10 rounded-xl border bg-muted p-6 text-muted-foreground">{{ __('blog.empty') }}</p>
        @else
            @php($first = $articles[0])
            @php($rest = array_slice($articles, 1))
            <div class="mt-10">
                @include('blog.partials.card', ['article' => $first, 'featured' => true, 'eager' => true])
            </div>

            @if ($rest !== [])
                <ul class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($rest as $article)
                        <li>@include('blog.partials.card', ['article' => $article])</li>
                    @endforeach
                </ul>
            @endif
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
