<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\Calculation\Rate;
use Brick\Math\BigRational;

mutates(Rate::class);

it('is an exact fraction, from a scaled integer or from a numerator and a denominator', function (): void {
    expect(Rate::scaled(2040, 4)->value()->isEqualTo(BigRational::ofFraction(51, 250)))->toBeTrue()
        ->and(Rate::scaled(7513, 5)->value()->isEqualTo(BigRational::ofFraction(7513, 100000)))->toBeTrue()
        ->and(Rate::scaled(15, 0)->value()->isEqualTo(BigRational::of(15)))->toBeTrue()
        ->and(Rate::fraction(383, 365)->value()->isEqualTo(BigRational::ofFraction(383, 365)))->toBeTrue();
});

it('rounds half up to a cent with of() and truncates with truncated()', function (): void {
    $factor = Rate::fraction(383, 365);

    expect($factor->of(50000))->toBe(52466)
        ->and($factor->truncated(50000))->toBe(52465)
        ->and(Rate::scaled(5, 2)->of(10))->toBe(1)
        ->and(Rate::scaled(5, 2)->of(9))->toBe(0)
        ->and(Rate::scaled(5, 2)->of(0))->toBe(0);
});

it('compares by value, not by representation', function (): void {
    expect(Rate::fraction(1, 2)->compareTo(Rate::scaled(5, 1)))->toBe(0)
        ->and(Rate::fraction(1, 3)->compareTo(Rate::fraction(1, 2)))->toBe(-1)
        ->and(Rate::fraction(2, 3)->compareTo(Rate::fraction(1, 2)))->toBe(1);
});

it('adds and multiplies exactly', function (): void {
    expect(Rate::one()->plus(Rate::fraction(15, 365))->plus(Rate::fraction(3, 365))->compareTo(Rate::fraction(383, 365)))->toBe(0)
        ->and(Rate::fraction(1, 3)->times(Rate::fraction(3, 1))->compareTo(Rate::one()))->toBe(0);
});

it('gives a whole number only when the value is an integer', function (): void {
    expect(Rate::scaled(11731, 0)->integer())->toBe(11731);

    Rate::scaled(1, 1)->integer();
})->throws(InvalidArgumentException::class);
