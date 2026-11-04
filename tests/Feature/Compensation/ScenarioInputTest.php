<?php

declare(strict_types=1);

use App\Modules\Compensation\Data\BenefitSpec;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Compensation\Support\Calculation\Rate;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\ScenarioInputBuilder;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;

mutates(ScenarioInputBuilder::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
});

/** A person the calculator can cost, hired on 2025-01-01 unless said otherwise. @param  array<string, mixed>  $attributes */
function staffMember(Tenant $tenant, JobGrade $grade, array $attributes = []): Employee
{
    return Employee::factory()->create([
        'tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'area_id' => null, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized',
        'state' => 'JAL', 'employee_number' => 'E-'.fake()->unique()->numerify('#####'),
        ...$attributes,
    ]);
}

function builtInput(Scenario $scenario): App\Modules\Compensation\Data\ScenarioInput
{
    return resolve(ScenarioInputBuilder::class)->build($scenario);
}

/** @param  list<BenefitSpec>  $specs */
function specOf(array $specs, string $key): ?BenefitSpec
{
    return array_find($specs, fn (BenefitSpec $spec): bool => $spec->key === $key);
}

it('counts the people on the effective date by cell and takes the tabulator salary in force that day', function (): void {
    staffMember($this->tenant, $this->grade);
    staffMember($this->tenant, $this->grade);
    staffMember($this->tenant, $this->grade, ['hired_on' => '2027-06-01']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 45_000, 'effective_from' => '2025-01-01']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-06-01']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 99_000, 'effective_from' => '2027-02-01']);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2027-01-01']);

    $input = builtInput($scenario);

    expect($input->effectiveOn->toDateString())->toBe('2027-01-01')
        ->and($input->cells)->toHaveCount(1)->and($input->cells[0]->count)->toBe(2)
        ->and($input->gradeSalaries)->toBe([$this->grade->id => 50_000])
        ->and($input->retroMonths)->toBe(0);
});

it('takes a person with an individual salary out of the cells and gives them their salary in force', function (): void {
    $rich = staffMember($this->tenant, $this->grade, ['employee_number' => 'E-1']);
    staffMember($this->tenant, $this->grade);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'e-1', 'daily_salary_cents' => 70_000, 'effective_from' => '2026-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-1', 'daily_salary_cents' => 80_000, 'effective_from' => '2026-07-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-1', 'daily_salary_cents' => 90_000, 'effective_from' => '2027-03-01']);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2027-01-01']);

    $input = builtInput($scenario);

    expect($input->cells)->toHaveCount(1)->and($input->cells[0]->count)->toBe(1)
        ->and($input->individuals)->toHaveCount(1)
        ->and($input->individuals[0]->person->employeeNumber)->toBe($rich->employee_number)
        ->and($input->individuals[0]->dailySalaryCents)->toBe(80_000);
});

it('leaves out of both the person whose individual salary has not started yet and costs them by the tabulator', function (): void {
    staffMember($this->tenant, $this->grade, ['employee_number' => 'E-1']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-1', 'daily_salary_cents' => 90_000, 'effective_from' => '2027-03-01']);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2027-01-01']);

    $input = builtInput($scenario);

    expect($input->individuals)->toBe([])->and($input->cells)->toHaveCount(1);
});

it('does not cost an individual salary whose person is not in the roster and says so in the gaps', function (): void {
    staffMember($this->tenant, $this->grade, ['employee_number' => 'E-2', 'hired_on' => null]);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-2', 'daily_salary_cents' => 90_000, 'effective_from' => '2026-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'GONE', 'daily_salary_cents' => 90_000, 'effective_from' => '2026-01-01']);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);

    $input = builtInput($scenario);

    expect($input->individuals)->toBe([])->and($input->cells)->toBe([])
        ->and($input->gaps->missing)->toBe(['E-2'])->and($input->gaps->total)->toBe(1)->and($input->gaps->costed)->toBe(0);
});

it('reads the benefits with their terms in force on the effective date', function (): void {
    $bonus = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => BenefitKind::FixedAmount, 'frequency' => Frequency::Monthly, 'applies_to_group' => PayGroup::Unionized, 'job_grade_ids' => [$this->grade->id], 'min_tenure_months' => 6, 'sbc_integration' => SbcIntegration::UpToCap, 'sbc_cap_cents' => 4_000, 'in_payroll_tax' => false, 'income_tax_exempt' => true, 'in_seniority_base' => true, 'cap_cents' => 900_000]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $bonus->id, 'effective_from' => '2026-01-01', 'value' => 100_000]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $bonus->id, 'effective_from' => '2026-07-01', 'value' => 120_000]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $bonus->id, 'effective_from' => '2027-06-01', 'value' => 999_999]);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2027-01-01']);

    $spec = specOf(builtInput($scenario)->benefits, $bonus->id);

    expect($spec)->toBeInstanceOf(BenefitSpec::class)
        ->and($spec->kind)->toBe(BenefitKind::FixedAmount)
        ->and($spec->amount->integer())->toBe(120_000)
        ->and($spec->frequency)->toBe(Frequency::Monthly)
        ->and($spec->group)->toBe(PayGroup::Unionized)
        ->and($spec->jobGradeIds)->toBe([$this->grade->id])
        ->and($spec->minTenureMonths)->toBe(6)
        ->and($spec->sbcIntegration)->toBe(SbcIntegration::UpToCap)
        ->and($spec->sbcCapCents)->toBe(4_000)
        ->and($spec->inPayrollTax)->toBeFalse()
        ->and($spec->incomeTaxExempt)->toBeTrue()
        ->and($spec->inSeniorityBase)->toBeTrue()
        ->and($spec->capCents)->toBe(900_000);
});

it('keys a base benefit by its base key and leaves out archived benefits and the ones without terms yet', function (): void {
    $aguinaldo = Benefit::factory()->base('christmas_bonus')->create(['tenant_id' => $this->tenant->id, 'kind' => BenefitKind::SalaryDays]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $aguinaldo->id, 'effective_from' => '2026-01-01', 'value' => 3_000]);
    $archived = Benefit::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $archived->id, 'effective_from' => '2026-01-01']);
    $later = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $later->id, 'effective_from' => '2028-01-01']);
    $none = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2027-01-01']);

    $keys = array_map(fn (BenefitSpec $spec): string => $spec->key, builtInput($scenario)->benefits);

    expect($keys)->toBe(['christmas_bonus']);
});

it('turns a stored value into the rate of the calculation by kind and keeps the tenure table', function (): void {
    $cases = [
        [BenefitKind::SalaryDays, 1_500, 15],
        [BenefitKind::SalaryPercent, 2_500, 0.25],
        [BenefitKind::FixedAmount, 50_000, 50_000],
        [BenefitKind::UmaMultiple, 150, 1.5],
    ];

    foreach ($cases as [$kind, $stored, $expected]) {
        $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => $kind]);
        ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $benefit->id, 'effective_from' => '2026-01-01', 'value' => $stored, 'tenure_table' => [['from_years' => 1, 'value' => $stored * 2]]]);
        $spec = specOf(builtInput(Scenario::factory()->create(['tenant_id' => $this->tenant->id]))->benefits, $benefit->id);

        expect($spec?->amount->compareTo(Rate::scaled((int) round($expected * 100), 2)))->toBe(0)
            ->and($spec?->tenureTable[0]['fromYears'])->toBe(1)
            ->and($spec?->tenureTable[0]['value']->compareTo(Rate::scaled((int) round($expected * 200), 2)))->toBe(0);
    }
});

it('proposes the changed terms in place of the current ones, or as a new benefit', function (): void {
    $bonus = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => BenefitKind::FixedAmount]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $bonus->id, 'effective_from' => '2026-01-01', 'value' => 100_000]);
    $new = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => BenefitKind::SalaryDays]);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'term_changes' => [
        ['benefit_id' => $bonus->id, 'value' => 130_000, 'tenure_table' => null],
        ['benefit_id' => $new->id, 'value' => 500, 'tenure_table' => [['from_years' => 2, 'value' => 700]]],
    ]]);

    $input = builtInput($scenario);

    expect(specOf($input->benefits, $bonus->id)?->amount->integer())->toBe(100_000)
        ->and($input->proposed)->toHaveCount(2)
        ->and(specOf($input->proposed, $bonus->id)?->amount->integer())->toBe(130_000)
        ->and(specOf($input->proposed, $new->id)?->amount->compareTo(Rate::scaled(5, 0)))->toBe(0)
        ->and(specOf($input->proposed, $new->id)?->tenureTable[0]['value']->compareTo(Rate::scaled(7, 0)))->toBe(0)
        ->and(specOf($input->benefits, $new->id))->toBeNull();
});

it('leaves out a proposed change whose benefit was archived after the scenario was saved', function (): void {
    $bonus = Benefit::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'term_changes' => [['benefit_id' => $bonus->id, 'value' => 130_000, 'tenure_table' => null]]]);

    expect(builtInput($scenario)->proposed)->toBe([]);
});

it('turns the raises into rates, the trusted copying the unionized when they have none of their own', function (): void {
    $copy = builtInput(Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'group_changes' => ['unionized' => 575, 'trusted' => null]]));
    $own = builtInput(Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'group_changes' => ['unionized' => 575, 'trusted' => 300]]));

    expect(array_keys($copy->raises))->toBe(['unionized'])->and($copy->raises['unionized']->compareTo(Rate::scaled(575, 4)))->toBe(0)
        ->and($own->raises['trusted']->compareTo(Rate::scaled(300, 4)))->toBe(0);
});

it('reads the fiscal values and the work risk premium of the effective date and the default state', function (): void {
    WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-01-01', 'rate_scaled' => 113_065]);
    WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-07-01', 'rate_scaled' => 758_875]);
    WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2028-01-01', 'rate_scaled' => 1_000_000]);

    $input = builtInput(Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2027-01-01', 'retroactive_months' => 3, 'default_state' => 'JAL']));
    $without = builtInput(Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2025-06-01']));

    expect($input->workRiskPremium?->compareTo(Rate::scaled(758_875, 7)))->toBe(0)
        ->and($input->defaultState)->toBe('JAL')->and($input->retroMonths)->toBe(3)
        ->and($input->fiscal->uma())->toBe(11_731)
        ->and($without->workRiskPremium)->toBeNull()->and($without->defaultState)->toBeNull();
});

it('costs a built scenario end to end: ten people at 500 pesos and a 5 percent raise', function (): void {
    foreach (range(1, 10) as $ignored) {
        staffMember($this->tenant, $this->grade);
    }

    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'effective_on' => '2027-01-01', 'group_changes' => ['unionized' => 500, 'trusted' => null]]);

    $cost = ScenarioCost::firstYear(builtInput($scenario));

    // criterios.md: $500 → $525 a day for 365 days is $9,125 a person; ten people, $91,250.
    expect($cost->people)->toBe(10)
        ->and(array_find($cost->concepts, fn ($concept): bool => $concept->key === 'salary')?->deltaCents())->toBe(9_125_000)
        ->and($cost->deltaCents())->toBeGreaterThan(9_125_000);
});
