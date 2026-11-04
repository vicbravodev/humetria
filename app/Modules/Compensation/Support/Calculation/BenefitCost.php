<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support\Calculation;

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Data\CostSubject;
use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Data\LawTerms;
use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\SbcIntegration;

/**
 * What one benefit costs one person in a year, and how much of it integrates to the SBC. A year is the 365 calendar days of
 * the integration factor; a benefit "per period" is paid once per day (365), per month (12) or per year (1).
 */
final class BenefitCost
{
    private const int DAYS_IN_YEAR = 365;

    /** Whether the benefit is for this person today: their group, their category and level, and enough months of service. */
    public static function applies(BenefitSpec $benefit, CostSubject $subject): bool
    {
        return ($benefit->group === null || $benefit->group === $subject->group)
            && ($benefit->jobGradeIds === [] || in_array($subject->jobGradeId, $benefit->jobGradeIds, true))
            && $subject->tenureMonths >= $benefit->minTenureMonths;
    }

    /** The benefit's value for a person with those completed years: the row of its tenure table, else its base amount. */
    public static function value(BenefitSpec $benefit, int $tenureYears): Rate
    {
        $value = $benefit->amount;
        $from = -1;

        foreach ($benefit->tenureTable as $row) {
            if ($row['fromYears'] <= $tenureYears && $row['fromYears'] > $from) {
                $value = $row['value'];
                $from = $row['fromYears'];
            }
        }

        return $value;
    }

    /** Cents the person costs in a year, 0 when it is not for them; limited to the benefit's cap. */
    public static function annual(BenefitSpec $benefit, CostSubject $subject, FiscalSnapshot $fiscal): int
    {
        if (! self::applies($benefit, $subject)) {
            return 0;
        }

        $value = self::value($benefit, $subject->tenureYears);
        $salary = Rate::scaled($subject->dailySalaryCents, 0);
        $periods = Rate::scaled(self::periodsPerYear($benefit->frequency), 0);

        $cost = match ($benefit->kind) {
            BenefitKind::SalaryDays => $value->times($salary)->times($periods),
            // A percent of the salary of every period adds up to the same percent of the year, whatever the frequency.
            BenefitKind::SalaryPercent => $value->times($salary)->times(Rate::scaled(self::DAYS_IN_YEAR, 0)),
            BenefitKind::FixedAmount => $value->times($periods),
            BenefitKind::UmaMultiple => $value->times(Rate::scaled($fiscal->uma(), 0))->times($periods),
        };

        $cents = $cost->of(1);

        return $benefit->capCents === null ? $cents : min($cents, $benefit->capCents);
    }

    /**
     * Cents per calendar day that the benefit adds to the SBC, as an exact fraction. The vouchers integrate only what is
     * over 40 % of the daily UMA (truncated to the cent); the attendance bonus, what is over 10 % of the SBC without it (when that
     * SBC is given); another benefit "up to a cap" integrates at most its cap. One "up to a cap" with no cap and no rule of the
     * law integrates entirely: the conservative reading.
     */
    public static function dailyIntegrating(BenefitSpec $benefit, CostSubject $subject, FiscalSnapshot $fiscal, ?int $sbcWithoutIt = null): Rate
    {
        $daily = Rate::fraction(self::annual($benefit, $subject, $fiscal), self::DAYS_IN_YEAR);

        return match ($benefit->sbcIntegration) {
            SbcIntegration::Never => Rate::zero(),
            SbcIntegration::Always => $daily,
            SbcIntegration::UpToCap => self::upToCap($benefit, $daily, $fiscal, $sbcWithoutIt),
        };
    }

    /**
     * Whether what integrates of the benefit depends on the SBC without it: an attendance bonus the contract leaves to the law's
     * limit of 10 % of the SBC (LSS art. 27, premios de asistencia y puntualidad). The caller works out that SBC first.
     */
    public static function needsSbcWithoutIt(BenefitSpec $benefit): bool
    {
        return $benefit->key === BaseBenefit::AttendanceBonus->value
            && $benefit->sbcIntegration === SbcIntegration::UpToCap
            && $benefit->sbcCapCents === null;
    }

    /**
     * The aguinaldo, the vacation and the premium the person really has: the contract's when it has the benefit and it is
     * for them, else the law's. The vacation follows the contract only when it brings a tenure table.
     *
     * @param  list<BenefitSpec>  $benefits
     */
    public static function lawTerms(array $benefits, CostSubject $subject, FiscalSnapshot $fiscal): LawTerms
    {
        $aguinaldo = self::forPerson($benefits, BaseBenefit::ChristmasBonus, $subject);
        $vacation = self::forPerson($benefits, BaseBenefit::Vacation, $subject);
        $premium = self::forPerson($benefits, BaseBenefit::VacationPremium, $subject);

        $vacationDays = $vacation !== null && $vacation->tenureTable !== []
            ? self::value($vacation, $subject->tenureYears)
            : Rate::scaled($fiscal->legalVacationDays($subject->tenureYears), 0);

        return new LawTerms(
            aguinaldoDays: $aguinaldo === null ? $fiscal->rate('christmas_bonus_days') : self::value($aguinaldo, $subject->tenureYears),
            vacationDays: $vacationDays,
            vacationPremium: $premium === null ? $fiscal->rate('vacation_premium') : self::value($premium, $subject->tenureYears),
            aguinaldoTaxable: $aguinaldo->inPayrollTax ?? true,
            premiumTaxable: $premium->inPayrollTax ?? true,
        );
    }

    private static function periodsPerYear(Frequency $frequency): int
    {
        return match ($frequency) {
            Frequency::Daily => self::DAYS_IN_YEAR,
            Frequency::Monthly => 12,
            Frequency::Yearly => 1,
        };
    }

    private static function upToCap(BenefitSpec $benefit, Rate $daily, FiscalSnapshot $fiscal, ?int $sbcWithoutIt): Rate
    {
        if ($benefit->sbcCapCents !== null) {
            $cap = Rate::scaled($benefit->sbcCapCents, 0);

            return $daily->compareTo($cap) <= 0 ? $daily : $cap;
        }

        if (self::needsSbcWithoutIt($benefit) && $sbcWithoutIt !== null) {
            // Only what is over 10 % of the SBC without the bonus integrates; the SBC is that of the person, so the 10 % follows it.
            $excess = $daily->minus(Rate::scaled($sbcWithoutIt, 0)->times(Rate::scaled(10, 2)));

            return $excess->compareTo(Rate::zero()) > 0 ? $excess : Rate::zero();
        }

        if ($benefit->key !== BaseBenefit::FoodVouchers->value) {
            return $daily;
        }

        $exempt = $fiscal->rate('voucher_exempt_uma')->times(Rate::scaled($fiscal->uma(), 0))->truncated(1);
        $excess = $daily->minus(Rate::scaled($exempt, 0));

        return $excess->compareTo(Rate::zero()) > 0 ? $excess : Rate::zero();
    }

    /** @param  list<BenefitSpec>  $benefits */
    private static function forPerson(array $benefits, BaseBenefit $key, CostSubject $subject): ?BenefitSpec
    {
        foreach ($benefits as $benefit) {
            if ($benefit->key === $key->value && self::applies($benefit, $subject)) {
                return $benefit;
            }
        }

        return null;
    }
}
