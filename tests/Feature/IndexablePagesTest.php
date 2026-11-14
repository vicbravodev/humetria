<?php

declare(strict_types=1);

use App\Http\IndexablePages;
use Illuminate\Support\Facades\Route;

mutates(IndexablePages::class);

it('lists the pages search engines may index', function (): void {
    expect(IndexablePages::includes('home'))->toBeTrue()
        ->and(IndexablePages::includes('privacy-notice'))->toBeTrue();
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
