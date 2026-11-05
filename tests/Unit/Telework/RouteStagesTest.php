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

it('does not apply where nobody teleworks nor is about to', function (): void {
    expect(RouteStages::from(tableRows(['5.1' => RowState::NotApplicable])))->toEqual(RouteProgress::notApplicable('Nadie teletrabaja en este centro'));
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
