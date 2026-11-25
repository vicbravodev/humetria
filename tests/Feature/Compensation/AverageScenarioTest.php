<?php

declare(strict_types=1);

use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\ScenarioInputBuilder;
use App\Modules\Tenancy\Models\Tenant;

mutates(ScenarioInputBuilder::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/**
 * The scenario of the infographic: 500 workers at $15,000 a month, one year of service, a 6 % raise from 2026-03-01.
 *
 * @param  array<string, mixed>  $with
 */
function infographicScenario(Tenant $tenant, array $with = []): Scenario
{
    return Scenario::factory()->average()->create([
        'tenant_id' => $tenant->id,
        'effective_on' => '2026-03-01',
        'group_changes' => ['unionized' => 600, 'trusted' => null],
        ...$with,
    ]);
}

/** @return array<string, int> what the scenario adds, by kind of cost, in cents */
function addedBy(Scenario $scenario): array
{
    $cost = ScenarioCost::firstYear(resolve(ScenarioInputBuilder::class)->build($scenario));
    $added = ['salary' => 0, 'benefit' => 0, 'contribution' => 0, 'total' => $cost->deltaCents(), 'people' => $cost->people];

    foreach ($cost->concepts as $concept) {
        $added[$concept->type->value] += $concept->deltaCents();
    }

    return $added;
}

it('costs a scenario of an average payroll as the infographic does, with no roster and no tabulator', function (): void {
    expect(addedBy(infographicScenario($this->tenant)))->toBe([
        // 6 % of $15,000 is $900 a person a month: $450,000 a month for 500, twelve months of $5,400,000.
        'salary' => 540_000_000,
        // $540 a person a year of aguinaldo and vacation premium.
        'benefit' => 27_000_000,
        // The contributions the raise adds: $197.79 a person a month, with the premium of class I the company has not captured.
        'contribution' => 118_674_000,
        'total' => 685_674_000,
        'people' => 500,
    ]);
});

it('uses the premium the company captured over the one of class I', function (): void {
    WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-01-01', 'rate_scaled' => 758_875]);

    // The work risk of a person grows 87,196 cents a year at 7.58875 % instead of 6,246 at class I (the SBC goes from 52,465 to
    // 55,613 cents a day, over 365 days): $1,186,740 + 500 × (87,196 - 6,246) cents.
    expect(addedBy(infographicScenario($this->tenant))['contribution'])->toBe(159_149_000);
});

it('reads the contract of the company for an average payroll: its own aguinaldo days', function (): void {
    $aguinaldo = Benefit::factory()->base('christmas_bonus')->create(['tenant_id' => $this->tenant->id, 'kind' => BenefitKind::SalaryDays]);
    ContractTerm::factory()->forBenefit($aguinaldo)->create(['effective_from' => '2020-01-01', 'value' => 3_000]);

    // 30 days of aguinaldo instead of 15: 30 × $30 a day of raise is $900 a person ($450,000 for 500), plus $45,000 of premium.
    expect(addedBy(infographicScenario($this->tenant))['benefit'])->toBe(45_000_000 + 4_500_000);
});

it('costs the trusted staff an average payroll of their own group', function (): void {
    $trusted = infographicScenario($this->tenant, ['average_group' => 'trusted', 'group_changes' => ['unionized' => null, 'trusted' => 600]]);

    expect(addedBy($trusted)['salary'])->toBe(540_000_000);
});

it('costs a roster scenario on a monthly basis when it says so', function (): void {
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'pay_basis' => 'monthly']);

    expect(resolve(ScenarioInputBuilder::class)->build($scenario)->payBasis->value)->toBe('monthly');
});
