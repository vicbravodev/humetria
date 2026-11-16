<?php

declare(strict_types=1);

namespace Database\Seeders\Demo\Stories;

use App\Modules\Compensation\Actions\SaveBenefit;
use App\Modules\Compensation\Actions\SaveContractTerm;
use App\Modules\Compensation\Actions\SaveGradeSalary;
use App\Modules\Compensation\Actions\SaveScenario;
use App\Modules\Compensation\Actions\SaveWorkRiskPremium;
use App\Modules\Compensation\Data\BenefitData;
use App\Modules\Compensation\Data\GradeSalaryData;
use App\Modules\Compensation\Data\ScenarioData;
use App\Modules\Compensation\Data\TermChange;
use App\Modules\Compensation\Data\TermData;
use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\FiscalParameter;
use App\Modules\Tenancy\Enums\MexicanState;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoContext;
use Database\Seeders\Demo\DemoStory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Compensación at Alba del Bajío. Two months ago HR opened the contract calculator, captured the tabulator (a daily salary
 * per job grade), the IMSS work risk premium of a metal-parts plant and the collective contract in force, above the law:
 * 30 days of aguinaldo, 50 % of vacation premium, vales, savings fund, an attendance bonus and a transport aid of its own for
 * the unionized staff. With the revision due at the start of next month, two proposals are on the table: the union's (9 % to
 * its people, more vales and transport) and the company's counter-offer (6 % and 4.5 % to trusted staff, a little more vales).
 * The module costs both against the contract in force; nothing here sets a computed number.
 *
 * Opening the calculator does not go through an action: the base benefits come from BaseCatalog, which the module keeps in
 * Support, closed to the story. The story writes the rows BaseCatalog writes the first time the panel opens (each base
 * benefit with the law's value of the year as its first term); a drift guard in the test compares both.
 */
final readonly class CompensationStory implements DemoStory
{
    private const int OPENED_DAYS_AGO = 60;

    /** Daily salary of each job grade of the roster, in cents. */
    private const array TABULATOR = [
        ['Operativo', 'O1', 38_000], ['Operativo', 'O2', 42_500], ['Operativo', 'O3', 52_000],
        ['Administrativo', 'A1', 45_000], ['Administrativo', 'A2', 65_000],
        ['Jefatura', 'J1', 90_000], ['Gerencia', 'G1', 180_000], ['Dirección', 'D1', 350_000],
    ];

    /** The collective contract in force, by base benefit: its value in hundredths (see BenefitKind). */
    private const array CONTRACT = [
        'christmas_bonus' => 3_000, 'vacation_premium' => 5_000, 'food_vouchers' => 120_000, 'savings_fund' => 1_300, 'attendance_bonus' => 1_000,
    ];

    /** A metal-parts plant's IMSS work risk premium, times 10^7: 2.5984 %. */
    private const int RISK_PREMIUM = 259_840;

    private const string TRANSPORT = 'Ayuda de transporte';

    /** What defines each base benefit in BaseCatalog: kind, frequency, SBC integration and whether it is in the payroll tax. */
    private const array BASE = [
        'christmas_bonus' => [BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Always, true],
        'vacation' => [BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Never, false],
        'vacation_premium' => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
        'savings_fund' => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Never, false],
        'food_vouchers' => [BenefitKind::FixedAmount, Frequency::Monthly, SbcIntegration::UpToCap, true],
        'seniority_premium' => [BenefitKind::SalaryDays, Frequency::Yearly, SbcIntegration::Always, true],
        'sunday_premium' => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
        'productivity_bonus' => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Always, true],
        'attendance_bonus' => [BenefitKind::SalaryPercent, Frequency::Monthly, SbcIntegration::UpToCap, true],
        'major_medical' => [BenefitKind::FixedAmount, Frequency::Yearly, SbcIntegration::Always, true],
        'retirement_fund' => [BenefitKind::SalaryPercent, Frequency::Yearly, SbcIntegration::Never, true],
    ];

    public function __construct(
        private SaveGradeSalary $salary,
        private SaveContractTerm $term,
        private SaveBenefit $benefit,
        private SaveWorkRiskPremium $premium,
        private SaveScenario $scenario,
    ) {}

    public function tell(DemoContext $context): void
    {
        $benefits = DemoClock::at('-'.self::OPENED_DAYS_AGO.' days', fn (): array => $this->contractInForce($context->tenantId));
        $revision = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay()->addMonthNoOverflow()->startOfMonth();

        DemoClock::at('-'.(self::OPENED_DAYS_AGO - 3).' days', function () use ($benefits, $revision): void {
            $this->propose('Revisión '.$revision->year.': propuesta del sindicato', $revision, 900, 0, [
                new TermChange($benefits['food_vouchers'], 150_000, null), new TermChange($benefits[self::TRANSPORT], 80_000, null),
            ]);
        });
        DemoClock::at('-'.(self::OPENED_DAYS_AGO - 10).' days', function () use ($benefits, $revision): void {
            $this->propose('Revisión '.$revision->year.': contrapropuesta de la empresa', $revision, 600, 450, [
                new TermChange($benefits['food_vouchers'], 135_000, null),
            ]);
        });

        Log::info('demo.compensation_seeded', ['scenarios' => 2]);
    }

    /**
     * HR opens the calculator and captures what the company pays today, from today on.
     *
     * @return array<string, string> the benefit ids by base key, and the company's own by name
     */
    private function contractInForce(string $tenantId): array
    {
        $benefits = $this->openCatalog($tenantId);
        $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();
        // On the first of January the law's terms start that very day: the contract's go on top of them the next one.
        $since = $today->isStartOfYear() ? $today->addDay() : $today;

        foreach (self::TABULATOR as [$category, $level, $cents]) {
            $this->salary->handle(new GradeSalaryData($category, $level, $cents, $since));
        }

        foreach (self::CONTRACT as $key => $value) {
            $this->term->handle($benefits[$key], new TermData($since, $value));
        }

        $transport = $this->benefit->handle(
            new BenefitData(null, self::TRANSPORT, BenefitKind::FixedAmount, Frequency::Monthly, PayGroup::Unionized, [], 0, SbcIntegration::Always, null, true, false, false, false, null),
            new TermData($since, 60_000),
        );
        $this->premium->handle($today->startOfYear(), self::RISK_PREMIUM);

        return [...array_map(fn (Benefit $benefit): string => $benefit->id, $benefits), self::TRANSPORT => $transport->id];
    }

    /** @param  list<TermChange>  $changes */
    private function propose(string $name, CarbonImmutable $revision, int $unionized, int $trusted, array $changes): void
    {
        $this->scenario->handle(new ScenarioData(null, $name, $revision, 0, 3, $unionized, $trusted, $changes, [], MexicanState::Queretaro->value));
    }

    /**
     * What BaseCatalog writes the first time the panel opens: each base benefit with, as its first term from the start of the
     * year, the law's value of the day (aguinaldo, vacation premium and the vacation table of art. 76) or zero.
     *
     * @return array<string, Benefit> by base key
     */
    private function openCatalog(string $tenantId): array
    {
        $now = CarbonImmutable::now();
        $legal = $this->legal($now);
        $from = $now->startOfYear()->toDateString();

        return DB::transaction(function () use ($tenantId, $legal, $from): array {
            $benefits = [];

            foreach (BaseBenefit::cases() as $base) {
                [$kind, $frequency, $sbc, $payrollTax] = self::BASE[$base->value];
                $benefit = Benefit::query()->create([
                    'tenant_id' => $tenantId, 'name' => $base->label(), 'base_key' => $base->value, 'job_grade_ids' => [],
                    'kind' => $kind, 'frequency' => $frequency, 'sbc_integration' => $sbc, 'in_payroll_tax' => $payrollTax,
                ]);
                [$value, $table] = match ($base) {
                    BaseBenefit::ChristmasBonus => [$legal['christmas_bonus_days'] ?? 0, null],
                    BaseBenefit::VacationPremium => [$legal['vacation_premium'] ?? 0, null],
                    BaseBenefit::Vacation => $this->vacationTable($legal),
                    default => [0, null],
                };
                ContractTerm::query()->create(['tenant_id' => $tenantId, 'benefit_id' => $benefit->id, 'effective_from' => $from, 'value' => $value, 'tenure_table' => $table]);
                $benefits[$base->value] = $benefit;
            }

            return $benefits;
        });
    }

    /**
     * The law's values of the day, in hundredths: the platform's latest of each key that started by then.
     *
     * @return array<string, int>
     */
    private function legal(CarbonImmutable $now): array
    {
        $values = [];

        foreach (FiscalParameter::query()->whereNull('state')->where('valid_from', '<=', $now->toDateString())->orderBy('valid_from')->get() as $parameter) {
            // Days are whole (15 days is 1500); a rate is of 10,000 (25 % is 2500).
            $values[$parameter->key] = self::scaled($parameter->value_int * ($parameter->key === 'vacation_premium' ? 10_000 : 100), $parameter->scale);
        }

        return $values;
    }

    /**
     * The days of vacation of art. 76 as a table by years of service, a row where the days change.
     *
     * @param  array<string, int>  $legal
     * @return array{int, list<array{from_years: int, value: int}>}
     */
    private function vacationTable(array $legal): array
    {
        $rows = [];
        $days = 0;

        for ($years = 1; $years <= 35; $years++) {
            $now = $legal["vacation_days.{$years}"] ?? $days;

            if ($now !== $days) {
                $rows[] = ['from_years' => $years, 'value' => $now];
                $days = $now;
            }
        }

        return [$rows[0]['value'] ?? 0, $rows];
    }

    private static function scaled(int $value, int $scale): int
    {
        for ($i = 0; $i < $scale; $i++) {
            $value = intdiv($value, 10);
        }

        return $value;
    }
}
