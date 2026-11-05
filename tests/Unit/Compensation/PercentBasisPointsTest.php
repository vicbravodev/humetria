<?php

declare(strict_types=1);

use App\Modules\Compensation\Support\PercentBasisPoints;

mutates(PercentBasisPoints::class);

it('reads a percent with up to two decimals and either mark into basis points', function (): void {
    foreach (['5' => 500, '5.75' => 575, '5,75' => 575, '0' => 0, '0.01' => 1, '100' => 10000, ' 3.5 ' => 350, '-2' => -200, '-0.5' => -50, '12.50' => 1250] as $text => $basisPoints) {
        expect(PercentBasisPoints::parse((string) $text))->toBe($basisPoints);
    }
});

it('refuses what is not a percent like that', function (): void {
    foreach (['', 'abc', '5.755', '5.', '.5', '--1', '1e3', '5%', '1 000', '1234'] as $text) {
        expect(PercentBasisPoints::parse($text))->toBeNull();
    }
});

it('writes basis points as a person types them, with only the decimals it needs', function (): void {
    foreach ([500 => '5', 575 => '5.75', 350 => '3.5', 0 => '0', 1 => '0.01', -200 => '-2', -50 => '-0.5', 10000 => '100'] as $basisPoints => $text) {
        expect(PercentBasisPoints::format($basisPoints))->toBe($text);
    }
});
