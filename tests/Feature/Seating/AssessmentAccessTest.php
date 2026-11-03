<?php

declare(strict_types=1);

use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\RiskAssessment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->other = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    $this->signed = RiskAssessment::factory()->signed()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
});

it('gives the viewer no names, scores or health: a 403 on the list and on every assessment', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

    $this->get(route('tenant.seating.assessments.index', [$this->tenant, $this->center->id]))->assertForbidden();
    $this->get(route('tenant.seating.assessments.show', [$this->tenant, $this->signed->id]))->assertForbidden();
    $this->post(route('tenant.seating.assessments.store', [$this->tenant, $this->center->id]), ['employee_id' => $this->ana->id])->assertForbidden();
});

it('lets owner and hr read the assessments of any center, assigned or not', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role));

    $this->get(route('tenant.seating.assessments.index', [$this->tenant, $this->center->id]))->assertOk();
    $this->get(route('tenant.seating.assessments.show', [$this->tenant, $this->signed->id]))->assertOk();
    $this->get(route('tenant.seating.assessments.index', [$this->tenant, $this->other->id]))->assertOk();
})->with([TenantRole::Owner, TenantRole::Hr]);

it('answers 404 to a committee member for a center it does not sit on', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->other->id, 'user_id' => $cora->id]);
    $this->actingAs($cora);

    $this->get(route('tenant.seating.assessments.index', [$this->tenant, $this->center->id]))->assertNotFound();
    $this->get(route('tenant.seating.assessments.show', [$this->tenant, $this->signed->id]))->assertNotFound();
});

it('tells the page whether the person can sign: only the assigned ones', function (TenantRole $role, bool $assigned, bool $canSign): void {
    $draft = RiskAssessment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id, 'work_center_id' => $this->center->id]);
    $user = memberOf($this->tenant, $role);
    if ($assigned) {
        CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $user->id]);
    }

    $this->actingAs($user)->get(route('tenant.seating.assessments.show', [$this->tenant, $draft->id]))
        ->assertInertia(fn (Assert $page) => $page->component('seating/assessment')->where('canSign', $canSign));
})->with([
    'owner not assigned' => [TenantRole::Owner, false, false],
    'owner assigned' => [TenantRole::Owner, true, true],
    'committee assigned' => [TenantRole::Committee, true, true],
]);
