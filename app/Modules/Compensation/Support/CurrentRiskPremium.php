<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Compensation\Support\Calculation\Rate;
use Carbon\CarbonImmutable;

/** The work risk premium the company has in force on a date: the latest one that started on or before it, null when it has none. */
final class CurrentRiskPremium
{
    /** The prima media of class I (LSS art. 73, 0.54355 %), for an estimate of a company that has not captured its own. */
    public const int CLASS_ONE_SCALED = 54_355; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    public function at(CarbonImmutable $on): ?Rate
    {
        $scaled = $this->scaledAt($on);

        return $scaled === null ? null : Rate::scaled($scaled, WorkRiskPremium::SCALE);
    }

    /** The same as it is stored (rate × 10^7: 7.58875 % is 758875), for PremiumRate to write as a percent. */
    public function scaledAt(CarbonImmutable $on): ?int
    {
        return WorkRiskPremium::query()->where('effective_from', '<=', $on->toDateString())->orderByDesc('effective_from')->first()?->rate_scaled;
    }

    public static function classOne(): Rate
    {
        return Rate::scaled(self::CLASS_ONE_SCALED, WorkRiskPremium::SCALE);
    }
}
