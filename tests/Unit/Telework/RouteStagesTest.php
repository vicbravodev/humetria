<?php

declare(strict_types=1);

use App\Modules\Telework\Enums\RowState;
use App\Modules\Telework\Support\ComplianceRow;
use App\Modules\Telework\Support\RouteStages;
use App\Modules\Tenancy\Data\RouteProgress;

mutates(RouteStages::class);

const TABLE_10_3 = ['5.1', '5.1.1', '5.1.2', '5.2', '5.3', '5.4 y 7', '5.5', '5.5.1', '5.6', '5.7', '5.8', '5.9 y 8', '5.10', '5.11', '5.12', '5.13', '5.14'];

/**
 * The 17 rows of a center, all done but those given.
 *
 * @param  array<string, RowState>  $states  by numeral
 * @return list<ComplianceRow>
 */
function tableRows(array $states = []): array
{
    return array_map(fn (string $numeral): ComplianceRow => new ComplianceRow($numeral, $numeral, $states[$numeral] ?? RowState::Done, null), TABLE_10_3);
}

// Rediseño v2 (decisión A2): with nobody in the padrón, the center's answer to «¿Te aplica?» decides.
it('waits for the answer of a center where nobody teleworks nor is about to', function (): void {
    expect(RouteStages::from(tableRows(['5.1' => RowState::NotApplicable])))->toEqual(RouteProgress::unanswered());
});

it('does not apply where the center declared that nobody works away more than 40 % of the time', function (): void {
    expect(RouteStages::from(tableRows(['5.1' => RowState::NotApplicable]), false, '3 feb 2026'))
        ->toEqual(RouteProgress::notApplicable('Nadie teletrabaja más del 40 % · constancia del 3 feb 2026'));
});

it('keeps the people stages ahead where the center declared that someone teleworks and nobody is registered yet', function (): void {
    expect(RouteStages::from(tableRows(['5.1' => RowState::NotApplicable]), true, '3 feb 2026'))
        ->toEqual(new RouteProgress(true, [true, false, false, false, true, false], 'registra a quién teletrabaja y dónde'));
});

it('follows the padrón over the declaration once someone is in it', function (): void {
    expect(RouteStages::from(tableRows(), false, '3 feb 2026'))->toEqual(new RouteProgress(true, [true, true, true, true, true, true], 'todo al día'));
});

it('starts with the rules: the policy first, then the documents that support it', function (): void {
    expect(RouteStages::from(tableRows(['5.2' => RowState::Missing, '5.13' => RowState::Missing])))
        ->toEqual(new RouteProgress(true, [false, true, true, true, true, false], 'define las reglas'))
        ->and(RouteStages::from(tableRows(['5.2' => RowState::Partial])))
        ->toEqual(new RouteProgress(true, [false, true, true, true, true, false], 'define las reglas'))
        ->and(RouteStages::from(tableRows(['5.3' => RowState::Partial, '5.14' => RowState::Missing])))
        ->toEqual(new RouteProgress(true, [false, true, true, true, true, false], 'prepara los documentos de apoyo'));
});

it('takes each stage from its rows of table 10.3, in the order of the route', function (string $numeral, int $stage, string $status): void {
    $done = [true, true, true, true, true, false];
    $done[$stage - 1] = false;

    expect(RouteStages::from(tableRows([$numeral => RowState::Partial])))->toEqual(new RouteProgress(true, $done, $status));
})->with([
    '5.1 people' => ['5.1', 2, 'registra a quién teletrabaja y dónde'],
    '5.1.2 places' => ['5.1.2', 2, 'registra a quién teletrabaja y dónde'],
    '5.1.1 conditions' => ['5.1.1', 3, 'revisa que cada lugar sea seguro'],
    '5.4 checklist' => ['5.4 y 7', 3, 'revisa que cada lugar sea seguro'],
    '5.5 validation' => ['5.5', 3, 'revisa que cada lugar sea seguro'],
    '5.5.1 periodic' => ['5.5.1', 3, 'revisa que cada lugar sea seguro'],
    '5.12 committee' => ['5.12', 3, 'revisa que cada lugar sea seguro'],
    '5.7 equipment' => ['5.7', 4, 'entrega equipo y capacita'],
    '5.9 training' => ['5.9 y 8', 4, 'entrega equipo y capacita'],
    '5.11 notices' => ['5.11', 5, 'atiende lo que avisan las personas'],
]);

it('counts a row that does not apply as done, and has the dossier ready once every stage is', function (): void {
    expect(RouteStages::from(tableRows(['5.11' => RowState::NotApplicable, '5.7' => RowState::NotApplicable])))
        ->toEqual(new RouteProgress(true, [true, true, true, true, true, true], 'todo al día'));
});

it('tells the earliest stage left when several are', function (): void {
    expect(RouteStages::from(tableRows(['5.11' => RowState::Missing, '5.7' => RowState::Missing])))
        ->toEqual(new RouteProgress(true, [true, true, true, false, false, false], 'entrega equipo y capacita'));
});

it('does not take the place, equipment and training as done while the padrón has only aspirants', function (): void {
    $aspirants = ['5.1' => RowState::Missing, '5.1.1' => RowState::NotApplicable, '5.5' => RowState::NotApplicable, '5.7' => RowState::NotApplicable, '5.9 y 8' => RowState::NotApplicable];

    expect(RouteStages::from(tableRows($aspirants), true)->done)->toBe([true, false, false, false, true, false]);
});

it('does not take the stages as done for a person without a validated place', function (): void {
    expect(RouteStages::from(tableRows(['5.1' => RowState::Done, '5.1.1' => RowState::Missing]), true)->done)->toBe([true, true, false, true, true, false]);
});

it('takes the people stages as done with a person in the padrón, a validated place, equipment and training', function (): void {
    expect(RouteStages::from(tableRows(), true)->done)->toBe([true, true, true, true, true, true]);
});

// C-02: with nobody in the padrón and no notice, «cada aviso revisado» is not done: there was nothing to review.
it('does not take the notices stage as done while nobody is in the padrón and there is no notice', function (): void {
    $nobody = ['5.1' => RowState::NotApplicable, '5.11' => RowState::NotApplicable];

    expect(RouteStages::from(tableRows($nobody), true)->done)->toBe([true, false, false, false, false, false])
        ->and(RouteStages::from(tableRows([...$nobody, '5.11' => RowState::Done]), true)->done[4])->toBeTrue();
});
