<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Enums\PayGroup;
use Carbon\CarbonImmutable;

/**
 * One line of the cost: a headcount cell (`count` people who all share a salary and a hire month) or one person with an
 * individual salary (`count` 1). `dailySalaryCents` is null when nobody knows what they earn (no tabulator salary).
 */
final readonly class CostUnit
{
    /** @param  int<1, max>  $count */
    public function __construct(
        public string $jobGradeId,
        public ?string $areaId,
        public PayGroup $group,
        public ?string $state,
        public CarbonImmutable $hiredOn,
        public int $count,
        public ?int $dailySalaryCents,
    ) {}
}
