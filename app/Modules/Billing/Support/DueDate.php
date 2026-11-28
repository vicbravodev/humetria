<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use Carbon\CarbonImmutable;

/** The day an issued statement is due: the day it was issued, in the panel's time zone, plus `billing.overdue_after_days`. */
final class DueDate
{
    public static function of(CarbonImmutable $issuedAt): CarbonImmutable
    {
        return $issuedAt->setTimezone(config()->string('app.display_timezone'))->startOfDay()->addDays(config()->integer('billing.overdue_after_days'));
    }
}
