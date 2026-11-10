<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Enums\WorkWeek;
use Carbon\CarbonImmutable;

/**
 * The terms a person's vacations follow: those of a policy of the company or, with no policy, the law itself. The premium
 * is a percentage, never an amount (criterio C-08).
 */
final readonly class PolicyTerms
{
    /**
     * @param  string|null  $policyId  null for the «Ley» policy, which is not a row
     * @param  list<array{from: int, to: int, days: int}>  $tiers  total days by range of years of service
     * @param  int  $fixedDays  days that come every year on top (birthday, personal days)
     */
    public function __construct(
        public ?string $policyId,
        public string $name,
        public int $premiumPercent,
        public WorkWeek $workWeek,
        public bool $allowsAdvance,
        public ?int $extraExpiresMonths,
        public ?int $carryoverCap,
        public array $tiers,
        public int $fixedDays,
    ) {}

    /**
     * The «Ley» policy: the days of art. 76, a premium of 25 percent and the working week the company confirmed.
     */
    public static function statutory(WorkWeek $week): self
    {
        return new self(null, 'Ley', 25, $week, false, null, null, [], 0);
    }

    /**
     * The days of a year of service, by tranche (criterio C-14): the statutory ones by the table of the anniversary, and
     * as extra what the tier of that year gives over them plus the fixed days. A year no tier covers gets the law.
     *
     * @return array{statutory: int, extra: int}
     */
    public function daysFor(int $years, CarbonImmutable $anniversary): array
    {
        $statutory = StatutoryDays::for($years, $anniversary);
        $extra = $this->fixedDays;

        foreach ($this->tiers as $tier) {
            if ($tier['from'] <= $years && $years <= $tier['to']) {
                $extra += max(0, $tier['days'] - $statutory);
            }
        }

        return ['statutory' => $statutory, 'extra' => $extra];
    }
}
