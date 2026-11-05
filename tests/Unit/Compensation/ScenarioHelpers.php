<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Data\ConceptCost;
use App\Modules\Compensation\Data\CostBreakdown;
use App\Modules\Compensation\Data\CostCut;
use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Data\IndividualSalary;
use App\Modules\Compensation\Data\ScenarioInput;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\CostConcept;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Organization\Data\CompensationPerson;
use App\Modules\Organization\Data\HeadcountCell;
use App\Modules\Organization\Data\RosterGaps;
use App\Modules\Organization\Enums\StaffGroup;
use Carbon\CarbonImmutable;

/**
 * The 2026 values of docs/normas/compensation/criterios.md, written out by hand: UMA $117.31, minimum wage $315.04, the rates
 * of section 2, the cesantia y vejez column of 2026 (section 2.1), 15 days of aguinaldo, 25 % of premium, the vacation table
 * and 40 % of the UMA for the vouchers. Jalisco 3 % and Ciudad de México 4 % stand for the payroll tax of a state.
 *
 * @param  list<string>  $stale
 */
function scenarioFiscal(array $stale = []): FiscalSnapshot
{
    $values = [
        'uma' => Rate::scaled(11_731, 0),
        'minimum_wage' => Rate::scaled(31_504, 0),
        'imss_fixed' => Rate::scaled(2_040, 4),
        'imss_surplus' => Rate::scaled(110, 4),
        'imss_surplus_umas' => Rate::scaled(3, 0),
        'imss_pensioners' => Rate::scaled(105, 4),
        'imss_cash' => Rate::scaled(70, 4),
        'imss_disability_life' => Rate::scaled(175, 4),
        'imss_childcare' => Rate::scaled(100, 4),
        'retirement' => Rate::scaled(200, 4),
        'infonavit' => Rate::scaled(500, 4),
        'payroll_tax:JAL' => Rate::scaled(300, 4),
        'payroll_tax:CMX' => Rate::scaled(400, 4),
        'christmas_bonus_days' => Rate::scaled(15, 0),
        'vacation_premium' => Rate::scaled(25, 2),
        'voucher_exempt_uma' => Rate::scaled(40, 2),
        'seniority_premium_days' => Rate::scaled(12, 0),
        'seniority_premium_cap_minimum_wages' => Rate::scaled(2, 0),
    ];

    foreach ([2 => 150, 3 => 200, 4 => 250, 5 => 300, 6 => 350, 7 => 400] as $bracket => $limit) {
        $values["severance_old_age_limit.{$bracket}"] = Rate::scaled($limit, 2);
    }

    foreach ([3150, 3676, 4851, 5556, 6026, 6361, 6613, 7513] as $index => $percent) {
        $values['severance_old_age.'.($index + 1)] = Rate::scaled($percent, 5);
    }

    foreach ([1 => 12, 2 => 14, 3 => 16, 4 => 18, 5 => 20, 6 => 22, 11 => 24] as $years => $days) {
        $values["vacation_days.{$years}"] = Rate::scaled($days, 0);
    }

    return new FiscalSnapshot($values, $stale);
}

/**
 * @param  array{key?: string, kind?: BenefitKind, amount?: Rate, frequency?: Frequency, group?: ?PayGroup, sbcIntegration?: SbcIntegration, inPayrollTax?: bool, inSeniorityBase?: bool}  $with
 */
function scenarioBenefit(array $with = []): BenefitSpec
{
    return new BenefitSpec(
        key: $with['key'] ?? 'meal_bonus',
        kind: $with['kind'] ?? BenefitKind::FixedAmount,
        amount: $with['amount'] ?? Rate::scaled(100_000, 0),
        frequency: $with['frequency'] ?? Frequency::Monthly,
        group: $with['group'] ?? null,
        jobGradeIds: [],
        minTenureMonths: 0,
        tenureTable: [],
        sbcIntegration: $with['sbcIntegration'] ?? SbcIntegration::Never,
        sbcCapCents: null,
        inPayrollTax: $with['inPayrollTax'] ?? false,
        incomeTaxExempt: false,
        inSeniorityBase: $with['inSeniorityBase'] ?? false,
        capCents: null,
    );
}

/** @param  int<1, max>  $count */
function scenarioCell(int $count = 1, string $hireMonth = '2025-01-01', string $grade = 'g1', ?string $area = 'a1', StaffGroup $group = StaffGroup::Unionized, ?string $state = null): HeadcountCell
{
    return new HeadcountCell($grade, $area, $group, $state, CarbonImmutable::parse($hireMonth), $count);
}

function scenarioPerson(string $number, int $dailyCents, string $hiredOn = '2025-01-01', StaffGroup $group = StaffGroup::Unionized, ?string $state = null): IndividualSalary
{
    return new IndividualSalary(new CompensationPerson($number, 'g1', 'a1', $group, $state, CarbonImmutable::parse($hiredOn)), $dailyCents);
}

/**
 * One person (a cell of one) hired on 2025-01-01 with $500 a day, costed from 2026-01-01: always one year of service, so the
 * vacation is 12 days in the 12 months. The premium of work risk is 0.5 % and the state is Jalisco.
 *
 * @param  array{effectiveOn?: string, retroMonths?: int, fiscal?: FiscalSnapshot, cells?: list<HeadcountCell>, individuals?: list<IndividualSalary>, gradeSalaries?: array<string, int>, benefits?: list<BenefitSpec>, raises?: array<string, Rate>, amounts?: array<string, int>, proposed?: list<BenefitSpec>, premium?: ?Rate, state?: ?string}  $with
 */
function scenarioInput(array $with = []): ScenarioInput
{
    $cells = $with['cells'] ?? [scenarioCell()];

    return new ScenarioInput(
        effectiveOn: CarbonImmutable::parse($with['effectiveOn'] ?? '2026-01-01'),
        retroMonths: $with['retroMonths'] ?? 0,
        fiscal: $with['fiscal'] ?? scenarioFiscal(),
        cells: $cells,
        individuals: $with['individuals'] ?? [],
        gradeSalaries: $with['gradeSalaries'] ?? ['g1' => 50_000],
        benefits: $with['benefits'] ?? [],
        raises: $with['raises'] ?? [],
        proposed: $with['proposed'] ?? [],
        workRiskPremium: array_key_exists('premium', $with) ? $with['premium'] : Rate::scaled(50, 4),
        defaultState: array_key_exists('state', $with) ? $with['state'] : 'JAL',
        gaps: new RosterGaps(count($cells), count($cells), [], 0, 0),
        monthlyAmounts: $with['amounts'] ?? [],
    );
}

/**
 * @param  array{effectiveOn?: string, retroMonths?: int, fiscal?: FiscalSnapshot, cells?: list<HeadcountCell>, individuals?: list<IndividualSalary>, gradeSalaries?: array<string, int>, benefits?: list<BenefitSpec>, raises?: array<string, Rate>, proposed?: list<BenefitSpec>, premium?: ?Rate, state?: ?string}  $with
 * @return list<string>
 */
function scenarioUncalculated(array $with): array
{
    return ScenarioCost::firstYear(scenarioInput($with))->uncalculatedLines;
}

/** @return array<string, int> delta in cents by concept, "type:key" */
function deltasOf(CostBreakdown $cost): array
{
    $deltas = [];

    foreach ($cost->concepts as $concept) {
        $deltas["{$concept->type->value}:{$concept->key}"] = $concept->deltaCents();
    }

    return $deltas;
}

function conceptOf(CostBreakdown $cost, CostConcept $type, string $key): ?ConceptCost
{
    foreach ($cost->concepts as $concept) {
        if ($concept->type === $type && $concept->key === $key) {
            return $concept;
        }
    }

    return null;
}

/** @param  list<CostCut>  $cuts */
function cutOf(array $cuts, ?string $key): ?CostCut
{
    foreach ($cuts as $cut) {
        if ($cut->key === $key) {
            return $cut;
        }
    }

    return null;
}
