<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\Calculation\Money;
use App\Modules\Compensation\Support\Calculation\Rate;
use Brick\Math\BigRational;

mutates(Money::class, Rate::class);

it('truncates 524.6575 to 524.65 and rounds half up elsewhere', function (): void {
    $cents = BigRational::of('52465.75');

    expect(Money::truncate($cents))->toBe(52465)
        ->and(Money::round($cents))->toBe(52466)
        ->and(Money::round(BigRational::of('52465.5')))->toBe(52466)
        ->and(Money::round(BigRational::of('52465.49')))->toBe(52465)
        ->and(Money::truncate(BigRational::of(52465)))->toBe(52465)
        ->and(Money::round(BigRational::ofFraction(1, 3)))->toBe(0)
        ->and(Money::round(BigRational::ofFraction(1, 2)))->toBe(1)
        ->and(Money::truncate(BigRational::ofFraction(199, 100)))->toBe(1);
});

it('rounds a negative amount away from zero on a half and truncates it toward zero', function (): void {
    expect(Money::round(BigRational::of('-2.5')))->toBe(-3)
        ->and(Money::truncate(BigRational::of('-2.9')))->toBe(-2);
});

it('turns pesos into whole cents without floats', function (string|int $pesos, int $cents): void {
    expect(Money::cents($pesos))->toBe($cents);
})->with([
    ['524.65', 52465],
    ['117.31', 11731],
    ['0.01', 1],
    ['500', 50000],
    [500, 50000],
    [0, 0],
    ['-1.5', -150],
]);

it('refuses pesos with fractions of a cent', function (): void {
    Money::cents('524.6575');
})->throws(InvalidArgumentException::class);

it('formats cents as pesos with thousands separators', function (int $cents, string $expected): void {
    expect(Money::format($cents))->toBe($expected);
})->with([
    [52465, '$524.65'],
    [293275, '$2,932.75'],
    [5, '$0.05'],
    [0, '$0.00'],
    [100, '$1.00'],
    [123456789, '$1,234,567.89'],
    [-52465, '-$524.65'],
]);

it('never uses floats in the calculation code', function (): void {
    $forbidden = ['float', 'floatval', 'doubleval', 'round', 'floor', 'ceil', 'fdiv', 'fmod', 'sqrt', 'pow', 'intval'];
    $found = [];

    foreach (glob(dirname(__DIR__, 3).'/app/Modules/Compensation/Support/Calculation/*.php') ?: [] as $file) {
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($file)),
            fn (array|string $token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        foreach ($tokens as $index => $token) {
            $previous = $tokens[$index - 1] ?? null;
            // A method named round() is not a call to the global function.
            $isMember = is_array($previous) && in_array($previous[0], [T_FUNCTION, T_DOUBLE_COLON, T_OBJECT_OPERATOR], true);
            $isForbidden = is_array($token) && match ($token[0]) {
                T_DNUMBER, T_DOUBLE_CAST, T_DIV_EQUAL => true,
                T_STRING => ! $isMember && in_array(strtolower($token[1]), $forbidden, true),
                default => false,
            };

            if ($token === '/' || $isForbidden) {
                $found[] = basename($file).': '.(is_array($token) ? $token[1] : $token);
            }
        }
    }

    expect($found)->toBe([]);
});

it('writes cents as whole pesos, a half going up, for the headline numbers', function (int $cents, string $expected): void {
    expect(Money::pesos($cents))->toBe($expected);
})->with([
    [123_949_999, '$1,239,500'], [123_950_049, '$1,239,500'], [123_950_050, '$1,239,501'], [0, '$0'], [49, '$0'], [50, '$1'], [-123_450, '-$1,235'],
]);

it('writes about how much in hundreds of pesos, for a figure to negotiate', function (int $cents, string $expected): void {
    expect(Money::about($cents))->toBe($expected);
})->with([[12_690_512, '$126,900'], [12_695_000, '$127,000'], [4_999, '$0'], [5_000, '$100'], [0, '$0']]);
