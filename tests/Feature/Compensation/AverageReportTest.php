<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\ReportShare;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Compensation\Support\ReportBuilder;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReportBuilder::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    config(['results.min_group' => 5]);
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

function averageReportScenario(Tenant $tenant, array $with = []): Scenario
{
    return Scenario::factory()->average()->create(['tenant_id' => $tenant->id, 'name' => 'Promedio 2027', 'effective_on' => '2027-01-01', ...$with]);
}

it('opens the report of an average payroll with its one row, the same report as the roster scenarios', function (): void {
    $scenario = averageReportScenario($this->tenant, ['retroactive_months' => 2]);

    $this->get(route('tenant.compensation.quick.report', [$this->tenant, $scenario->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/report')
            ->where('scenario.name', 'Promedio 2027')->where('scenario.source', 'average')->where('scenario.pay_basis', 'monthly')
            ->where('scenario.average', ['workers' => 500, 'monthly_salary_text' => '$15,000.00', 'tenure_years' => 1, 'group' => 'Sindicalizados'])
            ->where('headline.people', 500)
            ->where('coverage.total', 500)->where('coverage.costed', 500)->where('coverage.missing', [])
            ->where('cuts.grades.0.label', 'Plantilla promedio')->where('cuts.grades.0.people', 500)
            ->where('cuts.areas.0.label', 'Sin área')
            ->where('raises.0.label', 'Plantilla promedio')->where('raises.0.people', 500)
            // The factory raises 5 %: $750 a month of the $15,000 of each person.
            ->where('raises.0.monthly_raise_cents', 75_000)
            // Whoever manages the contract still can, though an average payroll has no annex to download.
            ->where('can_annex', false)->where('can_manage', true)
            ->has('concepts')->has('months', 12)->has('projection', 3)->has('assumptions', 5));
});

it('tells that the premium of work risk is the one of class I when the company has not captured its own', function (): void {
    $scenario = averageReportScenario($this->tenant);

    $this->get(route('tenant.compensation.quick.report', [$this->tenant, $scenario->id]))
        ->assertInertia(fn (Assert $page) => $page->where('warnings.class_one_premium', true));

    WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-01-01']);

    $this->get(route('tenant.compensation.quick.report', [$this->tenant, $scenario->id]))
        ->assertInertia(fn (Assert $page) => $page->where('warnings.class_one_premium', false));
});

it('does not tell it of a scenario of the roster', function (): void {
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('tenant.compensation.scenarios.report', [$this->tenant, $scenario->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scenario.source', 'roster')->where('scenario.pay_basis', 'daily')->where('scenario.average', null)->where('warnings.class_one_premium', false));
});

it('shares the report of an average payroll from a link, with its row when the group reaches the minimum', function (): void {
    $scenario = averageReportScenario($this->tenant);
    $token = str_repeat('b', 64);
    ReportShare::factory()->create(['scenario_id' => $scenario->id, 'tenant_id' => $this->tenant->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addDays(2)]);

    $this->get(route('compensation.report.shared', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/shared-report')
            ->where('report.headline.people', 500)
            ->where('report.can_annex', false)->where('report.can_manage', false)
            ->where('report.warnings.class_one_premium', true));
});

it('folds an average payroll under the minimum of the company out of the shared report, as any small group', function (): void {
    $scenario = averageReportScenario($this->tenant, ['average_workers' => 3]);
    $token = str_repeat('c', 64);
    ReportShare::factory()->create(['scenario_id' => $scenario->id, 'tenant_id' => $this->tenant->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addDays(2)]);

    $this->get(route('compensation.report.shared', $token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.headline.people', 3)
            ->where('report.cuts.grades', [])
            ->where('report.raises', []));
});

it('does not let an annex of people be asked of an average payroll', function (): void {
    $scenario = averageReportScenario($this->tenant);

    $this->get(route('tenant.compensation.scenarios.annex', [$this->tenant, $scenario->id]))->assertNotFound();
});

function averageReportProps(Tenant $tenant, Scenario $scenario): array
{
    return test()->get(route('tenant.compensation.quick.report', [$tenant, $scenario->id]))->assertOk()->viewData('page')['props'];
}

it('answers what the raise really costs: the payroll growth, the drag of each peso and the raise against inflation', function (): void {
    $props = averageReportProps($this->tenant, averageReportScenario($this->tenant));
    $recurring = $props['headline']['recurring_cents'];

    // A 5 % raise of the salary alone: $750 a month to each of 500 people for 12 months, and 3 % of inflation by default.
    expect($props['impact'])->toMatchArray(['salary_growth_bp' => 500, 'inflation_bp' => 300, 'real_raise_bp' => 194])
        ->and($props['impact']['per_person_month_cents'])->toBe(intdiv(intdiv($recurring, 500), 12))
        ->and($props['impact']['drag_hundredths'])->toBe((int) round($recurring / 450_000_000 * 100))
        ->and($props['impact']['payroll_growth_bp'])->toBe((int) round($recurring / $props['headline']['before_cents'] * 10_000))
        // The fixed quota of the IMSS does not grow with the salary, so the payroll grows less than the raise.
        ->and($props['impact']['payroll_growth_bp'])->toBeLessThan(500);
});

it('walks one average worker through the cost step by step, from the engine', function (): void {
    $props = averageReportProps($this->tenant, averageReportScenario($this->tenant));
    $example = $props['example'];

    expect($example)->toMatchArray([
        // 500 people at $15,000 a month: the base example of the negotiation.
        'workers' => 500, 'monthly_payroll_cents' => 750_000_000,
        'monthly_salary_cents' => 1_500_000, 'monthly_raise_cents' => 75_000,
        'aguinaldo_days' => '15', 'vacation_days' => '12', 'vacation_premium_percent' => '25',
        'bonus_extra_year_cents' => 37_500, 'premium_extra_year_cents' => 7_500, 'salary_extra_year_cents' => 900_000,
        // 15 / 365 + 12 × 25 % / 365 on top of 1.
        'factor' => '1.0493',
        // $500 a day × 1.0493150… = $524.65; $525 × 1.0493150… = $550.89 (the cent is truncated).
        'sbc_before_cents' => 52_465, 'sbc_after_cents' => 55_089,
    ])->and($example['total_extra_year_cents'] * 500)->toBe($props['headline']['recurring_cents'])
        // Without a state there is no payroll tax to calculate, and the quotas are all of the rest.
        ->and($example['payroll_tax_extra_year_cents'])->toBe(0)
        ->and($example['contributions_extra_year_cents'])->toBe($example['total_extra_year_cents'] - 900_000 - 37_500 - 7_500)
        ->and($example['total_extra_month_cents'])->toBe(intdiv($example['total_extra_year_cents'], 12));
});

it('has no worker to walk through for the roster, only its totals', function (): void {
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('tenant.compensation.scenarios.report', [$this->tenant, $scenario->id]))
        ->assertInertia(fn (Assert $page) => $page->where('example', null)->has('impact'));
});

it('puts the raise on a ladder of two points apart, the current one among them', function (): void {
    $scenario = averageReportScenario($this->tenant, ['group_changes' => ['unionized' => 600, 'trusted' => null]]);
    $props = averageReportProps($this->tenant, $scenario);
    $ladder = $props['ladder'];

    expect(array_column($ladder, 'raise_text'))->toBe(['4 %', '6 %', '8 %', '10 %'])
        ->and(array_column($ladder, 'current'))->toBe([false, true, false, false])
        // Every rung costs more than the one below, and the current one is the scenario.
        ->and($ladder[1]['first_year_cents'])->toBe($props['headline']['first_year_cents'])
        ->and($ladder[0]['first_year_cents'])->toBeLessThan($ladder[1]['first_year_cents'])
        ->and($ladder[3]['first_year_cents'])->toBeGreaterThan($ladder[2]['first_year_cents'])
        ->and(array_column($ladder, 'salary_growth_bp'))->toBe([400, 600, 800, 1000]);
});

it('does not put a raise of a fixed amount on a ladder, nor one near zero below zero', function (): void {
    $low = averageReportProps($this->tenant, averageReportScenario($this->tenant, ['group_changes' => ['unionized' => 100, 'trusted' => null]]));

    expect(array_column($low['ladder'], 'raise_text'))->toBe(['1 %', '3 %', '5 %', '7 %']);

    $amount = averageReportProps($this->tenant, averageReportScenario($this->tenant, ['raise_unit' => 'amount', 'group_changes' => ['unionized' => 50_000, 'trusted' => null]]));

    expect($amount['ladder'])->toBe([]);
});

it('keeps the ladder out of a report shared by a link', function (): void {
    $scenario = averageReportScenario($this->tenant);
    $token = str_repeat('c', 64);
    ReportShare::factory()->create(['scenario_id' => $scenario->id, 'tenant_id' => $this->tenant->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addDays(2)]);

    $this->get(route('compensation.report.shared', $token))
        ->assertInertia(fn (Assert $page) => $page->where('report.ladder', [])->has('report.impact')->has('report.example'));
});

it('keeps the payroll tax of the state apart from the quotas of the worker, so each line says what it adds', function (): void {
    $without = averageReportProps($this->tenant, averageReportScenario($this->tenant))['example'];
    $with = averageReportProps($this->tenant, averageReportScenario($this->tenant, ['default_state' => 'JAL']))['example'];

    // The tax is on the payroll that is taxable: the salary, with the quotas of the SBC untouched by it.
    expect($with['payroll_tax_extra_year_cents'])->toBeGreaterThan(0)
        ->and($with['contributions_extra_year_cents'])->toBe($without['contributions_extra_year_cents'])
        ->and($with['total_extra_year_cents'])->toBe($with['salary_extra_year_cents'] + $with['bonus_extra_year_cents'] + $with['premium_extra_year_cents'] + $with['contributions_extra_year_cents'] + $with['payroll_tax_extra_year_cents']);
});
