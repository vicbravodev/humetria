<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use Carbon\CarbonImmutable;

/**
 * The dates of a vacation period, all counted from its anniversary: until when it should be enjoyed (six months, LFT
 * art. 81; criterio C-09) and from when what is left of it can prescribe (eighteen months; criterio C-11). Days are plain
 * dates: the hour of what it is given never counts.
 */
final readonly class PeriodWindow
{
    private function __construct(public CarbonImmutable $enjoyBy, public CarbonImmutable $prescribesOn) {}

    /**
     * A month too short for the day of the anniversary uses its last day (criterio C-20).
     */
    public static function of(CarbonImmutable $anniversary): self
    {
        $day = $anniversary->startOfDay();

        return new self($day->addMonthsNoOverflow(6), $day->addMonthsNoOverflow(18));
    }

    /**
     * The anniversaries of someone who counts their seniority from `$start` that fall from `$from` to `$until`, both
     * included, by year of service. A 29 February falls on the 28th in the years without one (criterio C-20).
     *
     * @return array<int, CarbonImmutable>
     */
    public static function anniversaries(CarbonImmutable $start, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $start = $start->startOfDay();
        $first = $from->toDateString();
        $last = $until->toDateString();
        $anniversaries = [];

        for ($years = 1; ($day = $start->addYearsNoOverflow($years))->toDateString() <= $last; $years++) {
            if ($day->toDateString() >= $first) {
                $anniversaries[$years] = $day;
            }
        }

        return $anniversaries;
    }

    /**
     * Today as people in Mexico live it (app.display_timezone), as a plain date.
     */
    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString());
    }

    public function pastEnjoyWindow(CarbonImmutable $today): bool
    {
        return $today->toDateString() > $this->enjoyBy->toDateString();
    }

    public function prescribable(CarbonImmutable $today): bool
    {
        return $today->toDateString() > $this->prescribesOn->toDateString();
    }
}
