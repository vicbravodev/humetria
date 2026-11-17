<?php

declare(strict_types=1);

use App\Http\IndexablePages;
use Illuminate\Support\Facades\Route;

mutates(IndexablePages::class);

it('lists the pages search engines may index', function (): void {
    expect(IndexablePages::includes('home'))->toBeTrue()
        ->and(IndexablePages::includes('privacy-notice'))->toBeTrue()
        ->and(IndexablePages::includes('roadmap'))->toBeTrue();
});

it('does not list a missing name, an unknown name or the login', function (?string $name): void {
    expect(IndexablePages::includes($name))->toBeFalse();
})->with([null, 'no-such-route', 'login']);

it('lists only public GET routes with a name, no parameters and no auth', function (): void {
    expect(IndexablePages::ROUTES)->not->toBeEmpty();

    foreach (IndexablePages::ROUTES as $name) {
        expect(Route::has($name))->toBeTrue();

        $route = Route::getRoutes()->getRoutesByName()[$name];

        expect($route->methods())->toContain('GET')
            ->and($route->parameterNames())->toBe([])
            ->and($route->gatherMiddleware())->not->toContain('auth');
    }
});

it('lists in the static sitemap exactly the indexable pages, each with its canonical path', function (): void {
    $sitemap = simplexml_load_string((string) file_get_contents(public_path('sitemap.xml')));
    $paths = [];

    foreach ($sitemap->url ?? [] as $url) {
        $paths[] = parse_url((string) $url->loc, PHP_URL_PATH);
    }

    $expected = array_map(fn (string $name): string => route($name, [], false), IndexablePages::ROUTES);
    sort($paths);
    sort($expected);

    expect($paths)->toBe($expected);
});

it('points robots.txt at the sitemap and blocks no path, so a crawler can read the noindex', function (): void {
    $lines = array_map('trim', file(public_path('robots.txt'), FILE_IGNORE_NEW_LINES) ?: []);

    expect($lines)->toContain('User-agent: *')
        ->toContain('Disallow:')
        ->toContain('Sitemap: https://humetria.com.mx/sitemap.xml')
        ->and(array_filter($lines, fn (string $line): bool => preg_match('/^Disallow:\s*\S/', $line) === 1))->toBe([]);
});
