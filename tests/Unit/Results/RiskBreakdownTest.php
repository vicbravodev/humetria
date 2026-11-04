<?php

declare(strict_types=1);

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Data\RiskFocus;
use App\Modules\Results\Data\RiskGroup;

mutates(RiskBreakdown::class);

/**
 * @param  list<int>  $final
 * @param  list<RiskGroup>  $categories
 * @param  list<RiskGroup>  $domains
 */
function breakdown(array $final, array $categories = [], array $domains = []): RiskBreakdown
{
    return new RiskBreakdown(RiskBreakdown::AVAILABLE, array_sum($final), $final, $categories, $domains);
}

it('asks for no Programa when nobody is in Medio or higher', function (): void {
    $risk = breakdown([3, 2, 0, 0, 0], [new RiskGroup('c1', 'Categoría', null, [3, 2, 0, 0, 0])], [new RiskGroup('d1', 'Dominio', 'c1', [5, 0, 0, 0, 0])]);

    expect($risk->needingProgram())->toBe([]);
});

it('asks for a Programa for each level from Medio up, in the final score, a category and a domain', function (RiskLevel $level, array $levels): void {
    $risk = breakdown($levels, [new RiskGroup('c1', 'Categoría', null, $levels)], [new RiskGroup('d1', 'Dominio', 'c1', $levels)]);

    expect($risk->needingProgram())->toEqual([
        new RiskFocus('final', 'final', 'Calificación final', null, $level, 1),
        new RiskFocus('category', 'c1', 'Categoría', null, $level, 1),
        new RiskFocus('domain', 'd1', 'Dominio', 'c1', $level, 1),
    ]);
})->with([
    'Medio' => [RiskLevel::Medium, [4, 0, 1, 0, 0]],
    'Alto' => [RiskLevel::High, [4, 0, 0, 1, 0]],
    'Muy alto' => [RiskLevel::VeryHigh, [4, 0, 0, 0, 1]],
]);

it('takes the highest level present and counts the questionnaires in Medio or more', function (): void {
    $risk = breakdown([1, 1, 2, 1, 3], domains: [new RiskGroup('d1', 'Dominio', 'c1', [0, 1, 2, 1, 0])]);

    expect($risk->needingProgram())->toEqual([
        new RiskFocus('final', 'final', 'Calificación final', null, RiskLevel::VeryHigh, 6),
        new RiskFocus('domain', 'd1', 'Dominio', 'c1', RiskLevel::High, 3),
    ]);
});

it('leaves out the group where only Nulo and Bajo appear, whatever the others say', function (): void {
    $risk = breakdown([0, 0, 5, 0, 0], [new RiskGroup('c1', 'Categoría', null, [3, 2, 0, 0, 0]), new RiskGroup('c2', 'Otra', null, [0, 0, 1, 0, 0])]);

    expect(array_map(fn (RiskFocus $focus): string => $focus->key, $risk->needingProgram()))->toBe(['final', 'c2']);
});

it('asks for nothing without figures', function (RiskBreakdown $risk): void {
    expect($risk->needingProgram())->toBe([]);
})->with([
    'open' => fn () => RiskBreakdown::open(),
    'not scored' => fn () => RiskBreakdown::notScored(),
    'below the minimum' => fn () => RiskBreakdown::belowMinimum(),
]);

it('tells the highest level of the Programa', function (): void {
    expect(breakdown([0, 0, 1, 2, 0])->highestNeedingProgram())->toBe(RiskLevel::High)
        ->and(breakdown([5, 0, 0, 0, 0])->highestNeedingProgram())->toBeNull();
});
