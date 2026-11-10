<?php

declare(strict_types=1);

use App\Modules\Leave\Support\StatutoryDays;
use Carbon\CarbonImmutable;

mutates(StatutoryDays::class);

// docs/normas/vacaciones/texto-de-referencia.md, art. 76 (criterio C-01).
it('gives the days of article 76 by years of service', function (int $years, int $days): void {
    expect(StatutoryDays::for($years, CarbonImmutable::parse('2026-06-01')))->toBe($days)
        ->and(StatutoryDays::current($years))->toBe($days);
})->with([
    [1, 12], [2, 14], [3, 16], [4, 18], [5, 20],
    [6, 22], [10, 22], [11, 24], [15, 24], [16, 26], [20, 26],
    [21, 28], [25, 28], [26, 30], [30, 30], [31, 32], [35, 32], [36, 34],
]);

// Article 76 as it read until 31 December 2022 (criterio C-02).
it('gives the days of the earlier table to an anniversary before the reform', function (int $years, int $days): void {
    expect(StatutoryDays::for($years, CarbonImmutable::parse('2022-12-31')))->toBe($days);
})->with([
    [1, 6], [2, 8], [3, 10], [4, 12], [5, 14], [9, 14], [10, 16], [14, 16], [15, 18],
]);

it('uses the current table from the day the reform took effect', function (): void {
    expect(StatutoryDays::REFORM)->toBe('2023-01-01')
        ->and(StatutoryDays::for(1, CarbonImmutable::parse('2023-01-01')))->toBe(12)
        ->and(StatutoryDays::for(1, CarbonImmutable::parse('2023-01-01 18:00:00')))->toBe(12)
        ->and(StatutoryDays::for(1, CarbonImmutable::parse('2022-12-31 23:59:59')))->toBe(6);
});

it('has no days before the first year of service', function (int $years): void {
    expect(fn () => StatutoryDays::for($years, CarbonImmutable::parse('2026-06-01')))->toThrow(InvalidArgumentException::class)
        ->and(fn () => StatutoryDays::current($years))->toThrow(InvalidArgumentException::class)
        ->and(fn () => StatutoryDays::for($years, CarbonImmutable::parse('2022-06-01')))->toThrow(InvalidArgumentException::class);
})->with([0, -1]);
