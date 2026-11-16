<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Models\AgreementAcceptance;

/** Whether a person accepted their company's current agreement (criterio 5.1): an older version does not count. */
final readonly class AgreementStatus
{
    public function __construct(private CurrentAgreement $agreement) {}

    public function accepted(string $employeeId): bool
    {
        return AgreementAcceptance::query()
            ->where('employee_id', $employeeId)
            ->where('agreement_id', $this->agreement->get()->id)
            ->exists();
    }
}
