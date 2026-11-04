<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => 'E-001']);
    $this->a = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'A: cinco por ciento', 'group_changes' => ['unionized' => 500, 'trusted' => null]]);
    $this->b = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'B: seis por ciento', 'group_changes' => ['unionized' => 600, 'trusted' => null]]);
});

function compareUrl(Tenant $tenant, string ...$ids): string
{
    return route('tenant.compensation.scenarios.compare', [$tenant, 'ids' => $ids]);
}

it('compares scenarios by concept, the cascade in its order', function (): void {
    $bonus = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bono de transporte', 'kind' => 'fixed_amount', 'frequency' => 'monthly']);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $bonus->id, 'effective_from' => '2026-01-01', 'value' => 100_000]);
    $c = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'C: con bono', 'group_changes' => ['unionized' => 500, 'trusted' => null],
        'term_changes' => [['benefit_id' => $bonus->id, 'value' => 150_000, 'tenure_table' => null]]]);

    $this->get(compareUrl($this->tenant, $this->a->id, $this->b->id, $c->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/scenario-compare')
            ->where('scenarios.0.id', $this->a->id)->where('scenarios.0.name', 'A: cinco por ciento')
            ->where('scenarios.1.id', $this->b->id)->where('scenarios.2.id', $c->id)
            ->where('scenarios.0.people', 1)
            ->where('scenarios.0.first_year_text', fn (string $text): bool => str_starts_with($text, '$') && $text !== '$0.00')
            ->where('rows.0.label', 'Salario')->where('rows.0.type', 'salary')
            // 5 % of $500 a day for 365 days is $9,125.00; 6 % is $10,950.00; the bonus changes no salary.
            ->where('rows.0.cells', ['$9,125.00', '$10,950.00', '$9,125.00'])
            ->where('rows.1.label', 'Aguinaldo')->where('rows.1.type', 'benefit')
            ->where('rows.2.label', 'Prima vacacional')
            // The contract's bonus is in every scenario; C raises it from $1,000 to $1,500 a month: $6,000 more a year.
            ->where('rows.3.label', 'Bono de transporte')
            ->where('rows.3.cells', ['$0.00', '$0.00', '$6,000.00'])
            ->where('rows.4.type', 'contribution')
            ->where('rows.4.label', 'IMSS: cuota fija de enfermedad y maternidad'));
});

it('knows the label of every employer line the calculation can give', function (): void {
    $response = $this->get(compareUrl($this->tenant, $this->a->id, $this->b->id))->assertOk();

    $response->assertInertia(fn (Assert $page) => $page->where('rows', function ($rows): bool {
        $labels = collect($rows)->pluck('label')->all();

        return in_array('Impuesto sobre nómina', $labels, true) && in_array('Infonavit', $labels, true) && ! in_array('', $labels, true);
    }));
});

it('wants between two and four scenarios', function (): void {
    $this->get(compareUrl($this->tenant, $this->a->id))->assertSessionHasErrors('ids');
    $this->get(route('tenant.compensation.scenarios.compare', $this->tenant))->assertSessionHasErrors('ids');
    $five = Scenario::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
    $this->get(compareUrl($this->tenant, $this->a->id, $this->b->id, $five[0]->id, $five[1]->id, $five[2]->id))->assertSessionHasErrors('ids');
    $this->get(compareUrl($this->tenant, $this->a->id, $this->a->id))->assertSessionHasErrors('ids.0');
});

it('answers 404 when a scenario is of another account or does not exist', function (): void {
    $foreign = Scenario::factory()->create();

    $this->get(compareUrl($this->tenant, $this->a->id, $foreign->id))->assertNotFound();
    $this->get(compareUrl($this->tenant, $this->a->id, (string) Str::uuid()))->assertNotFound();
    $this->get(compareUrl($this->tenant, $this->a->id, 'no-uuid'))->assertSessionHasErrors('ids.1');
});

it('is for whoever views compensation', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    $this->get(compareUrl($this->tenant, $this->a->id, $this->b->id))->assertForbidden();
});
