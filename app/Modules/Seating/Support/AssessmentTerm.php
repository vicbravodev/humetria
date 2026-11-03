<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use Carbon\CarbonImmutable;

/**
 * How long a signed assessment is current: `seating.reassessment_months` after it was signed (the Disposiciones set no term,
 * criterio C-07). Past that, the person is due to be assessed again.
 */
final class AssessmentTerm
{
    public static function overdue(CarbonImmutable $signedAt): bool
    {
        return $signedAt->addMonths(config()->integer('seating.reassessment_months'))->isPast();
    }
}
