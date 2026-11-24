{{--
    The header of the landing, for the pages that are Blade (the blog): same logo, same links in the same order (they come
    from App\Http\PublicNav, the file the React header reads too), same pill, same two ways in. No framework: «Herramientas»
    is a plain link to its section and the phone menu is a native <details>. Only the theme button needs a script, and a small one.
--}}
@php($links = App\Http\PublicNav::links())
@php($current = $current ?? '')
<header class="sticky top-0 z-10 border-b bg-background/80 backdrop-blur-xl backdrop-saturate-150">
    <div class="mx-auto grid h-16 w-full max-w-[1200px] grid-cols-[1fr_auto] items-center gap-3 px-5 sm:px-8 lg:grid-cols-[1fr_auto_1fr] lg:px-10 min-[1700px]:max-w-[1360px]">
        <a href="/" aria-label="Humetría, inicio" class="flex min-h-11 min-w-11 items-center justify-self-start rounded-md">
            <span class="sm:hidden">
                <img src="/brand/humetria-simbolo-color.svg" alt="" class="size-8 dark:hidden">
                <img src="/brand/humetria-simbolo-color-oscuro.svg" alt="" class="hidden size-8 dark:block">
            </span>
            <span class="hidden sm:block">
                <img src="/brand/humetria-horizontal-color.svg" alt="Humetría" class="h-7 w-auto dark:hidden">
                <img src="/brand/humetria-horizontal-color-oscuro.svg" alt="Humetría" class="hidden h-7 w-auto dark:block">
            </span>
        </a>

        <nav aria-label="Principal" class="hidden lg:block">
            <ul class="flex items-center rounded-full border bg-muted/70 p-1 backdrop-blur-md">
                @foreach ($links as $link)
                    <li>
                        <a data-test="{{ $link['test'] }}" href="{{ App\Http\PublicNav::href($link) }}"
                           @if ($link['id'] === $current) aria-current="page" @endif
                           class="flex h-9 items-center rounded-full px-4 text-sm font-medium transition-colors duration-150 ease-out {{ $link['id'] === $current ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground' }}">{{ $link['label'] }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="relative flex items-center gap-1 justify-self-end">
            <button type="button" data-theme-toggle aria-label="{{ __('blog.theme') }}" class="hidden size-11 items-center justify-center rounded-md text-muted-foreground transition-colors hover:text-foreground sm:flex">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5 dark:hidden"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hidden size-5 dark:block"><path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/></svg>
            </button>
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-md px-3 text-sm font-normal text-muted-foreground transition-colors hover:text-foreground">{{ __('blog.login') }}</a>
            <a data-test="header-demo" href="/#agenda-una-demo" class="ml-1 inline-flex min-h-11 items-center rounded-full bg-primary px-4 text-sm font-medium text-primary-foreground transition-opacity hover:opacity-90">
                <span class="sm:hidden">Demo</span><span class="hidden sm:inline">{{ __('blog.cta_button') }}</span>
            </a>
            <details class="group ml-1 lg:hidden">
                <summary aria-label="{{ __('blog.menu') }}" class="flex size-11 cursor-pointer list-none items-center justify-center rounded-md text-foreground [&::-webkit-details-marker]:hidden">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5 group-open:hidden"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="hidden size-5 group-open:block"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </summary>
                <nav aria-label="Menú" class="fixed inset-x-4 top-[76px] grid max-h-[calc(100dvh-96px)] gap-1 overflow-y-auto rounded-3xl border bg-card p-3 shadow-raised">
                    @foreach ($links as $link)
                        <a data-test="menu-{{ $link['id'] }}" href="{{ App\Http\PublicNav::href($link) }}"
                           @if ($link['id'] === $current) aria-current="page" @endif
                           class="flex min-h-11 items-center rounded-xl px-3 text-base font-medium {{ $link['id'] === $current ? 'bg-accent text-primary' : 'hover:bg-accent' }}">{{ $link['label'] }}</a>
                    @endforeach
                    <button type="button" data-theme-toggle class="flex min-h-11 items-center rounded-xl px-3 text-start text-base font-medium hover:bg-accent sm:hidden">{{ __('blog.theme') }}</button>
                </nav>
            </details>
        </div>
    </div>
</header>
