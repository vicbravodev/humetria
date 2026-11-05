<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OutOfBoundsException;

/**
 * Gives the current company the base benefits (spec, decision 6) the first time it needs them, each with the legal value
 * of the year as its first term (aguinaldo 15 days, the vacation table of LFT art. 76, a 25 % vacation premium) and at
 * zero where the law sets none, ready for the company to fill in.
 *
 * What each one does to the SBC and to the payroll tax follows docs/normas/compensation/criterios.md: sections 3 and 6 name
 * what does NOT integrate or is out of the base, and every other concept is in. The PTU base, the seniority premium base and
 * the income tax exemption are not covered there, so no base benefit claims them.
 *
 * It is safe to call at any time and from several requests at once: the database's unique key on (company, base_key) decides
 * who creates each benefit, and only the request that created one writes its terms.
 */
final readonly class BaseCatalog
{
    /** Two decimals: 15 days are 1500 (see BenefitKind). */
    private const int HUNDREDTHS = 100;

    public function __construct(private CurrentTenant $tenant, private FiscalParameters $fiscal) {}

    /**
     * @param  ?CarbonImmutable  $on  the date whose legal values start the terms; the terms are dated at the start of its year. Today by default.
     *
     * @throws OutOfBoundsException when the platform has no legal values for the date, before anything is created
     */
    public function ensure(?CarbonImmutable $on = null): void
    {
        if (Benefit::query()->whereNotNull('base_key')->count() === count(BaseBenefit::cases())) {
            return;
        }

        $on ??= CarbonImmutable::now();
        $legal = $this->fiscal->at($on);
        $terms = [];

        foreach (BaseBenefit::cases() as $base) {
            $terms[$base->value] = $this->firstTerm($base, $legal);
        }

        $tenantId = $this->tenant->id();
        $from = $on->startOfYear()->toDateString();
        $now = now();

        DB::transaction(function () use ($terms, $tenantId, $from, $now): void {
            foreach (BaseBenefit::cases() as $base) {
                $id = (string) Str::uuid();
                $created = Benefit::query()->insertOrIgnore([
                    'id' => $id,
                    'tenant_id' => $tenantId,
                    'name' => $base->label(),
                    'base_key' => $base->value,
                    'job_grade_ids' => '[]',
                    'created_at' => $now,
                    'updated_at' => $now,
                    ...$this->attributes($base),
                ]);

                if ($created === 1) {
                    ContractTerm::query()->insert([
                        'id' => (string) Str::uuid(),
                        'tenant_id' => $tenantId,
                        'benefit_id' => $id,
                        'effective_from' => $from,
                        'value' => $terms[$base->value]['value'],
                        'tenure_table' => $terms[$base->value]['table'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        });
    }

    /**
     * What defines the benefit, the rest being the same for all: for everyone, no seniority needed, no cap of its own, not in
     * the PTU or seniority base and not pointed out as exempt.
     *
     * @return array<string, mixed>
     */
    private function attributes(BaseBenefit $base): array
    {
        [$kind, $frequency, $sbc, $payrollTax] = match ($base) {
            BaseBenefit::ChristmasBonus => [BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Always, true],
            // The days of vacation are already in the salary; the integration factor takes their premium.
            BaseBenefit::Vacation => [BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Never, false],
            BaseBenefit::VacationPremium => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
            // LSS art. 27: a fund with equal deposits of the worker and the company does not integrate (assumed to meet the
            // requirements: the platform does not check them), and the ISN leaves it out the same way.
            BaseBenefit::SavingsFund => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Never, false],
            // Up to 40 % of the daily UMA does not integrate; the law sets that cap, so none is kept here.
            BaseBenefit::FoodVouchers => [BenefitKind::FixedAmount, Frequency::Monthly, SbcIntegration::UpToCap, true],
            BaseBenefit::SeniorityPremium => [BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Always, true],
            BaseBenefit::SundayPremium => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
            BaseBenefit::ProductivityBonus => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
            // Up to 10 % of the SBC does not integrate; again the law's cap.
            BaseBenefit::AttendanceBonus => [BenefitKind::SalaryPercent, Frequency::Monthly, SbcIntegration::UpToCap, true],
            BaseBenefit::MajorMedical => [BenefitKind::FixedAmount, Frequency::Yearly, SbcIntegration::Always, true],
            // A pension plan that meets the CONSAR requirements does not integrate (assumed, as with the savings fund).
            BaseBenefit::RetirementFund => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Never, true],
        };

        return [
            'kind' => $kind->value,
            'frequency' => $frequency->value,
            'sbc_integration' => $sbc->value,
            'in_payroll_tax' => $payrollTax,
        ];
    }

    /**
     * The first term of a base benefit: the legal minimum where the law sets one, zero where it does not.
     *
     * @return array{value: int, table: ?string}
     */
    private function firstTerm(BaseBenefit $base, FiscalSnapshot $legal): array
    {
        return match ($base) {
            BaseBenefit::ChristmasBonus => ['value' => $legal->rate('christmas_bonus_days')->integer() * self::HUNDREDTHS, 'table' => null],
            // 25 % is 2500 hundredths of a percent: the rate times 10,000.
            BaseBenefit::VacationPremium => ['value' => $legal->rate('vacation_premium')->of(10_000), 'table' => null],
            BaseBenefit::Vacation => $this->vacationTerm($legal),
            default => ['value' => 0, 'table' => null],
        };
    }

    /**
     * The days of vacation of LFT art. 76 as a table by years of service: a row where the days change.
     *
     * @return array{value: int, table: string}
     */
    private function vacationTerm(FiscalSnapshot $legal): array
    {
        $rows = [];
        $previous = 0;

        for ($years = 1; $years <= 35; $years++) {
            $days = $legal->legalVacationDays($years) * self::HUNDREDTHS;

            if ($days !== $previous) {
                $rows[] = ['from_years' => $years, 'value' => $days];
                $previous = $days;
            }
        }

        return ['value' => $rows[0]['value'] ?? 0, 'table' => json_encode($rows, JSON_THROW_ON_ERROR)];
    }
}
