<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support;

use App\Modules\Instruments\Contracts\Nom035Sample;
use InvalidArgumentException;

/**
 * III.1, in integers so no rounding error can move a result: n = ⌈9604·N / (25·(N − 1) + 9604)⌉, the norm's formula
 * with both terms multiplied by 10 000.
 */
final class Nom035SampleRule implements Nom035Sample
{
    public function size(int $workers): int
    {
        if ($workers <= 50) {
            throw new InvalidArgumentException('Guía III, and its sample, apply to more than 50 workers.');
        }

        $divisor = 25 * ($workers - 1) + 9604;

        return intdiv(9604 * $workers + $divisor - 1, $divisor);
    }

    public function allocate(int $sample, array $strata): array
    {
        $people = array_sum($strata);

        if ($sample > $people) {
            throw new InvalidArgumentException('The sample cannot be larger than the people there are.');
        }

        if ($people === 0) {
            return $strata;
        }

        $quotas = [];
        $remainders = [];

        foreach ($strata as $gender => $count) {
            $quotas[$gender] = intdiv($sample * $count, $people);
            $remainders[] = ['gender' => $gender, 'remainder' => ($sample * $count) % $people, 'count' => $count];
        }

        // Largest remainder first; a tie goes to the larger gender, then keeps key order (usort is stable).
        usort($remainders, fn (array $a, array $b): int => [$b['remainder'], $b['count']] <=> [$a['remainder'], $a['count']]);

        foreach (array_slice($remainders, 0, $sample - array_sum($quotas)) as $seat) {
            $quotas[$seat['gender']]++;
        }

        return $quotas;
    }
}
