<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

use App\Modules\Compensation\Enums\AssumptionKey;

/**
 * What the projection assumes about the years after the first, as yearly rates in basis points (500 is 5 %).
 *
 * `inflationBp` grows the UMA and the minimum wage of a year the platform has no value for; `turnoverBp` is the share of the
 * people who leave each year and are replaced by new hires; `headcountGrowthBp` the yearly growth of the staff;
 * `futureRevisionsBp` the raise every salary gets in each following year, with or without the proposal; and
 * `followingYearsRateBp` a raise more each year given only on the proposal's side.
 */
final readonly class Assumptions
{
    public function __construct(
        public int $inflationBp,
        public int $turnoverBp,
        public int $headcountGrowthBp,
        public int $futureRevisionsBp,
        public int $followingYearsRateBp,
    ) {}

    /** No assumption: the following years only differ by the tenure that grows by itself. */
    public static function none(): self
    {
        return new self(0, 0, 0, 0, 0);
    }

    /** The values the platform proposes (AssumptionKey). */
    public static function defaults(): self
    {
        return new self(
            AssumptionKey::Inflation->defaultBasisPoints(),
            AssumptionKey::Turnover->defaultBasisPoints(),
            AssumptionKey::HeadcountGrowth->defaultBasisPoints(),
            AssumptionKey::FutureRevisions->defaultBasisPoints(),
            AssumptionKey::FollowingYearsRate->defaultBasisPoints(),
        );
    }
}
