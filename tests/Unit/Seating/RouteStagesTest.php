<?php

declare(strict_types=1);

use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Support\ComplianceRow;
use App\Modules\Seating\Support\RouteStages;
use App\Modules\Tenancy\Data\RouteProgress;

mutates(RouteStages::class);

/**
 * The thirteen requirements of a center, all met but those given.
 *
 * @param  array<string, array{0: RowState, 1?: int}>  $states  by key, with the count behind it
 * @return list<ComplianceRow>
 */
function seatingRows(array $states = []): array
{
    return array_map(fn (int $number): ComplianceRow => new ComplianceRow("r{$number}", $states["r{$number}"][0] ?? RowState::Met, $states["r{$number}"][1] ?? 0), range(1, 13));
}

it('does not apply where the center declared that nobody works standing and has no position', function (): void {
    expect(RouteStages::from(seatingRows(), true, false, '3 oct 2026'))
        ->toEqual(RouteProgress::notApplicable('Nadie trabaja de pie · declarado el 3 oct 2026'))
        // Positions registered after the declaration take it back.
        ->and(RouteStages::from(seatingRows(), true, true, '3 oct 2026')->applies)->toBeTrue()
        ->and(RouteStages::from(seatingRows(), false, false, null)->applies)->toBeTrue();
});

it('takes each stage from its requirements, in the order of the route', function (string $key, int $stage, string $status): void {
    $done = [true, true, true, true, true, true];
    $done[$stage - 1] = false;

    expect(RouteStages::from(seatingRows([$key => [RowState::Warning]]), false, true, null))->toEqual(new RouteProgress(true, $done, $status));
})->with([
    'R1 positions' => ['r1', 1, 'identifica los puestos de pie'],
    'R4 seats' => ['r4', 2, 'pon asientos donde hacen falta'],
    'R5 rest areas' => ['r5', 2, 'pon asientos donde hacen falta'],
    'R6 signs' => ['r6', 2, 'pon asientos donde hacen falta'],
    'R2 reassessment due' => ['r2', 3, 'evalúa a cada persona que trabaja de pie'],
    'R3 minutes' => ['r3', 3, 'evalúa a cada persona que trabaja de pie'],
    'R8 confirmation' => ['r8', 3, 'evalúa a cada persona que trabaja de pie'],
    'R9 referral' => ['r9', 3, 'evalúa a cada persona que trabaja de pie'],
    'R7 measures' => ['r7', 4, 'acuerda pausas y medidas'],
    'R11 pauses' => ['r11', 4, 'acuerda pausas y medidas'],
    'R10 regulation' => ['r10', 5, 'actualiza tu reglamento interior'],
    'R12 diagnosis' => ['r12', 6, 'documenta para la inspección'],
    'R13 committee' => ['r13', 6, 'documenta para la inspección'],
]);

it('counts the people left to assess', function (): void {
    expect(RouteStages::from(seatingRows(['r2' => [RowState::Missing, 6]]), false, true, null)->status)->toBe('evalúa a 6 personas')
        ->and(RouteStages::from(seatingRows(['r2' => [RowState::Missing, 1]]), false, true, null)->status)->toBe('evalúa a 1 persona');
});

it('is up to date when every requirement is met, and tells the earliest stage left otherwise', function (): void {
    expect(RouteStages::from(seatingRows(), false, true, null))->toEqual(new RouteProgress(true, [true, true, true, true, true, true], 'todo al día'))
        ->and(RouteStages::from(seatingRows(['r10' => [RowState::Missing, 1], 'r4' => [RowState::Missing, 1]]), false, true, null))
        ->toEqual(new RouteProgress(true, [true, false, true, true, false, true], 'pon asientos donde hacen falta'));
});
