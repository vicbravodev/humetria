<?php

declare(strict_types=1);

namespace App\Modules\Seating\Events;

use App\Modules\Instruments\Data\RiskLevel;

/**
 * An individual assessment was signed. It carries ids and the level only: never the scores nor the person's answers.
 */
final readonly class RiskAssessmentSigned
{
    public function __construct(public string $employeeId, public string $workCenterId, public RiskLevel $level) {}
}
