<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Contracts;

use InvalidArgumentException;

/**
 * The representative sample of NOM-035 Guía III (III.1), for work centers of more than 50 workers.
 */
interface Nom035Sample
{
    /**
     * n = 0.9604·N / (0.0025·(N − 1) + 0.9604), rounded up (criterio C-09).
     *
     * @throws InvalidArgumentException for 50 workers or fewer, where Guía III does not apply.
     */
    public function size(int $workers): int;

    /**
     * Splits a sample among genders in proportion to how many people each has (largest remainder; a tie goes to the
     * larger gender, then to key order). The quotas add up to `$sample` and none exceeds its gender.
     *
     * @param  array<string, int>  $strata  people by gender
     * @return array<string, int> people to draw by gender, in the order given
     *
     * @throws InvalidArgumentException when the sample is larger than the people there are.
     */
    public function allocate(int $sample, array $strata): array;
}
