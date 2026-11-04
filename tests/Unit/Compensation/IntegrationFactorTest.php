<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\Calculation\IntegrationFactor;
use App\Modules\Compensation\Support\Calculation\Rate;

mutates(IntegrationFactor::class);

it('gives factor 1.0493 with the legal minimums', function (): void {
    $factor = IntegrationFactor::for(aguinaldoDays: 15, vacationDays: 12, vacationPremium: Rate::scaled(25, 2));

    expect($factor->compareTo(Rate::fraction(383, 365)))->toBe(0)
        // 383/365 = 1.04931506…, so it is 1.0493 to four decimals.
        ->and($factor->of(10_000))->toBe(10_493);
});

it('uses the real values of the person, not the legal minimums', function (int $aguinaldo, int $vacations, string $premium, int $numerator): void {
    $factor = IntegrationFactor::for($aguinaldo, $vacations, Rate::scaled((int) $premium, 2));

    expect($factor->compareTo(Rate::fraction($numerator, 365)))->toBe(0);
})->with([
    'richer aguinaldo and premium' => [30, 22, '50', 406],      // 365 + 30 + 22 × 0.5
    'no premium' => [15, 12, '0', 380],                          // 365 + 15
    'no aguinaldo' => [0, 12, '25', 368],                        // 365 + 12 × 0.25
    'one day of vacation, full premium' => [15, 1, '100', 381],  // 365 + 15 + 1
]);

it('is exactly one without aguinaldo or vacations', function (): void {
    expect(IntegrationFactor::for(0, 0, Rate::scaled(25, 2))->compareTo(Rate::one()))->toBe(0)
        ->and(IntegrationFactor::for(0, 12, Rate::scaled(0, 2))->compareTo(Rate::one()))->toBe(0);
});

it('keeps the fraction exact, with no rounding before the salary is multiplied', function (): void {
    $factor = IntegrationFactor::for(15, 12, Rate::scaled(25, 2));

    // 500 pesos × 383/365 = 524.6575…: the cent is only settled by the salary base.
    expect($factor->truncated(50_000))->toBe(52_465)
        ->and($factor->of(50_000))->toBe(52_466);
});

it('takes days that are not whole, as a contract may grant 22.5 days of aguinaldo', function (): void {
    // 365 + 22.5 + 12 × 0.25 = 390.5 over 365.
    $factor = IntegrationFactor::ofDays(Rate::scaled(225, 1), Rate::scaled(12, 0), Rate::scaled(25, 2));

    expect($factor->compareTo(Rate::fraction(781, 730)))->toBe(0)
        ->and(IntegrationFactor::ofDays(Rate::scaled(15, 0), Rate::scaled(12, 0), Rate::scaled(25, 2))->compareTo(IntegrationFactor::for(15, 12, Rate::scaled(25, 2))))->toBe(0);
});
