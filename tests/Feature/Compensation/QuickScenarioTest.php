<?php

declare(strict_types=1);

use App\Modules\Compensation\Enums\PayBasis;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\ScenarioSource;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Support\AverageExample;
use App\Modules\Compensation\Support\AveragePayroll;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AveragePayroll::class, AverageExample::class);

/*
 * «Cotizador rápido»: the scenarios of an average payroll live under /compensation/quick with the same pages as the ones of the
 * roster under /compensation/scenarios. The address says which kind a page is for; the other kind is not shown there.
 */

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

/**
 * What the editor of an average payroll sends: 500 workers at $15,000 a month and a 6 % raise.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function averageForm(array $override = []): array
{
    return [
        'name' => 'Revisión 2027', 'effective_on' => '2027-01-01', 'retroactive_months' => '0', 'horizon_years' => '3',
        'unionized_raise' => '6', 'trusted_raise' => '', 'default_state' => '',
        'average_workers' => '500', 'average_monthly_salary' => '15000', 'average_tenure_years' => '1', 'average_group' => 'unionized',
        ...$override,
    ];
}

it('lists only the scenarios of an average payroll under quick, and only the ones of the roster under scenarios', function (): void {
    $average = Scenario::factory()->average()->create(['tenant_id' => $this->tenant->id, 'name' => 'Promedio']);
    $roster = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Plantilla']);
    Scenario::factory()->average()->archived()->create(['tenant_id' => $this->tenant->id, 'name' => 'Promedio archivado']);

    $this->get(route('tenant.compensation.quick.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/scenarios')
            ->where('scenarios.total', 1)->where('scenarios.data.0.id', $average->id)
            ->where('scenarios.data.0.people', 500)
            ->where('counts', ['active' => 1, 'archived' => 1]));

    $this->get(route('tenant.compensation.scenarios.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('scenarios.total', 1)->where('scenarios.data.0.id', $roster->id)
            ->where('counts', ['active' => 1, 'archived' => 0]));
});

it('opens a new scenario of an average payroll with its own fields and no raise per group', function (): void {
    $this->get(route('tenant.compensation.quick.create', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/scenario')
            ->where('source', 'average')
            ->where('scenario', null)
            ->where('defaults.average_workers', '')->where('defaults.average_monthly_salary', '')
            ->where('defaults.average_tenure_years', '1')->where('defaults.average_group', 'unionized')
            ->where('defaults.pay_basis', 'monthly'));

    $this->get(route('tenant.compensation.scenarios.create', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('source', 'roster')->where('defaults.pay_basis', 'daily'));
});

it('saves a scenario of an average payroll and opens its report under quick', function (): void {
    $response = $this->post(route('tenant.compensation.quick.store', $this->tenant), averageForm(['default_state' => 'JAL']));

    $saved = Scenario::query()->sole();
    $response->assertRedirect(route('tenant.compensation.quick.report', [$this->tenant, $saved->id]))->assertSessionHasNoErrors();

    expect($saved->source)->toBe(ScenarioSource::Average)
        ->and($saved->average_workers)->toBe(500)
        ->and($saved->average_monthly_salary_cents)->toBe(1_500_000)
        ->and($saved->average_tenure_years)->toBe(1)
        ->and($saved->average_group)->toBe(PayGroup::Unionized)
        ->and($saved->pay_basis)->toBe(PayBasis::Monthly)
        ->and($saved->group_changes)->toEqual(['unionized' => 600, 'trusted' => null])
        ->and($saved->default_state)->toBe('JAL');
});

it('gives the raise of an average payroll of trusted staff to the trusted', function (): void {
    $this->post(route('tenant.compensation.quick.store', $this->tenant), averageForm(['average_group' => 'trusted']))->assertSessionHasNoErrors();

    expect(Scenario::query()->sole()->group_changes)->toEqual(['unionized' => null, 'trusted' => 600]);
});

it('answers what is wrong with an average payroll, by field', function (array $override, string $field): void {
    $this->post(route('tenant.compensation.quick.store', $this->tenant), averageForm($override))->assertSessionHasErrors([$field]);

    expect(Scenario::query()->count())->toBe(0);
})->with([
    'no workers' => [['average_workers' => ''], 'average_workers'],
    'workers that are not a number' => [['average_workers' => 'muchos'], 'average_workers'],
    'a salary that is not money' => [['average_monthly_salary' => 'abc'], 'average_monthly_salary'],
    'a salary with a thousands comma' => [['average_monthly_salary' => '15,000'], 'average_monthly_salary'],
    'no years of service' => [['average_tenure_years' => ''], 'average_tenure_years'],
    'a group that does not exist' => [['average_group' => 'jefes'], 'average_group'],
    'no group' => [['average_group' => ''], 'average_group'],
]);

it('lets a scenario of the roster say how its salary is paid, and always pays an average payroll monthly', function (): void {
    $this->post(route('tenant.compensation.scenarios.store', $this->tenant), averageForm(['pay_basis' => 'monthly']))->assertSessionHasNoErrors();
    $this->post(route('tenant.compensation.quick.store', $this->tenant), averageForm(['name' => 'Otra', 'pay_basis' => 'daily']))->assertSessionHasNoErrors();

    $roster = Scenario::query()->where('name', 'Revisión 2027')->sole();
    $average = Scenario::query()->where('name', 'Otra')->sole();

    // The roster scenario carries the average fields of the form but keeps none of them.
    expect($roster->source)->toBe(ScenarioSource::Roster)->and($roster->pay_basis)->toBe(PayBasis::Monthly)->and($roster->average_workers)->toBeNull()
        ->and($average->source)->toBe(ScenarioSource::Average)->and($average->pay_basis)->toBe(PayBasis::Monthly);

    $this->post(route('tenant.compensation.scenarios.store', $this->tenant), averageForm(['pay_basis' => 'weekly']))->assertSessionHasErrors(['pay_basis']);
});

it('edits a scenario of an average payroll and shows it as the editor needs it', function (): void {
    $scenario = Scenario::factory()->average()->create(['tenant_id' => $this->tenant->id, 'name' => 'Promedio', 'group_changes' => ['unionized' => 575, 'trusted' => null], 'default_state' => 'JAL']);

    $this->get(route('tenant.compensation.quick.edit', [$this->tenant, $scenario->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/scenario')
            ->where('source', 'average')
            ->where('scenario.id', $scenario->id)
            ->where('scenario.average_workers', '500')->where('scenario.average_monthly_salary', '15000')
            ->where('scenario.average_tenure_years', '1')->where('scenario.average_group', 'unionized')
            ->where('scenario.unionized_raise', '5.75')->where('scenario.pay_basis', 'monthly'));

    $this->put(route('tenant.compensation.quick.update', [$this->tenant, $scenario->id]), averageForm(['name' => 'Segunda', 'average_workers' => '800', 'average_group' => 'trusted']))
        ->assertRedirect(route('tenant.compensation.quick.report', [$this->tenant, $scenario->id]));

    $edited = $scenario->fresh();

    expect($edited?->name)->toBe('Segunda')->and($edited?->average_workers)->toBe(800)->and($edited?->group_changes)->toEqual(['unionized' => null, 'trusted' => 600]);
});

it('shows the trusted raise of an average payroll of trusted staff as its one raise', function (): void {
    $scenario = Scenario::factory()->average()->create(['tenant_id' => $this->tenant->id, 'average_group' => 'trusted', 'group_changes' => ['unionized' => null, 'trusted' => 300]]);

    $this->get(route('tenant.compensation.quick.edit', [$this->tenant, $scenario->id]))
        ->assertInertia(fn (Assert $page) => $page->where('scenario.average_group', 'trusted')->where('scenario.unionized_raise', '3'));
});

it('sends a scenario to the pages of its kind', function (): void {
    $average = Scenario::factory()->average()->create(['tenant_id' => $this->tenant->id]);
    $roster = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('tenant.compensation.scenarios.edit', [$this->tenant, $average->id]))->assertRedirect(route('tenant.compensation.quick.edit', [$this->tenant, $average->id]));
    $this->get(route('tenant.compensation.quick.edit', [$this->tenant, $roster->id]))->assertRedirect(route('tenant.compensation.scenarios.edit', [$this->tenant, $roster->id]));
    $this->get(route('tenant.compensation.scenarios.report', [$this->tenant, $average->id]))->assertRedirect(route('tenant.compensation.quick.report', [$this->tenant, $average->id]));
    $this->get(route('tenant.compensation.quick.report', [$this->tenant, $roster->id]))->assertRedirect(route('tenant.compensation.scenarios.report', [$this->tenant, $roster->id]));
});

it('does not compare a scenario of an average payroll with one of the roster', function (): void {
    $average = Scenario::factory()->average()->count(2)->create(['tenant_id' => $this->tenant->id]);
    $roster = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('tenant.compensation.quick.compare', [$this->tenant, 'ids' => [$average[0]->id, $roster->id]]))->assertNotFound();
    $this->get(route('tenant.compensation.scenarios.compare', [$this->tenant, 'ids' => [$average[0]->id, $average[1]->id]]))->assertNotFound();
    $this->get(route('tenant.compensation.quick.compare', [$this->tenant, 'ids' => [$average[0]->id, $average[1]->id]]))->assertOk();
});

it('costs an average payroll live as it is typed and saves nothing', function (): void {
    $this->postJson(route('tenant.compensation.quick.preview', $this->tenant), averageForm())
        ->assertOk()
        ->assertJsonPath('people', 500)
        ->assertJsonPath('left_out', 0)
        ->assertJsonPath('groups', ['unionized' => 500, 'trusted' => 0]);

    expect(Scenario::query()->count())->toBe(0);
});

it('lets only whoever manages compensation write an average payroll, and whoever views it read', function (): void {
    $viewer = Tenant::factory()->create();
    $this->actingAs(memberOf($viewer, TenantRole::Viewer));

    $this->get(route('tenant.compensation.quick.index', $viewer))->assertForbidden();
    $this->get(route('tenant.compensation.quick.create', $viewer))->assertForbidden();
    $this->post(route('tenant.compensation.quick.store', $viewer), averageForm())->assertForbidden();
});

it('does not open an average payroll of another company', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $foreign = Scenario::factory()->average()->create(['tenant_id' => $other->id]);
    workIn($this->tenant);

    $this->get(route('tenant.compensation.quick.edit', [$this->tenant, $foreign->id]))->assertNotFound();
});
