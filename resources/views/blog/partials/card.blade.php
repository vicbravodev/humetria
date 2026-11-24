{{-- A card of the blog: cover, its tool in its family's color, title, description. The whole card is the link (the title's, stretched). --}}
@php($featured = $featured ?? false)
@php($eager = $eager ?? false)
<article class="family-{{ $article->family }} group relative flex h-full flex-col overflow-hidden rounded-2xl border bg-card text-card-foreground transition-shadow duration-200 hover:shadow-raised {{ $featured ? 'lg:grid lg:grid-cols-[1.15fr_1fr]' : '' }}">
    <img src="{{ $article->cover() }}" width="1200" height="630" alt="Portada: {{ $article->toolName }}"
         @if ($eager) fetchpriority="high" @else loading="lazy" decoding="async" @endif
         class="aspect-[1200/630] w-full object-cover {{ $featured ? 'lg:h-full lg:min-h-[320px]' : '' }}">
    <div class="flex flex-1 flex-col gap-3 p-5 sm:p-6 {{ $featured ? 'lg:justify-center lg:p-10' : '' }}">
        <p class="flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-muted-foreground">
            <span class="rounded-full bg-family-soft px-2.5 py-0.5 font-medium text-family">{{ $article->toolName }}</span>
            <span><time datetime="{{ $article->publishedAt->toDateString() }}">{{ $article->publishedAt->translatedFormat('j \d\e F \d\e Y') }}</time> · {{ __('blog.reading', ['minutes' => $article->readingMinutes]) }}</span>
        </p>
        <{{ $featured ? 'h2' : ($heading ?? 'h2') }} class="font-semibold tracking-[-0.02em] text-balance {{ $featured ? 'text-2xl leading-tight sm:text-[32px] sm:leading-[1.1]' : 'text-xl leading-snug' }}">
            <a href="{{ route('blog.show', $article->slug) }}" class="rounded-md after:absolute after:inset-0 after:content-[''] group-hover:text-family focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">{{ $article->title }}</a>
        </{{ $featured ? 'h2' : ($heading ?? 'h2') }}>
        <p class="text-meta text-muted-foreground {{ $featured ? 'sm:text-base' : '' }}">{{ $article->description }}</p>
    </div>
</article>
