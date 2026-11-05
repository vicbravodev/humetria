<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\Calculation\IntegrationFactor;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\SalaryBase;

mutates(SalaryBase::class);

const UMA_2026 = 11_731;
const CAP_2026 = 293_275; // 25 × 117.31 = $2,932.75

it('gives SBC 52465 cents for 500 pesos with 15 days, 12 vacation days and 25 percent', function (): void {
    $factor = IntegrationFactor::for(15, 12, Rate::scaled(25, 2));

    expect(SalaryBase::sbc(50_000, $factor, CAP_2026))->toBe(52_465);
});

it('truncates the fraction of a cent instead of rounding it', function (): void {
    // 1/3 of 100 cents is 33.33…; 2/3 of 100 is 66.66… and would round to 67.
    expect(SalaryBase::sbc(100, Rate::fraction(1, 3), CAP_2026))->toBe(33)
        ->and(SalaryBase::sbc(100, Rate::fraction(2, 3), CAP_2026))->toBe(66)
        ->and(SalaryBase::sbc(1, Rate::fraction(199, 100), CAP_2026))->toBe(1);
});

it('caps SBC at 25 UMA and one cent above stays at the cap', function (): void {
    $one = Rate::one();

    expect(SalaryBase::sbc(CAP_2026 - 1, $one, CAP_2026))->toBe(CAP_2026 - 1)
        ->and(SalaryBase::sbc(CAP_2026, $one, CAP_2026))->toBe(CAP_2026)
        ->and(SalaryBase::sbc(CAP_2026 + 1, $one, CAP_2026))->toBe(CAP_2026)
        ->and(SalaryBase::sbc(CAP_2026 * 4, $one, CAP_2026))->toBe(CAP_2026);
});

it('caps the integrated salary, not the daily salary', function (): void {
    $factor = IntegrationFactor::for(15, 12, Rate::scaled(25, 2));

    // 2,800 pesos is under the cap, but × 383/365 it is 2,938.08: over it.
    expect(SalaryBase::sbc(280_000, $factor, CAP_2026))->toBe(CAP_2026)
        // 2,790 pesos × 383/365 = 2,927.58…: still under.
        ->and(SalaryBase::sbc(279_000, $factor, CAP_2026))->toBe(292_758);
});

it('is zero for a salary of zero', function (): void {
    expect(SalaryBase::sbc(0, Rate::fraction(383, 365), CAP_2026))->toBe(0);
});

it('adds the daily extras before truncating, once', function (): void {
    $factor = Rate::fraction(383, 365);

    // 50,000 × 383/365 = 52,465.75…; plus 1.5 cents is 52,467.25…: 52,467, not 52,465 + 1.
    expect(SalaryBase::sbc(50_000, $factor, CAP_2026, Rate::fraction(3, 2)))->toBe(52_467)
        ->and(SalaryBase::sbc(50_000, $factor, CAP_2026, Rate::zero()))->toBe(52_465)
        ->and(SalaryBase::sbc(50_000, $factor, CAP_2026))->toBe(52_465);
});

it('caps the salary with its extras', function (): void {
    expect(SalaryBase::sbc(290_000, Rate::one(), CAP_2026, Rate::scaled(5_000, 0)))->toBe(CAP_2026)
        ->and(SalaryBase::sbc(290_000, Rate::one(), CAP_2026, Rate::scaled(3_275, 0)))->toBe(CAP_2026)
        ->and(SalaryBase::sbc(290_000, Rate::one(), CAP_2026, Rate::scaled(3_274, 0)))->toBe(CAP_2026 - 1);
});
