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

mutates(TourCatalog::class);

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

        // A step done by opening a page waits for a panel page that exists: its name is what the guide records.
        if ($step->visit !== null) {
            expect(Route::getRoutes()->getByName($step->visit)?->methods() ?? ["{$step->key}: {$step->visit}"])->toContain('GET')
                ->and($step->visit)->toStartWith('tenant.');
        }
    }

    expect(resolve(TourCatalog::class)->visits())->toBe(array_values(array_filter(array_map(fn (TourStep $step): ?string => $step->visit, everyStep()))));
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

it('promises no hiding the demo cannot keep: its group minimum is 1', function (): void {
    foreach (everyStep() as $step) {
        expect(mb_strtolower($step->title.' '.$step->todo.' '.$step->why))->not->toContain('sin exponer')
            ->not->toContain('nunca quién')
            ->not->toContain('nadie puede');
    }

    $results = array_filter(everyStep(), fn (TourStep $step): bool => in_array($step->key, ['nom035.results', 'engagement.results'], true));

    expect($results)->toHaveCount(2)
        ->each(fn ($step) => $step->why->toContain('En la demo el mínimo por grupo es 1'));
});

it('says what the platform does in plain words, without promising what it cannot', function (): void {
    $why = array_column(array_map(fn (TourStep $step): array => [$step->key, $step->why], everyStep()), 1, 0);

    // «Anónima» promises more than the demo keeps with its minimum of 1: say what is kept instead.
    expect($why['engagement.launch'])->toContain('cada respuesta se guarda sin el nombre de quien contesta')
        ->not->toContain('anónima')
        // RH sees the results once it closes: only while it runs does RH see just how many answered.
        ->and($why['nom035.answers'])->toContain('Mientras tanto, RH solo ve cuántas van')
        ->and($why['engagement.answers'])->toContain('Mientras tanto, RH solo ve cuántas van')
        // The date of entry two years back is what the vacation tour needs: the balance the law gives.
        ->and($why['base.people'])->toContain('con su antigüedad, sus vacaciones ya salen con el saldo que marca la ley')
        ->and($why['attendance.mode'])->toContain('Una oficina marca con el QR de la entrada; quien trabaja en campo, desde donde esté');
});
