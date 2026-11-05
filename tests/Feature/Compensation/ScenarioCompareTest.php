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
            ->where('scenarios.0.first_year_text', fn (string $text): bool => str_starts_with($text, '$') && $text !== '$0')
            ->where('rows.0.label', 'Salario')->where('rows.0.type', 'salary')
            // 5 % of $500 a day for 365 days is $9,125.00; 6 % is $10,950.00; the bonus changes no salary.
            ->where('rows.0.cells', ['$9,125', '$10,950', '$9,125'])
            ->where('rows.1.label', 'Aguinaldo')->where('rows.1.type', 'benefit')
            ->where('rows.2.label', 'Prima vacacional')
            // The contract's bonus is in every scenario; C raises it from $1,000 to $1,500 a month: $6,000 more a year.
            ->where('rows.3.label', 'Bono de transporte')
            ->where('rows.3.cells', ['$0', '$0', '$6,000'])
            ->where('rows.4.type', 'contribution')
            ->where('rows.4.label', 'IMSS: excedente de enfermedad y maternidad'));
});

it('leaves out the concepts that no scenario changes', function (): void {
    $this->get(compareUrl($this->tenant, $this->a->id, $this->b->id))
        ->assertInertia(fn (Assert $page) => $page->where('rows', function ($rows): bool {
            $labels = collect($rows)->pluck('label')->all();

            // The fixed IMSS quota does not depend on the salary, and no benefit of the contract changes: neither is a choice.
            return ! in_array('IMSS: cuota fija de enfermedad y maternidad', $labels, true) && ! in_array('Fondo de ahorro', $labels, true) && in_array('Salario', $labels, true);
        }));
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

it('puts the scenarios from the cheapest, each with what it costs over the cheapest (board V4-Comp-Comparar)', function (): void {
    $this->get(compareUrl($this->tenant, $this->b->id, $this->a->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scenarios.0.id', $this->a->id)->where('scenarios.0.above_lowest_text', null)
            ->where('scenarios.0.raise_text', '5 % sindicalizados y confianza')->where('scenarios.0.retroactive_months', 0)
            ->where('scenarios.1.id', $this->b->id)
            ->where('scenarios.1.above_lowest_text', fn (string $text): bool => preg_match('/\A\+\$[\d,]+\z/', $text) === 1)
            // The 1 % between them, on the $500 of one person: $1,825 of salary a year, plus what it drags.
            ->where('rows.0.difference_text', '+$1,825')
            ->where('totals.first_year', fn ($cells): bool => count($cells) === 2)
            ->where('distance.from', 'A: cinco por ciento')->where('distance.to', 'B: seis por ciento')
            ->where('distance.first_year_text', fn (string $text): bool => preg_match('/\A\$[\d,]+\z/', $text) === 1)
            ->where('distance.mostly', 'salario')
            ->where('moves.unionized_point_text', fn (string $text): bool => preg_match('/\A\$[\d,]+00\z/', $text) === 1)
            ->where('moves.trusted_point_text', null)
            ->where('moves.retroactive_month_text', fn (string $text): bool => preg_match('/\A\$[\d,]+00\z/', $text) === 1)
            ->where('midpoint', ['raise_unit' => 'percent', 'unionized_raise' => '5.5', 'trusted_raise' => '', 'retroactive_months' => 0]));
});

it('offers no point in between two scenarios that raise in different ways', function (): void {
    $amount = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'raise_unit' => 'amount', 'group_changes' => ['unionized' => 60_800, 'trusted' => null], 'retroactive_months' => 3]);

    $this->get(compareUrl($this->tenant, $this->a->id, $amount->id))->assertInertia(fn (Assert $page) => $page->where('midpoint', null));
});

it('starts a new scenario at the point in between that the comparison offers', function (): void {
    $this->get(route('tenant.compensation.scenarios.create', [$this->tenant, 'name' => 'Punto medio', 'raise_unit' => 'percent', 'unionized_raise' => '5.5', 'trusted_raise' => '', 'retroactive_months' => '1']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaults.name', 'Punto medio')->where('defaults.raise_unit', 'percent')
            ->where('defaults.unionized_raise', '5.5')->where('defaults.trusted_raise', '')->where('defaults.retroactive_months', 1));

    $this->get(route('tenant.compensation.scenarios.create', [$this->tenant, 'raise_unit' => 'mucho', 'unionized_raise' => 'cinco', 'retroactive_months' => '99', 'name' => str_repeat('x', 200)]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('defaults.name', '')->where('defaults.raise_unit', 'percent')
            ->where('defaults.unionized_raise', '0')->where('defaults.retroactive_months', 0));
});
