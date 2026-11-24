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
                    <li class="relative">
                        @if ($link['kind'] === 'group')
                            @php($inside = collect($link['items'])->contains(fn ($item) => $item['id'] === $current))
                            <details class="group/menu" data-menu>
                                <summary data-test="{{ $link['test'] }}" class="flex h-9 cursor-pointer list-none items-center gap-1.5 rounded-full px-4 text-sm font-medium transition-colors duration-150 ease-out [&::-webkit-details-marker]:hidden {{ $inside ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground' }}">
                                    {{ $link['label'] }}
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 transition-transform duration-200 group-open/menu:rotate-180"><path d="m6 9 6 6 6-6"/></svg>
                                </summary>
                                <div class="absolute top-full right-0 z-20 mt-3 grid w-[360px] gap-1 rounded-3xl border bg-card p-3 text-left shadow-raised">
                                    @foreach ($link['items'] as $item)
                                        <a data-test="{{ $item['test'] }}" href="{{ App\Http\PublicNav::href($item) }}"
                                           @if ($item['id'] === $current) aria-current="page" @endif
                                           class="group grid min-h-11 grid-cols-[40px_minmax(0,1fr)] items-center gap-3 rounded-xl px-2.5 py-2 hover:bg-accent {{ $item['id'] === $current ? 'bg-accent' : '' }}">
                                            @if (isset($item['image']))
                                                {{-- Humi has its own face: the avatar of the design system, in light and dark. --}}
                                                <span class="grid size-10 place-items-center">
                                                    <img src="/brand/{{ $item['image'] }}.svg" alt="" width="40" height="40" class="size-10 dark:hidden">
                                                    <img src="/brand/{{ $item['image'] }}-oscuro.svg" alt="" width="40" height="40" class="hidden size-10 dark:block">
                                                </span>
                                            @else
                                                <span class="grid size-10 place-items-center rounded-xl bg-accent text-primary">
                                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-5">@foreach (App\Http\PublicNav::iconPaths($item['id']) as $d)<path d="{{ $d }}"/>@endforeach</svg>
                                                </span>
                                            @endif
                                            <span class="grid gap-0.5">
                                                <span class="font-semibold {{ $item['id'] === $current ? 'text-primary' : '' }}">{{ $item['label'] }}</span>
                                                <span class="text-caption text-muted-foreground">{{ $item['hint'] }}</span>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </details>
                        @elseif ($link['id'] === 'herramientas')
                            <details class="group/menu" data-menu>
                                <summary data-test="tools-menu" class="flex h-9 cursor-pointer list-none items-center gap-1.5 rounded-full px-4 text-sm font-medium text-muted-foreground transition-colors duration-150 ease-out hover:text-foreground [&::-webkit-details-marker]:hidden">
                                    {{ $link['label'] }}
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="size-3.5 transition-transform duration-200 group-open/menu:rotate-180"><path d="m6 9 6 6 6-6"/></svg>
                                </summary>
                                <nav id="tools-menu" aria-label="Herramientas" class="absolute top-full left-1/2 z-20 mt-3 grid w-[min(880px,90vw)] -translate-x-1/2 gap-5 rounded-3xl border bg-card p-5 text-left shadow-raised sm:grid-cols-3">
                                    @foreach (App\Http\PublicNav::families() as $family)
                                        <div class="family-{{ $family['key'] }} grid content-start gap-1">
                                            <div class="grid gap-0.5 px-2.5 pb-2">
                                                <p class="text-caption font-semibold text-family">{{ $family['name'] }}</p>
                                                <p class="text-caption text-muted-foreground">{{ $family['hint'] }}</p>
                                            </div>
                                            @foreach (collect(App\Http\PublicNav::tools())->where('family', $family['key']) as $tool)
                                                <a href="{{ route($tool['route'], absolute: false) }}" class="group grid min-h-11 grid-cols-[40px_minmax(0,1fr)] items-center gap-3 rounded-xl px-2.5 py-2 transition-colors duration-150 ease-out hover:bg-muted">
                                                    <span class="grid size-10 place-items-center rounded-xl bg-family-soft text-family">
                                                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" class="size-5">@foreach ($tool['paths'] as $d)<path d="{{ $d }}"/>@endforeach</svg>
                                                    </span>
                                                    <span class="grid gap-0.5">
                                                        <span class="font-semibold">{{ $tool['name'] }}</span>
                                                        <span class="text-caption text-muted-foreground">{{ $tool['hint'] }}</span>
                                                    </span>
                                                </a>
                                            @endforeach
                                        </div>
                                    @endforeach
                                    <div class="flex flex-wrap gap-x-6 gap-y-1 border-t pt-3 sm:col-span-3">
                                        <a data-test="header-tools" href="/#herramientas" class="inline-flex min-h-11 items-center rounded-xl px-2.5 font-semibold text-primary">Ver las nueve herramientas</a>
                                        <a href="{{ route('guides.workday-2027', absolute: false) }}" class="inline-flex min-h-11 items-center rounded-xl px-2.5 font-semibold text-primary">Guía: qué cambia con el registro de jornada en 2027</a>
                                    </div>
                                </nav>
                            </details>
                        @else
                            <a data-test="{{ $link['test'] }}" href="{{ App\Http\PublicNav::href($link) }}"
                               class="flex h-9 items-center rounded-full px-4 text-sm font-medium text-muted-foreground transition-colors duration-150 ease-out hover:text-foreground">{{ $link['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="relative flex items-center gap-1 justify-self-end">
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-md px-3 text-base font-normal whitespace-nowrap transition-colors hover:bg-accent sm:min-h-9">{{ __('blog.login') }}</a>
            <a data-test="header-demo" href="/#agenda-una-demo" class="ml-1 inline-flex min-h-11 items-center rounded-full bg-primary px-4 sm:min-h-9 text-sm font-medium whitespace-nowrap text-primary-foreground transition-opacity hover:opacity-90">
                <span class="sm:hidden">Demo</span><span class="hidden sm:inline">{{ __('blog.cta_button') }}</span>
            </a>
            <details class="group ml-1 lg:hidden">
                <summary aria-label="{{ __('blog.menu') }}" class="flex size-11 cursor-pointer list-none items-center justify-center rounded-md text-foreground [&::-webkit-details-marker]:hidden">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="size-5 group-open:hidden"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="hidden size-5 group-open:block"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </summary>
                <nav aria-label="Menú" class="fixed inset-x-4 top-[76px] grid max-h-[calc(100dvh-96px)] gap-1 overflow-y-auto rounded-3xl border bg-card p-3 shadow-raised">
                    @foreach (App\Http\PublicNav::flat() as $link)
                        <a data-test="menu-{{ $link['id'] }}" href="{{ App\Http\PublicNav::href($link) }}"
                           @if ($link['id'] === $current) aria-current="page" @endif
                           class="flex min-h-11 items-center rounded-xl px-3 text-base font-medium {{ $link['id'] === $current ? 'bg-accent text-primary' : 'hover:bg-accent' }}">{{ $link['label'] }}</a>
                    @endforeach
                    <button type="button" data-theme-toggle class="flex min-h-11 items-center rounded-xl px-3 text-start text-base font-medium hover:bg-accent">{{ __('blog.theme') }}</button>
                </nav>
            </details>
        </div>
    </div>
</header>
