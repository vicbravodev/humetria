<?php

declare(strict_types=1);

use App\Modules\Organization\Http\Controllers\OrgChartQualityController;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

mutates(OrgChartQualityController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = memberOf($this->tenant, TenantRole::Owner);
    $this->dir = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dir']);
    $this->gte = Employee::factory()->reportingTo($this->dir)->create(['name' => 'Gte']);
    $this->ana = Employee::factory()->reportingTo($this->gte)->create(['name' => 'Ana']);
    $this->beto = Employee::factory()->reportingTo($this->dir)->create(['name' => 'Beto']);
});

function moveManager(string $employee, ?string $manager): Illuminate\Testing\TestResponse
{
    return test()->putJson(route('tenant.org-chart.manager.update', [test()->tenant, $employee]), ['manager_id' => $manager]);
}

it('moves a person under a new manager when the chart asks for JSON, with nothing to flash', function (): void {
    Log::spy();
    $this->actingAs($this->owner);

    moveManager($this->ana->id, $this->beto->id)->assertOk()->assertExactJson(['manager_id' => $this->beto->id]);

    expect($this->ana->refresh()->manager_id)->toBe($this->beto->id);
    Log::shouldHaveReceived('info')->with('employee.manager_changed', Mockery::on(fn (array $context): bool => $context['employee_id'] === $this->ana->id && $context['to_manager_id'] === $this->beto->id));
    $this->assertDatabaseHas('audit_entries', ['event' => 'employee.manager_changed', 'subject_id' => $this->ana->id]);
});

it('undoes a move with another one to the previous manager, or to nobody', function (): void {
    $this->actingAs($this->owner);

    moveManager($this->ana->id, $this->beto->id)->assertOk();
    moveManager($this->ana->id, $this->gte->id)->assertOk();
    moveManager($this->ana->id, null)->assertOk()->assertExactJson(['manager_id' => null]);

    expect($this->ana->refresh()->manager_id)->toBeNull();
});

it('does not let a person report to who reports to them, and says why', function (): void {
    $this->actingAs($this->owner);

    moveManager($this->dir->id, $this->ana->id)
        ->assertUnprocessable()
        ->assertJsonPath('errors.manager_id.0', 'Esa persona le reporta, directa o indirectamente. Elige a alguien más.');

    expect($this->dir->refresh()->manager_id)->toBeNull();
});

it('does not move anybody into another company, nor a person of another company', function (): void {
    $this->actingAs($this->owner);
    $theirs = Employee::factory()->create(['name' => 'Ajena']);

    moveManager($this->ana->id, $theirs->id)->assertUnprocessable()->assertJsonPath('errors.manager_id.0', 'Elige a una persona de la lista.');
    moveManager($theirs->id, $this->dir->id)->assertNotFound();

    expect($this->ana->refresh()->manager_id)->toBe($this->gte->id);
});

it('is for whoever may change the org chart only', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    moveManager($this->ana->id, $this->beto->id)->assertForbidden();

    expect($this->ana->refresh()->manager_id)->toBe($this->gte->id);
});
