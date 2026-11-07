<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** A week of the registro: Monday 00:00 to Sunday 23:59:59 in the display time zone, as the design fixes it (Semana y día). */
final readonly class WeekPeriod
{
    private function __construct(private CarbonImmutable $start) {}

    public static function containing(CarbonImmutable $instant): self
    {
        return new self(self::local($instant)->startOfWeek(CarbonInterface::MONDAY));
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    public function end(): CarbonImmutable
    {
        return $this->start->endOfWeek(CarbonInterface::SUNDAY);
    }

    /** The local day an instant falls on, at its midnight. */
    public function localDate(CarbonImmutable $instant): CarbonImmutable
    {
        return self::local($instant)->startOfDay();
    }

    private static function local(CarbonImmutable $instant): CarbonImmutable
    {
        return $instant->setTimezone(config()->string('app.display_timezone'));
    }
}
