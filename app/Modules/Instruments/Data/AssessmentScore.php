<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Data;

final readonly class AssessmentScore
{
    public function __construct(
        public int $total,
        public RiskLevel $level,
    ) {}
}
