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

    /** The week of a local calendar date, whatever time zone its Carbon carries. */
    public static function ofDate(CarbonImmutable $date): self
    {
        return self::containing(CarbonImmutable::parse($date->toDateString(), config()->string('app.display_timezone')));
    }

    /** Whether its Sunday ended: only then can the person answer for it. */
    public function isOver(): bool
    {
        return CarbonImmutable::now()->greaterThanOrEqualTo($this->nextStartUtc());
    }

    public function start(): CarbonImmutable
    {
        return $this->start;
    }

    public function end(): CarbonImmutable
    {
        return $this->start->endOfWeek(CarbonInterface::SUNDAY);
    }

    /**
     * The first instant of the week in UTC. Queries bind these, never the local bounds: a Carbon binding loses its offset
     * and the database session is UTC.
     */
    public function startUtc(): CarbonImmutable
    {
        return $this->start->utc();
    }

    /** The first instant of the next week in UTC: an exclusive upper bound, so no microsecond is lost at the edge. */
    public function nextStartUtc(): CarbonImmutable
    {
        return $this->start->addWeek()->utc();
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
