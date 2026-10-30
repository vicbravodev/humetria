<?php

declare(strict_types=1);

use App\Modules\Instruments\Contracts\Nom035Sample;
use App\Modules\Instruments\Support\Nom035SampleRule;

mutates(Nom035SampleRule::class);

/**
 * III.1: n = 0.9604·N / (0.0025·(N − 1) + 0.9604), rounded up because the norm asks for "al menos" that many
 * (criterio C-09). The exact value is in each case's name.
 */
it('sizes the Guía III sample rounding up', function (int $workers, int $sample): void {
    expect(resolve(Nom035Sample::class)->size($workers))->toBe($sample);
})->with([
    '51 (45.13)' => [51, 46],
    '52 (45.91)' => [52, 46],
    '100 (79.51), the example of the norm' => [100, 80],
    '500 (217.49)' => [500, 218],
    '1000 (277.74)' => [1000, 278],
    '10000 (369.98)' => [10000, 370],
]);

it('never asks for more people than work there, nor fewer than the formula', function (): void {
    foreach (range(51, 2000) as $workers) {
        $sample = resolve(Nom035Sample::class)->size($workers);
        $exact = 0.9604 * $workers / (0.0025 * ($workers - 1) + 0.9604);

        expect($sample)->toBeLessThanOrEqual($workers)
            ->and($sample)->toBeGreaterThanOrEqual($exact)
            ->and($sample - $exact)->toBeLessThan(1);
    }
});

it('samples only work centers of more than 50 workers', function (int $workers): void {
    resolve(Nom035Sample::class)->size($workers);
})->with([50, 16, 0])->throws(InvalidArgumentException::class);

/**
 * III.1: "La muestra se distribuirá entre hombres y mujeres conforme al porcentaje de cada género". Largest remainder:
 * each gender gets its whole part, and the seats left go to the largest fractions; a tie goes to the larger gender.
 */
it('splits the sample by gender in proportion', function (int $sample, array $strata, array $quotas): void {
    expect(resolve(Nom035Sample::class)->allocate($sample, $strata))->toBe($quotas);
})->with([
    '80 of 56/42/2: 44.8/33.6/1.6' => [80, ['female' => 56, 'male' => 42, 'other' => 2], ['female' => 45, 'male' => 34, 'other' => 1]],
    'a single gender takes it all' => [46, ['male' => 51], ['male' => 46]],
    'a tie goes to the larger gender: 1.5/2.5' => [4, ['female' => 3, 'male' => 5], ['female' => 1, 'male' => 3]],
    'an equal tie goes in key order' => [1, ['female' => 1, 'male' => 1], ['female' => 1, 'male' => 0]],
    'a gender of one never gets two' => [46, ['female' => 50, 'other' => 1], ['female' => 45, 'other' => 1]],
    'nobody to draw' => [0, ['female' => 3], ['female' => 0]],
]);

it('allocates exactly the sample, never more than a gender has', function (): void {
    mt_srand(35);

    foreach (range(1, 200) as $case) {
        $strata = ['female' => mt_rand(0, 400), 'male' => mt_rand(0, 400), 'other' => mt_rand(0, 10)];
        $workers = array_sum($strata);

        if ($workers <= 50) {
            continue;
        }

        $sample = resolve(Nom035Sample::class)->size($workers);
        $quotas = resolve(Nom035Sample::class)->allocate($sample, $strata);

        expect(array_sum($quotas))->toBe($sample);

        foreach ($quotas as $gender => $quota) {
            expect($quota)->toBeLessThanOrEqual($strata[$gender])->toBeGreaterThanOrEqual(0);
        }
    }
});

it('refuses a sample larger than the people there are', function (): void {
    resolve(Nom035Sample::class)->allocate(5, ['female' => 2, 'male' => 2]);
})->throws(InvalidArgumentException::class);
