<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Support\Calculation\Rate;

mutates(FiscalSnapshot::class);

function snapshotWith(array $stale): FiscalSnapshot
{
    return new FiscalSnapshot([
        'uma' => Rate::scaled(11_731, 0),
        'minimum_wage' => Rate::scaled(31_504, 0),
        'minimum_wage_border' => Rate::scaled(44_087, 0),
        'infonavit' => Rate::scaled(500, 4),
    ], $stale);
}

it('grows the UMA and the minimum wages that were carried past their vigencia', function (): void {
    $indexed = snapshotWith(['uma', 'minimum_wage', 'minimum_wage_border'])->indexed(Rate::scaled(11, 1));

    // 11,731 × 1.1 = 12,904.1 → 12,904 cents; 31,504 × 1.1 = 34,654.4; 44,087 × 1.1 = 48,495.7.
    expect($indexed->uma())->toBe(12_904)
        ->and($indexed->minimumWage())->toBe(34_654)
        ->and($indexed->cents('minimum_wage_border'))->toBe(48_496)
        ->and($indexed->sbcCap())->toBe(12_904 * 25)
        ->and($indexed->staleKeys())->toBe(['uma', 'minimum_wage', 'minimum_wage_border']);
});

it('keeps a value the platform knows for that date and a rate the index does not touch', function (): void {
    $indexed = snapshotWith(['minimum_wage'])->indexed(Rate::scaled(11, 1));

    expect($indexed->uma())->toBe(11_731)
        ->and($indexed->minimumWage())->toBe(34_654)
        ->and($indexed->rate('infonavit')->compareTo(Rate::scaled(500, 4)))->toBe(0);
});

it('changes nothing when nothing is stale', function (): void {
    $indexed = snapshotWith([])->indexed(Rate::scaled(11, 1));

    expect($indexed->uma())->toBe(11_731)->and($indexed->minimumWage())->toBe(31_504)->and($indexed->isStale())->toBeFalse();
});
