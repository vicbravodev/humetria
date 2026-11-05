<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\TenureTable;

mutates(TenureTable::class);

it('accepts a table that ascends by years', function (): void {
    expect(TenureTable::problem([['from_years' => 0, 'value' => 0], ['from_years' => 1, 'value' => 1200], ['from_years' => 30, 'value' => 3200]]))->toBeNull()
        ->and(TenureTable::problem([['from_years' => 5, 'value' => 100]]))->toBeNull()
        ->and(TenureTable::problem([]))->toBeNull();
});

it('refuses a table out of order, with a repeated year, with a negative number or too long', function (): void {
    $tables = [
        'out of order' => [['from_years' => 5, 'value' => 100], ['from_years' => 1, 'value' => 100]],
        'a repeated year' => [['from_years' => 1, 'value' => 100], ['from_years' => 1, 'value' => 200]],
        'a negative year' => [['from_years' => -1, 'value' => 100]],
        'a negative value' => [['from_years' => 1, 'value' => -1]],
        'a year too far' => [['from_years' => 81, 'value' => 100]],
        'too many rows' => array_map(fn (int $year): array => ['from_years' => $year, 'value' => 1], range(0, 40)),
    ];

    foreach ($tables as $name => $rows) {
        expect(TenureTable::problem($rows))->toBeString($name);
    }
});

it('reads the value in force at a year of service', function (): void {
    $rows = [['from_years' => 1, 'value' => 1200], ['from_years' => 5, 'value' => 2000]];

    expect(TenureTable::valueAt($rows, 999, 0))->toBe(999)
        ->and(TenureTable::valueAt($rows, 999, 1))->toBe(1200)
        ->and(TenureTable::valueAt($rows, 999, 4))->toBe(1200)
        ->and(TenureTable::valueAt($rows, 999, 5))->toBe(2000)
        ->and(TenureTable::valueAt($rows, 999, 40))->toBe(2000)
        ->and(TenureTable::valueAt(null, 700, 12))->toBe(700)
        ->and(TenureTable::valueAt([], 700, 12))->toBe(700);
});
