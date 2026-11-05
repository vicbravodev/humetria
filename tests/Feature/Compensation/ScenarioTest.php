<?php

declare(strict_types=1);

use App\Modules\Compensation\Actions\ArchiveScenario;
use App\Modules\Compensation\Actions\DuplicateScenario;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ArchiveScenario::class, DuplicateScenario::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner));
});

/**
 * The fields the editor sends.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function scenarioForm(array $override = []): array
{
    return [
        'name' => 'Revisión 2027', 'effective_on' => '2027-01-01', 'retroactive_months' => '2', 'horizon_years' => '4',
        'unionized_raise' => '5.75', 'trusted_raise' => '', 'default_state' => '', ...$override,
    ];
}

/** Ten people at $500 a day in one category, so a scenario has a cost. */
function staffOfTen(Tenant $tenant): JobGrade
{
    $grade = JobGrade::factory()->create(['tenant_id' => $tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);

    foreach (range(1, 10) as $ignored) {
        Employee::factory()->create(['tenant_id' => $tenant->id, 'job_grade_id' => $grade->id, 'hired_on' => '2025-01-01', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => 'E-'.fake()->unique()->numerify('#####')]);
    }

    return $grade;
}

it('lists the scenarios newest first with their cost, paginated ten at a time', function (): void {
    staffOfTen($this->tenant);
    $old = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Vieja', 'created_at' => '2026-01-01', 'group_changes' => ['unionized' => 0, 'trusted' => null]]);
    $new = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Nueva', 'created_at' => '2026-06-01', 'group_changes' => ['unionized' => 500, 'trusted' => 300], 'retroactive_months' => 2]);
    Scenario::factory()->archived()->create(['tenant_id' => $this->tenant->id, 'name' => 'Archivada']);

    $this->get(route('tenant.compensation.scenarios.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/scenarios')
            ->where('scenarios.total', 2)->where('scenarios.per_page', 10)
            ->where('scenarios.data.0.id', $new->id)->where('scenarios.data.0.name', 'Nueva')
            ->where('scenarios.data.0.effective_on', '2027-01-01')
            ->where('scenarios.data.0.horizon_years', 3)->where('scenarios.data.0.retroactive_months', 2)
            ->where('scenarios.data.0.unionized_raise', '5')->where('scenarios.data.0.trusted_raise', '3')
            ->where('scenarios.data.0.people', 10)
            ->where('scenarios.data.0.first_year_text', fn (string $text): bool => str_starts_with($text, '$') && $text !== '$0.00')
            ->where('scenarios.data.1.id', $old->id)->where('scenarios.data.1.first_year_text', '$0.00')
            ->where('scenarios.data.1.trusted_raise', null)
            ->where('counts', ['active' => 2, 'archived' => 1])
            ->where('filters', ['status' => 'active']));

    $this->get(route('tenant.compensation.scenarios.index', [$this->tenant, 'status' => 'archived']))
        ->assertInertia(fn (Assert $page) => $page->where('scenarios.total', 1)->where('scenarios.data.0.name', 'Archivada')->where('filters.status', 'archived'));
});

it('pages the scenarios and keeps the status in the page links', function (): void {
    Scenario::factory()->count(11)->create(['tenant_id' => $this->tenant->id]);

    $this->get(route('tenant.compensation.scenarios.index', [$this->tenant, 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('scenarios.current_page', 2)->has('scenarios.data', 1));
});

it('shows a new scenario with the platform assumptions and the states', function (): void {
    $this->get(route('tenant.compensation.scenarios.create', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/scenario')
            ->where('scenario', null)
            ->where('assumptions.0.key', 'inflation')->where('assumptions.0.label', 'Inflación')->where('assumptions.0.value', '3')
            ->where('assumptions.0.source', fn (string $source): bool => str_contains($source, 'Banco de México'))->where('assumptions.0.set_on', '2026-11-04')
            ->has('assumptions', 5)
            ->has('states', 32)
            ->where('defaults.effective_on', '2027-01-01')->where('defaults.horizon_years', 3)
            ->where('horizons', [3, 4, 5]));
});

it('saves a scenario from the editor and opens its report («Guardar y ver reporte», board V4-Comp-Escenario)', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bono de transporte']);

    $response = $this->post(route('tenant.compensation.scenarios.store', $this->tenant), scenarioForm([
        'trusted_raise' => '3', 'default_state' => 'JAL', 'assumptions' => ['turnover' => '8', 'inflation' => '3'],
        'term_changes' => [['benefit_id' => $benefit->id, 'value' => '1500', 'tenure_table' => [['from_years' => '2', 'value' => '1800']]]],
    ]));

    $saved = Scenario::query()->sole();
    $response->assertRedirect(route('tenant.compensation.scenarios.report', [$this->tenant, $saved->id]))->assertSessionHasNoErrors();

    expect($saved->name)->toBe('Revisión 2027')
        ->and($saved->retroactive_months)->toBe(2)->and($saved->horizon_years)->toBe(4)
        ->and($saved->group_changes)->toEqual(['unionized' => 575, 'trusted' => 300])
        ->and($saved->default_state)->toBe('JAL')
        ->and($saved->term_changes)->toEqual([['benefit_id' => $benefit->id, 'value' => 150000, 'tenure_table' => [['from_years' => 2, 'value' => 180000]]]])
        ->and($saved->assumptions['turnover']['value_bp'])->toBe(800);
});

it('copies the unionized raise to the trusted by default', function (): void {
    $this->post(route('tenant.compensation.scenarios.store', $this->tenant), scenarioForm(['trusted_raise' => '']))->assertSessionHasNoErrors();

    expect(Scenario::query()->sole()->group_changes['trusted'])->toBeNull();
});

it('answers the validation errors of the editor', function (): void {
    $url = route('tenant.compensation.scenarios.store', $this->tenant);

    $this->post($url, scenarioForm(['retroactive_months' => '60', 'horizon_years' => '3']))->assertSessionHasErrors(['retroactive_months']);
    $this->post($url, scenarioForm(['horizon_years' => '6']))->assertSessionHasErrors(['horizon_years']);
    $this->post($url, scenarioForm(['name' => '']))->assertSessionHasErrors(['name']);
    $this->post($url, scenarioForm(['unionized_raise' => 'cinco']))->assertSessionHasErrors(['unionized_raise']);
    $this->post($url, scenarioForm(['trusted_raise' => '5.555']))->assertSessionHasErrors(['trusted_raise']);
    $this->post($url, scenarioForm(['unionized_raise' => '101']))->assertSessionHasErrors(['unionized_raise']);
    $this->post($url, scenarioForm(['effective_on' => '01/01/2027']))->assertSessionHasErrors(['effective_on']);
    $this->post($url, scenarioForm(['assumptions' => ['turnover' => 'mucha']]))->assertSessionHasErrors(['assumptions.turnover']);
    $this->post($url, scenarioForm(['term_changes' => [['benefit_id' => 'x', 'value' => 'abc']]]))->assertSessionHasErrors(['term_changes']);
    $this->post($url, scenarioForm(['term_changes' => [['benefit_id' => (string) Str::uuid(), 'value' => '100']]]))->assertSessionHasErrors(['term_changes']);

    expect(Scenario::query()->count())->toBe(0);
});

it('edits a scenario and shows it as the editor needs it', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bono de transporte', 'kind' => 'fixed_amount', 'frequency' => 'monthly']);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $benefit->id, 'effective_from' => '2026-01-01', 'value' => 100_000]);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Primera', 'group_changes' => ['unionized' => 575, 'trusted' => null], 'default_state' => 'JAL',
        'term_changes' => [['benefit_id' => $benefit->id, 'value' => 150_000, 'tenure_table' => [['from_years' => 2, 'value' => 180_000]]]]]);

    $this->get(route('tenant.compensation.scenarios.edit', [$this->tenant, $scenario->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/scenario')
            ->where('scenario.id', $scenario->id)->where('scenario.name', 'Primera')
            ->where('scenario.unionized_raise', '5.75')->where('scenario.trusted_raise', '')->where('scenario.raise_unit', 'percent')
            ->where('scenario.default_state', 'JAL')->where('scenario.horizon_years', 3)
            ->where('scenario.term_changes.0.benefit_id', $benefit->id)
            ->where('scenario.term_changes.0.name', 'Bono de transporte')
            ->where('scenario.term_changes.0.unit', 'pesos')
            ->where('scenario.term_changes.0.value', '1500')
            ->where('scenario.term_changes.0.current_text', '$1,000.00')
            ->where('scenario.term_changes.0.tenure_table', [['from_years' => 2, 'value' => '1800']]));

    $this->put(route('tenant.compensation.scenarios.update', [$this->tenant, $scenario->id]), scenarioForm(['name' => 'Segunda', 'unionized_raise' => '6']))
        ->assertRedirect(route('tenant.compensation.scenarios.report', [$this->tenant, $scenario->id]));

    $edited = $scenario->fresh();

    expect($edited?->name)->toBe('Segunda')->and($edited?->group_changes['unionized'])->toBe(600)->and($edited?->term_changes)->toBe([]);
});

it('duplicates a scenario under another name and opens the copy', function (): void {
    Log::spy();
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Primera', 'retroactive_months' => 3, 'group_changes' => ['unionized' => 575, 'trusted' => 300]]);

    $response = $this->post(route('tenant.compensation.scenarios.duplicate', [$this->tenant, $scenario->id]));
    $copy = Scenario::query()->whereKeyNot($scenario->id)->sole();

    $response->assertRedirect(route('tenant.compensation.scenarios.edit', [$this->tenant, $copy->id]));
    expect($copy->name)->toBe('Copia de Primera')
        ->and($copy->retroactive_months)->toBe(3)
        ->and($copy->group_changes)->toEqual(['unionized' => 575, 'trusted' => 300])
        ->and($copy->assumptions)->toEqual($scenario->assumptions)
        ->and($copy->archived_at)->toBeNull();
    Log::shouldHaveReceived('info')->with('compensation.scenario_duplicated', ['scenario_id' => $copy->id, 'from_scenario_id' => $scenario->id])->once();
});

it('keeps the name of a copy inside 120 characters', function (): void {
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => str_repeat('a', 120)]);

    $this->post(route('tenant.compensation.scenarios.duplicate', [$this->tenant, $scenario->id]));

    expect(mb_strlen(Scenario::query()->whereKeyNot($scenario->id)->sole()->name))->toBe(120);
});

it('archives a scenario instead of deleting it and takes it out of the active list', function (): void {
    Log::spy();
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->post(route('tenant.compensation.scenarios.archive', [$this->tenant, $scenario->id]))->assertRedirect();

    expect($scenario->fresh()?->archived_at)->not->toBeNull()->and(Scenario::query()->count())->toBe(1);
    Log::shouldHaveReceived('info')->with('compensation.scenario_archived', ['scenario_id' => $scenario->id])->once();
    $this->get(route('tenant.compensation.scenarios.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('scenarios.total', 0)->where('counts.archived', 1));
});

it('lets only whoever manages compensation write, and whoever views it read', function (): void {
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach ([TenantRole::Viewer, TenantRole::Committee] as $role) {
        $this->actingAs(memberOf($this->tenant, $role));

        $this->get(route('tenant.compensation.scenarios.index', $this->tenant))->assertForbidden();
        $this->get(route('tenant.compensation.scenarios.create', $this->tenant))->assertForbidden();
        $this->post(route('tenant.compensation.scenarios.store', $this->tenant), scenarioForm())->assertForbidden();
        $this->put(route('tenant.compensation.scenarios.update', [$this->tenant, $scenario->id]), scenarioForm())->assertForbidden();
        $this->post(route('tenant.compensation.scenarios.duplicate', [$this->tenant, $scenario->id]))->assertForbidden();
        $this->post(route('tenant.compensation.scenarios.archive', [$this->tenant, $scenario->id]))->assertForbidden();
    }

    expect(Scenario::query()->count())->toBe(1);
});

it('searches the benefits of the company on the server for the editor', function (): void {
    Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bono de Transporte', 'kind' => 'fixed_amount']);
    Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Fondo de ahorro']);
    Benefit::factory()->archived()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bono viejo']);

    $this->getJson(route('tenant.compensation.scenarios.benefits', [$this->tenant, 'q' => 'transporte']))->assertOk()
        ->assertJsonCount(1)->assertJsonPath('0.label', 'Bono de Transporte')->assertJsonPath('0.unit', 'pesos');
    $this->getJson(route('tenant.compensation.scenarios.benefits', [$this->tenant, 'q' => 'bono']))->assertJsonCount(1);
});

it('saves a raise of a fixed amount a month from the editor and copies it to a duplicate', function (): void {
    $url = route('tenant.compensation.scenarios.store', $this->tenant);

    $this->post($url, scenarioForm(['raise_unit' => 'amount', 'unionized_raise' => '608', 'trusted_raise' => '300.50']))->assertSessionHasNoErrors();
    $saved = Scenario::query()->sole();

    expect($saved->raise_unit->value)->toBe('amount')->and($saved->group_changes)->toEqual(['unionized' => 60_800, 'trusted' => 30_050]);

    $this->post($url, scenarioForm(['raise_unit' => 'amount', 'unionized_raise' => '608.555']))->assertSessionHasErrors(['unionized_raise']);
    $this->post($url, scenarioForm(['raise_unit' => 'monto']))->assertSessionHasErrors(['raise_unit']);
    $this->post(route('tenant.compensation.scenarios.duplicate', [$this->tenant, $saved->id]));

    expect(Scenario::query()->whereKeyNot($saved->id)->sole()->raise_unit->value)->toBe('amount');
});

it('shows the editor what the other active scenarios cost, so this one is weighed against them (board V4-Comp-Escenario)', function (): void {
    staffOfTen($this->tenant);
    $scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Esta', 'created_at' => '2026-01-01']);
    Scenario::factory()->archived()->create(['tenant_id' => $this->tenant->id, 'name' => 'Archivada']);

    foreach (range(1, 5) as $day) {
        Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => "Otra {$day}", 'created_at' => "2026-02-0{$day}", 'group_changes' => ['unionized' => 100 * $day, 'trusted' => null]]);
    }

    $this->get(route('tenant.compensation.scenarios.edit', [$this->tenant, $scenario->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('others', 4)
            ->where('others.0.name', 'Otra 5')->where('others.3.name', 'Otra 2')
            ->where('others.0.first_year_cents', fn (int $cents): bool => $cents > 0)
            ->where('others.0.first_year_text', fn (string $text): bool => preg_match('/\A\$[\d,]+\z/', $text) === 1));

    $this->get(route('tenant.compensation.scenarios.create', $this->tenant))->assertInertia(fn (Assert $page) => $page->has('others', 4)->where('others.3.name', 'Otra 2'));
});
