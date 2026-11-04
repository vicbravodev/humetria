<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Support\Calculation\Rate;

/** A benefit and a value of its terms as the calculation reads them: stored hundredths become the rate of their kind. */
final class BenefitSpecs
{
    /**
     * @param  int  $value  hundredths, see BenefitKind
     * @param  ?list<array{from_years: int, value: int}>  $tenureTable
     */
    public static function from(Benefit $benefit, int $value, ?array $tenureTable): BenefitSpec
    {
        return new BenefitSpec(
            key: $benefit->base_key ?? $benefit->id,
            kind: $benefit->kind,
            amount: self::rate($benefit->kind, $value),
            frequency: $benefit->frequency,
            group: $benefit->applies_to_group,
            jobGradeIds: $benefit->job_grade_ids,
            minTenureMonths: $benefit->min_tenure_months,
            tenureTable: array_map(fn (array $row): array => ['fromYears' => $row['from_years'], 'value' => self::rate($benefit->kind, $row['value'])], $tenureTable ?? []),
            sbcIntegration: $benefit->sbc_integration,
            sbcCapCents: $benefit->sbc_cap_cents,
            inPayrollTax: $benefit->in_payroll_tax,
            incomeTaxExempt: $benefit->income_tax_exempt,
            inSeniorityBase: $benefit->in_seniority_base,
            capCents: $benefit->cap_cents,
        );
    }

    /** 15 days are 1500, 25 % is 2500 (0.25), $500.00 is 50000 cents and 1.5 UMA is 150. */
    private static function rate(BenefitKind $kind, int $value): Rate
    {
        return match ($kind) {
            BenefitKind::SalaryDays, BenefitKind::UmaMultiple => Rate::scaled($value, 2),
            BenefitKind::SalaryPercent => Rate::scaled($value, 4),
            BenefitKind::FixedAmount => Rate::scaled($value, 0),
        };
    }
}
