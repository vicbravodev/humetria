<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Compensation\Support\ContractStatus;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ContractStatus::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

/** Ten people in a category with a salary, one without a category and an empty category without a salary. */
function contractOfTen(Tenant $tenant): void
{
    $grade = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);

    foreach (range(1, 10) as $number) {
        Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => $number <= 7 ? 'unionized' : 'trusted', 'state' => 'JAL', 'employee_number' => "E-{$number}"]);
    }

    Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => null, 'employee_number' => 'E-90']);
    JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Coordinador', 'level' => 'A']);
}

it('starts a company with nothing loaded at its three steps (board V4-Comp-Inicio, primera vez)', function (): void {
    $this->get(route('tenant.compensation.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/index')
            ->where('status.roster', ['people' => 0, 'missing' => 0, 'without_grade' => 0, 'without_hire_date' => 0, 'without_group' => 0])
            ->where('first_time', true)
            ->where('scenarios.total', 0)
            ->where('today', null)
            ->where('negotiation', null));
});

it('counts the base benefits at the start, without having opened Prestaciones', function (): void {
    contractOfTen($this->tenant);

    $this->get(route('tenant.compensation.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('status.benefits', ['active' => 11]));
});

it('shows the data of the contract with what each lacks and the one notice of accuracy (C6)', function (): void {
    contractOfTen($this->tenant);
    Benefit::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);
    Benefit::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-1', 'effective_from' => '2026-01-01']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'E-1', 'effective_from' => '2026-06-01']);

    $this->get(route('tenant.compensation.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('first_time', false)
            ->where('status', [
                'roster' => ['people' => 11, 'missing' => 1, 'without_grade' => 1, 'without_hire_date' => 1, 'without_group' => 1],
                'tabulator' => ['grades' => 2, 'without_salary' => 1],
                // The 2 of the company and the 11 the base catalog gives it, which the start now makes sure of.
                'benefits' => ['active' => 13],
                'overrides' => ['people' => 1],
                'risk_premium' => ['set' => false],
                // The person without a category, the category without a salary and the premium.
                'accuracy' => ['costed' => 10, 'total' => 11, 'missing' => 3],
            ]));

    WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-01-01']);

    $this->get(route('tenant.compensation.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('status.risk_premium.set', true)->where('status.accuracy.missing', 2));
});

it('lists the active scenarios with what each costs against today, and what a point and a month cost at the table', function (): void {
    contractOfTen($this->tenant);
    $four = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cuatro', 'created_at' => '2026-10-01', 'group_changes' => ['unionized' => 400, 'trusted' => 250]]);
    $amount = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Monto', 'created_at' => '2026-10-02', 'retroactive_months' => 2, 'raise_unit' => 'amount', 'group_changes' => ['unionized' => 60_800, 'trusted' => null]]);
    Scenario::factory()->archived()->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('tenant.compensation.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scenarios.total', 2)->where('scenarios.per_page', 10)
            ->where('scenarios.data.0.id', $amount->id)
            ->where('scenarios.data.0.raise_text', '$608 al mes a sindicalizados y confianza')
            ->where('scenarios.data.1.id', $four->id)
            ->where('scenarios.data.1.raise_text', '4 % sindicalizados · 2.5 % confianza')
            ->where('scenarios.data.1.first_year_cents', fn (int $cents): bool => $cents > 0)
            ->where('scenarios.data.1.first_year_text', fn (string $text): bool => preg_match('/\A\$[\d,]+\z/', $text) === 1)
            ->where('scenarios.data.1.over_today_text', fn (string $text): bool => preg_match('/\A[1-9](\.\d)?\z/', $text) === 1)
            ->where('counts', ['active' => 2, 'archived' => 1])
            ->where('today.people', 10)
            ->where('today.cost_text', fn (string $text): bool => preg_match('/\A\$[\d,]+\z/', $text) === 1)
            // 7 unionized and 3 trusted at the same salary: a point to the unionized costs more than twice one to the trusted.
            ->where('negotiation.unionized_point_text', fn (string $text): bool => preg_match('/\A\$[\d,]+00\z/', $text) === 1)
            ->where('negotiation.trusted_point_text', fn (string $text): bool => preg_match('/\A\$[\d,]+00\z/', $text) === 1));
});

it('pages the scenarios of the start ten at a time', function (): void {
    Scenario::factory()->count(11)->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('tenant.compensation.index', [$this->tenant, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('scenarios.current_page', 2)->has('scenarios.data', 1));
});

it('shows a union its own proposals and data, never a company\'s', function (): void {
    contractOfTen($this->tenant);
    Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'De la empresa']);
    $union = Tenant::factory()->union()->create(['slug' => 'sindicato']);
    Scenario::factory()->create(['tenant_id' => $union->id, 'name' => 'Del sindicato']);

    $this->actingAs(ownerOf($union))
        ->get(route('tenant.compensation.index', $union))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scenarios.total', 1)->where('scenarios.data.0.name', 'Del sindicato')
            ->where('status.roster.people', 0)->where('status.tabulator.grades', 0));
});

it('leaves out what a point to the trusted costs when nobody is trusted', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => 'E-1']);

    $this->get(route('tenant.compensation.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('negotiation.trusted_point_text', null)->where('negotiation.unionized_point_text', fn (string $text): bool => $text !== '$0'));
});
