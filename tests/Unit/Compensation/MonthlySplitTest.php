<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\Calculation\MonthlySplit;

mutates(MonthlySplit::class);

it('splits a year of twelfths so the months add up to the rounded year', function (): void {
    // 28,725 cents a year is 2,393.75 a month: nine months carry the extra cent.
    $months = MonthlySplit::of(array_fill(0, 12, 28_725));

    expect(array_sum($months))->toBe(28_725)
        ->and($months)->toBe([2394, 2394, 2394, 2394, 2394, 2394, 2394, 2394, 2394, 2393, 2393, 2393]);
});

it('gives the extra cents to the largest remainders, the earliest month on a tie', function (): void {
    // 7 + 11 + 3 = 21 twelfths of a cent is 1.75: two cents, to the remainders of 11 and 7.
    expect(MonthlySplit::of([7, 11, 3, 0, 0, 0, 0, 0, 0, 0, 0, 0]))->toBe([1, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])
        ->and(MonthlySplit::of([3, 3, 11, 0, 0, 0, 0, 0, 0, 0, 0, 0]))->toBe([0, 0, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0])
        ->and(MonthlySplit::of([6, 6, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]))->toBe([1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
});

it('rounds a half up and drops less than a half', function (): void {
    expect(array_sum(MonthlySplit::of([6, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])))->toBe(1)
        ->and(array_sum(MonthlySplit::of([5, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])))->toBe(0)
        ->and(array_sum(MonthlySplit::of([17, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])))->toBe(1)
        ->and(array_sum(MonthlySplit::of([18, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0])))->toBe(2);
});

it('keeps whole months whole', function (): void {
    expect(MonthlySplit::of([1_200, 1_200, 2_400, 0, 0, 0, 0, 0, 0, 0, 0, 0]))->toBe([100, 100, 200, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
});

it('gives nothing for no months and refuses a negative amount', function (): void {
    expect(MonthlySplit::of([]))->toBe([]);

    MonthlySplit::of([100, -1]);
})->throws(InvalidArgumentException::class);
