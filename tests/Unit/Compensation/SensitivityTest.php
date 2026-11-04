<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\Calculation\Sensitivity;
use App\Modules\Organization\Enums\StaffGroup;

mutates(Sensitivity::class);

/** The first-year cost, with its retroactivity, of a scenario that raises both groups by that fraction (0.05 is 5 %). */
function costOfRaise(Rate $raise, int $retroMonths = 0): int
{
    return ScenarioCost::firstYear(scenarioInput(['retroMonths' => $retroMonths, 'raises' => ['unionized' => $raise, 'trusted' => $raise]]))->firstYearCents();
}

it('returns the cost of one percentage point and of plus and minus one point around the proposal', function (): void {
    $result = Sensitivity::around(scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 2)]]));

    expect($result->baseCents)->toBe(costOfRaise(Rate::scaled(5, 2)))
        ->and($result->minusOneCents)->toBe(costOfRaise(Rate::scaled(4, 2)))
        ->and($result->plusOneCents)->toBe(costOfRaise(Rate::scaled(6, 2)))
        ->and($result->onePointCents)->toBe(costOfRaise(Rate::scaled(1, 2)))
        // The point costs at least the salary it raises: $5 a day for 365 days.
        ->and($result->onePointCents)->toBeGreaterThan(182_500)
        ->and($result->minusOneCents)->toBeLessThan($result->baseCents)
        ->and($result->plusOneCents)->toBeGreaterThan($result->baseCents);
});

it('prices the point on an unchanged contract whatever the scenario proposes', function (): void {
    $proposal = scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 2)], 'proposed' => [scenarioBenefit()]]);

    expect(Sensitivity::around($proposal)->onePointCents)->toBe(costOfRaise(Rate::scaled(1, 2)));
});

it('never goes below a raise of zero', function (): void {
    $result = Sensitivity::around(scenarioInput(['raises' => ['unionized' => Rate::scaled(5, 3)]]));

    // 0.5 % minus a point is a pay cut, which a revision does not propose: it stops at no raise and costs nothing.
    expect($result->minusOneCents)->toBe(0)->and($result->plusOneCents)->toBe(costOfRaise(Rate::scaled(15, 3)));
});

it('moves both groups by a point, the trusted following the unionized when they have no raise of their own', function (): void {
    $cells = [scenarioCell(), scenarioCell(group: StaffGroup::Trusted)];
    $copy = Sensitivity::around(scenarioInput(['cells' => $cells, 'raises' => ['unionized' => Rate::scaled(5, 2)]]));
    $both = Sensitivity::around(scenarioInput(['cells' => $cells, 'raises' => ['unionized' => Rate::scaled(5, 2), 'trusted' => Rate::scaled(5, 2)]]));

    expect($copy->plusOneCents)->toBe($both->plusOneCents)->and($copy->minusOneCents)->toBe($both->minusOneCents);
});

it('keeps a group with its own raise apart from the other', function (): void {
    $cells = [scenarioCell(), scenarioCell(group: StaffGroup::Trusted)];
    $result = Sensitivity::around(scenarioInput(['cells' => $cells, 'raises' => ['unionized' => Rate::scaled(5, 2), 'trusted' => Rate::scaled(3, 2)]]));
    $expected = ScenarioCost::firstYear(scenarioInput(['cells' => $cells, 'raises' => ['unionized' => Rate::scaled(6, 2), 'trusted' => Rate::scaled(4, 2)]]))->firstYearCents();

    expect($result->plusOneCents)->toBe($expected);
});

it('has a base of zero and a point that costs money for a scenario without changes', function (): void {
    $result = Sensitivity::around(scenarioInput());

    expect($result->baseCents)->toBe(0)->and($result->minusOneCents)->toBe(0)
        ->and($result->plusOneCents)->toBe(costOfRaise(Rate::scaled(1, 2)))->and($result->onePointCents)->toBe(costOfRaise(Rate::scaled(1, 2)));
});

it('includes the retroactive months in what a point costs', function (): void {
    $result = Sensitivity::around(scenarioInput(['retroMonths' => 3, 'raises' => ['unionized' => Rate::scaled(5, 2)]]));

    expect($result->onePointCents)->toBe(costOfRaise(Rate::scaled(1, 2), 3))->and($result->baseCents)->toBe(costOfRaise(Rate::scaled(5, 2), 3));
});

it('costs nothing for an empty roster', function (): void {
    $result = Sensitivity::around(scenarioInput(['cells' => [], 'raises' => ['unionized' => Rate::scaled(5, 2)]]));

    expect($result->onePointCents)->toBe(0)->and($result->plusOneCents)->toBe(0);
});
