<?php

declare(strict_types=1);

use App\Http\PublicNav;

it('lists the sections of the landing and the two pages that were only in the footer', function (): void {
    $links = collect(PublicNav::links());

    expect($links->pluck('id')->all())->toBe(['como-funciona', 'herramientas', 'confidencialidad', 'humi', 'precios', 'blog', 'roadmap'])
        ->and($links->firstWhere('id', 'blog'))->toMatchArray(['kind' => 'page', 'path' => '/blog', 'label' => 'Blog'])
        ->and($links->firstWhere('id', 'roadmap'))->toMatchArray(['kind' => 'page', 'path' => '/roadmap', 'label' => 'Roadmap'])
        ->and($links->firstWhere('id', 'precios'))->toMatchArray(['kind' => 'section', 'section' => 'seccion-precios']);
});

it('points every page link at a real route, so the header cannot link to a page that moved', function (): void {
    foreach (collect(PublicNav::links())->where('kind', 'page') as $link) {
        expect(route($link['route'], absolute: false))->toBe($link['path']);
    }
});
