<?php

declare(strict_types=1);

use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Support\TourCatalog;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/*
 * The catalog of a guided session: «El centro y su gente» first, then the tours Victor chose, in his order. Each step's
 * copy is fixed (driver.js writes it as HTML): no placeholder and nothing of the demo company.
 */

uses(TestCase::class);

/** @return list<TourStep> every step of every tour */
function everyStep(): array
{
    return resolve(TourCatalog::class)->for(TourKey::cases());
}

/**
 * @param  list<TourStep>  $steps
 * @return list<TourKey> the tours of `$steps`, each once, in their order
 */
function toursOf(array $steps): array
{
    return array_values(array_unique(array_map(fn (TourStep $step): TourKey => $step->tour, $steps), SORT_REGULAR));
}

it('puts base first and keeps the chosen order', function (): void {
    $catalog = resolve(TourCatalog::class);

    expect(toursOf($catalog->for([TourKey::Leave, TourKey::Nom035])))->toBe([TourKey::Base, TourKey::Leave, TourKey::Nom035])
        ->and(toursOf($catalog->for([TourKey::Humi, TourKey::Base])))->toBe([TourKey::Base, TourKey::Humi])
        ->and(toursOf($catalog->for([])))->toBe([TourKey::Base])
        ->and(toursOf(everyStep()))->toBe(TourKey::cases());
});

it('has unique step keys and routes that exist', function (): void {
    $keys = array_map(fn (TourStep $step): string => $step->key, everyStep());

    expect($keys)->toBe(array_values(array_unique($keys)));

    foreach (everyStep() as $step) {
        $route = Route::getRoutes()->getByName($step->route);

        expect($step->key)->toStartWith($step->tour->value.'.')
            ->and($step->kind)->toBeIn(['do', 'watch'])
            ->and($route)->not->toBeNull("{$step->key}: {$step->route}")
            ->and($route?->methods())->toContain('GET')
            ->and($step->route)->toStartWith('tenant.');
    }
});

it('lists every spot it lights up in one place, in kebab-case', function (): void {
    $targets = array_values(array_unique(array_filter(array_map(fn (TourStep $step): ?string => $step->target, everyStep()))));
    sort($targets);
    $listed = TourCatalog::TARGETS;
    sort($listed);

    expect($targets)->toBe($listed)
        ->and($listed)->each->toMatch('/\A[a-z]+(-[a-z]+)*\z/');
});

it('writes no company data in the copy', function (): void {
    foreach (everyStep() as $step) {
        foreach (array_filter([$step->title, $step->todo, $step->why, $step->ask]) as $copy) {
            expect($copy)->not->toContain('{')
                ->not->toContain('}')
                ->not->toContain('<')
                ->not->toContain('@')
                ->not->toContain('Alba')
                ->not->toContain('Manufacturas')
                ->not->toContain('Bajío')
                ->not->toContain('Sala ·');
        }

        // «Por qué importa» speaks to the client in one or two sentences.
        expect(preg_match_all('/[.!?](\s|\z)/u', $step->why))->toBeBetween(1, 2, $step->key)
            ->and($step->title)->not->toBe('')
            ->and($step->todo)->not->toBe('');
    }
});
