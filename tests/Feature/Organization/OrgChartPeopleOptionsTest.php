<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\OrgChartPeopleController;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

mutates(OrgChartPeopleController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function person(Tenant $tenant, string $name, array $attributes = []): Employee
{
    return Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => $name, 'job_title' => 'Analista', 'employee_number' => null, ...$attributes]);
}

it('finds active people of the company by name or employee number, ignoring case and accents', function (): void {
    $ana = person($this->tenant, 'Ána López', ['job_title' => 'Gerente de planta']);
    $beto = person($this->tenant, 'Beto Ruiz', ['employee_number' => 'ANA-7', 'job_title' => null]);
    person($this->tenant, 'Carla Díaz');
    person($this->tenant, 'Ana que se fue', ['deactivated_at' => now()]);
    person(Tenant::factory()->create(), 'Ana de otra empresa');

    $found = $this->actingAs($this->hr)
        ->getJson(route('tenant.org-chart.people.options', [$this->tenant, 'q' => ' ana ']))
        ->assertOk()
        ->json();

    // Name and job title only: never the email or the mobile.
    expect($found)->toEqualCanonicalizing([
        ['value' => $ana->id, 'label' => 'Ána López · Gerente de planta'],
        ['value' => $beto->id, 'label' => 'Beto Ruiz'],
    ]);
});

it('leaves out a person and whoever reports to them, directly or not', function (): void {
    $carla = person($this->tenant, 'Carla');
    $beto = Employee::factory()->reportingTo($carla)->create(['name' => 'Beto']);
    Employee::factory()->reportingTo($beto)->create(['name' => 'Ana']);
    $dora = person($this->tenant, 'Dora');

    $this->actingAs($this->hr)
        ->getJson(route('tenant.org-chart.people.options', [$this->tenant, 'except' => $carla->id]))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.value', $dora->id);

    // Something that is not an id leaves nobody out.
    $this->actingAs($this->hr)
        ->getJson(route('tenant.org-chart.people.options', [$this->tenant, 'except' => 'nobody']))
        ->assertJsonCount(4);
});

it('gives at most 20 people, by name', function (): void {
    Employee::factory()->count(20)->create(['tenant_id' => $this->tenant->id]);
    $first = person($this->tenant, '  Aaa');

    $this->actingAs($this->hr)
        ->getJson(route('tenant.org-chart.people.options', $this->tenant))
        ->assertJsonCount(20)
        ->assertJsonPath('0.value', $first->id);
});

it('is only for those who manage the organization or the org chart', function (TenantRole $role, int $status): void {
    $this->actingAs(memberOf($this->tenant, $role))
        ->getJson(route('tenant.org-chart.people.options', $this->tenant))
        ->assertStatus($status);
})->with([
    'owner' => [TenantRole::Owner, 200],
    'hr' => [TenantRole::Hr, 200],
    'viewer' => [TenantRole::Viewer, 403],
]);

it('is not for outsiders', function (): void {
    $this->getJson(route('tenant.org-chart.people.options', $this->tenant))->assertUnauthorized();
    $this->actingAs(memberOf(Tenant::factory()->create(), TenantRole::Owner))
        ->getJson(route('tenant.org-chart.people.options', $this->tenant))
        ->assertNotFound();
});

it('can leave out the person alone, to choose who takes their place among those who report to them', function (): void {
    $carla = person($this->tenant, 'Carla');
    $beto = Employee::factory()->reportingTo($carla)->create(['name' => 'Beto']);

    $this->actingAs($this->hr)
        ->getJson(route('tenant.org-chart.people.options', [$this->tenant, 'except' => $carla->id, 'keep_reports' => '1']))
        ->assertJsonCount(1)
        ->assertJsonPath('0.value', $beto->id);
});
