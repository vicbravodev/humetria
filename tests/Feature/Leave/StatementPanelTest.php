<?php

declare(strict_types=1);

use App\Modules\Leave\Http\Controllers\StatementController;
use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

mutates(StatementController::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']));
    $this->travelTo('2026-03-16 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    $period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14, 'extra_days' => 2, 'premium_percent' => 30]);
    $this->statement = LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'leave_entitlement_id' => $period->id, 'issued_at' => '2026-03-15 18:00:00']);
});

it('lives under the vacations of the company', function (): void {
    expect(route('tenant.vacations.statements.show', [$this->tenant, $this->statement], absolute: false))->toBe("/admin/acme/vacations/statements/{$this->statement->id}");
});

it('gives RH the sheet of the period with its acknowledgment, never an amount', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.vacations.statements.show', [$this->tenant, $this->statement]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave/statement')
            ->where('employeeId', $this->ana->id)
            ->where('sheet', [
                'company' => 'Acme', 'name' => 'Ana Solís', 'startedOn' => '15 de marzo de 2024', 'years' => 2, 'days' => 16,
                'enjoyFrom' => '15 de marzo de 2026', 'enjoyBy' => '15 de septiembre de 2026', 'premiumPercent' => 30,
                'issuedOn' => '15 de marzo de 2026', 'acknowledgedOn' => null,
            ]));

    LeaveStatement::query()->update(['acknowledged_at' => '2026-03-16 18:00:00']);

    $this->get(route('tenant.vacations.statements.show', [$this->tenant, $this->statement]))
        ->assertInertia(fn (Assert $page) => $page->where('sheet.acknowledgedOn', '16 de marzo de 2026'));
});

it('lets a viewer read it', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.vacations.statements.show', [$this->tenant, $this->statement]))->assertOk();
});

it('answers 404 for a statement of another company or one that does not exist', function (): void {
    $other = Tenant::factory()->create();
    $hr = memberOf($other, TenantRole::Hr);

    $this->actingAs($hr)->get(route('tenant.vacations.statements.show', [$other, $this->statement]))->assertNotFound();
    $this->actingAs($hr)->get(route('tenant.vacations.statements.show', [$other, '00000000-0000-4000-8000-000000000000']))->assertNotFound();
});

it('is linked from the record of the person', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.vacations.employees.show', [$this->tenant, $this->ana]))
        ->assertInertia(fn (Assert $page) => $page->where('statements.0.id', $this->statement->id));
});
