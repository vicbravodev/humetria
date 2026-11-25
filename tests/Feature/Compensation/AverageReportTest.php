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
