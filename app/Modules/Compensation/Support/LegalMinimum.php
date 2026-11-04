<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\BenefitKind;

/**
 * Whether the terms of a base benefit fall below what the law sets (aguinaldo, vacation premium, vacation days): the contract
 * may give more, never less. The other benefits have no minimum to compare with.
 */
final class LegalMinimum
{
    /** Where the vacation table of the law stops growing (LFT art. 76: from 31 years it grows every five). */
    private const int VACATION_YEARS = 35;

    /**
     * What is below the law, in words for the person; null when the terms meet it or the benefit has no minimum.
     *
     * @param  ?list<array{from_years: int, value: int}>  $table
     */
    public static function problem(?BaseBenefit $base, int $value, ?array $table, FiscalSnapshot $legal): ?string
    {
        $prefix = 'Está por debajo del mínimo de la ley: ';

        return match ($base) {
            BaseBenefit::ChristmasBonus => self::belowFlat($value, $legal->rate('christmas_bonus_days')->integer() * 100, BenefitKind::SalaryDays, $prefix),
            BaseBenefit::VacationPremium => self::belowFlat($value, $legal->rate('vacation_premium')->of(10_000), BenefitKind::SalaryPercent, $prefix),
            BaseBenefit::Vacation => self::belowVacation($value, $table, $legal, $prefix),
            default => null,
        };
    }

    private static function belowFlat(int $value, int $minimum, BenefitKind $kind, string $prefix): ?string
    {
        return $value < $minimum ? $prefix.BenefitValue::format($kind, $minimum).'.' : null;
    }

    /**
     * @param  ?list<array{from_years: int, value: int}>  $table
     */
    private static function belowVacation(int $value, ?array $table, FiscalSnapshot $legal, string $prefix): ?string
    {
        for ($years = 1; $years <= self::VACATION_YEARS; $years++) {
            $given = TenureTable::valueAt($table, $value, $years);
            $minimum = $legal->legalVacationDays($years) * 100;

            if ($given < $minimum) {
                return $prefix."en el año {$years} de servicio da ".BenefitValue::format(BenefitKind::SalaryDays, $given).' y la ley pide '.BenefitValue::decimal($minimum).'.';
            }
        }

        return null;
    }
}
