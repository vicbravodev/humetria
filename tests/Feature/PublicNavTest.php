<?php

declare(strict_types=1);

use App\Http\PublicNav;

it('keeps the bar to four things: three links and a Recursos group that holds the rest', function (): void {
    $links = collect(PublicNav::links());

    expect($links->pluck('id')->all())->toBe(['herramientas', 'como-funciona', 'precios', 'recursos'])
        ->and($links->firstWhere('id', 'recursos')['kind'])->toBe('group')
        ->and(collect($links->firstWhere('id', 'recursos')['items'])->pluck('id')->all())
        ->toBe(['blog', 'roadmap', 'guia-jornada', 'confidencialidad', 'humi']);
});

it('lists the pages that were only in the footer, as plain links to their real routes', function (): void {
    $pages = collect(PublicNav::flat())->where('kind', 'page');

    expect($pages->pluck('path', 'id')->all())->toBe(['blog' => '/blog', 'roadmap' => '/roadmap', 'guia-jornada' => '/guias/registro-de-jornada-2027']);

    foreach ($pages as $page) {
        expect(route($page['route'], absolute: false))->toBe($page['path']);
    }
});

it('flattens the group for the phone menu, in the order of the bar', function (): void {
    expect(collect(PublicNav::flat())->pluck('id')->all())
        ->toBe(['herramientas', 'como-funciona', 'precios', 'blog', 'roadmap', 'guia-jornada', 'confidencialidad', 'humi']);
});
