<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\SeatingAssessment;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Instruments\Data\SeatPlacement;
use App\Modules\Instruments\Support\Seating\Assessment;

mutates(Assessment::class);

const SEATING_KEYS = ['standing_time', 'posture_change', 'surface', 'footwear', 'movement_space', 'discomfort', 'pauses'];

/** @return array<string, int> */
function seatingPoints(int $each): array
{
    return array_fill_keys(SEATING_KEYS, $each);
}

/** @return array<string, array<int, string>> The DOF tables of the reference text: «a) …» => [points => option]. */
function dofTables(): array
{
    $text = (string) file_get_contents(base_path('docs/normas/ley-silla/texto-de-referencia.md'));
    $tables = substr($text, (int) strpos($text, '### Tablas de puntaje del inciso D'));
    preg_match_all('/^\*\*([a-g])\) ([^*]+)\*\*\n\n\| Opción \| Puntos \|\n\|---\|---\|\n((?:\|.+\|\n)+)/m', $tables, $found, PREG_SET_ORDER);

    $parsed = [];
    foreach ($found as $table) {
        preg_match_all('/^\| (.+) \| ([123]) \|$/m', $table[3], $rows, PREG_SET_ORDER);
        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row[2]] = $row[1];
        }
        $parsed[$table[1]] = ['title' => preg_replace('/\s+/', ' ', $table[2]), 'options' => $options];
    }

    return $parsed;
}

it('has the seven conditions of the DOF with their exact texts and points', function (): void {
    $definition = app(SeatingAssessment::class)->definition();
    $dof = dofTables();

    expect($definition->version)->toBe('dof-2025-07-17')
        ->and($dof)->toHaveCount(7)
        ->and(array_map(fn ($c): string => $c->key, $definition->conditions))->toBe(SEATING_KEYS);

    foreach ($definition->conditions as $condition) {
        expect($condition->options)->toBe($dof[$condition->letter]['options'])
            ->and($condition->title)->toBe($dof[$condition->letter]['title']);
    }
});

it('scores 7 as Low, 8 and 14 as Medium, 15 and 21 as High', function (int $total, RiskLevel $level): void {
    $points = seatingPoints(1);
    $remaining = $total - 7;
    foreach (SEATING_KEYS as $key) {
        $add = min(2, $remaining);
        $points[$key] += $add;
        $remaining -= $add;
    }

    $score = app(SeatingAssessment::class)->score($points);

    expect($score->total)->toBe($total)->and($score->level)->toBe($level);
})->with([
    'lowest' => [7, RiskLevel::Low],
    'first medium' => [8, RiskLevel::Medium],
    'last medium' => [14, RiskLevel::Medium],
    'first high' => [15, RiskLevel::High],
    'highest' => [21, RiskLevel::High],
]);

it('rejects a point of 0 or 4', function (int $bad): void {
    $points = seatingPoints(2);
    $points['surface'] = $bad;

    app(SeatingAssessment::class)->score($points);
})->with([0, 4, -1])->throws(InvalidArgumentException::class);

it('rejects six conditions or an unknown key', function (): void {
    $six = seatingPoints(2);
    unset($six['pauses']);
    $unknown = [...$six, 'mood' => 2];

    expect(fn () => app(SeatingAssessment::class)->score($six))->toThrow(InvalidArgumentException::class)
        ->and(fn () => app(SeatingAssessment::class)->score($unknown))->toThrow(InvalidArgumentException::class);
});

it('follows Figure 1', function (bool $seated, bool $space, bool $nearby, SeatPlacement $expected): void {
    expect(app(SeatingAssessment::class)->placement($seated, $space, $nearby))->toBe($expected);
})->with([
    'seated with space, nearby irrelevant (yes)' => [true, true, true, SeatPlacement::AtWorkstation],
    'seated with space, nearby irrelevant (no)' => [true, true, false, SeatPlacement::AtWorkstation],
    'seated without space, needs to be nearby' => [true, false, true, SeatPlacement::Nearby],
    'seated without space, can go away' => [true, false, false, SeatPlacement::WithinCenter],
    'cannot sit, needs to be nearby (space)' => [false, true, true, SeatPlacement::Nearby],
    'cannot sit, needs to be nearby (no space)' => [false, false, true, SeatPlacement::Nearby],
    'cannot sit, can go away (space)' => [false, true, false, SeatPlacement::WithinCenter],
    'cannot sit, can go away (no space)' => [false, false, false, SeatPlacement::WithinCenter],
]);
