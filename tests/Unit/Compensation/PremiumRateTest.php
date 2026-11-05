<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\PremiumRate;

mutates(PremiumRate::class);

it('reads a percent with up to five decimals as the rate times ten million', function (string $text, ?int $scaled): void {
    expect(PremiumRate::parse($text))->toBe($scaled);
})->with([
    'a whole percent' => ['5', 500_000],
    'the law minimum' => ['0.5', 50_000],
    'five decimals' => ['7.58875', 758_875],
    'a comma' => ['1,13065', 113_065],
    'the law maximum' => ['15', 1_500_000],
    'spaces' => [' 2.5 ', 250_000],
    'six decimals' => ['1.123456', null],
    'negative' => ['-1', null],
    'text' => ['cinco', null],
    'nothing' => ['', null],
    'a bare mark' => ['.5', null],
    'too many digits' => ['1000', null],
]);

it('writes the rate as a percent with the decimals it needs', function (int $scaled, string $text): void {
    expect(PremiumRate::format($scaled))->toBe($text);
})->with([
    [500_000, '5'], [758_875, '7.58875'], [50_000, '0.5'], [113_065, '1.13065'], [1_500_000, '15'], [100_000, '1'],
]);
