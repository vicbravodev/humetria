<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\WorkCenterController;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

mutates(WorkCenterController::class, ResolveTenant::class, TenantContext::class);

it('shares the role and what it allows with every page of the company', function (TenantRole $role, bool $organization, bool $members, bool $campaigns): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, $role))
        ->get(route('tenant.work-centers.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.role', $role->value)
            ->where('tenant.role_label', $role->label())
            ->where('tenant.can', ['manage_organization' => $organization, 'manage_members' => $members, 'manage_campaigns' => $campaigns, 'view_trauma_screenings' => $campaigns, 'manage_telework' => $campaigns, 'review_workplaces' => $campaigns]));
})->with([
    'owner' => [TenantRole::Owner, true, true, true],
    'hr' => [TenantRole::Hr, true, false, true],
    'viewer' => [TenantRole::Viewer, false, false, false],
]);

it('asks Spatie what the current person may do in the company', function (TenantRole $role, bool $organization, bool $members, bool $campaigns): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('manage-organization'))->toBe($organization)
        ->and(Gate::allows('manage-members'))->toBe($members)
        ->and(Gate::allows('manage-campaigns'))->toBe($campaigns)
        // Who runs the evaluations also channels whoever needs care (num. 5.5): the Guía I list.
        ->and(Gate::allows('view-trauma-screenings'))->toBe($campaigns);
})->with([
    'owner' => [TenantRole::Owner, true, true, true],
    'hr' => [TenantRole::Hr, true, false, true],
    'viewer' => [TenantRole::Viewer, false, false, false],
]);

it('lets a viewer see the work centers', function (): void {
    $workCenter = WorkCenter::factory()->create(['name' => 'Planta Norte']);

    $this->actingAs(memberOf($workCenter->tenant, TenantRole::Viewer))
        ->get(route('tenant.work-centers.index', $workCenter->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('workCenters.data.0.name', 'Planta Norte'));
});

it('does not let a viewer change a work center', function (): void {
    $workCenter = WorkCenter::factory()->create(['name' => 'Planta Norte']);
    $archived = WorkCenter::factory()->for($workCenter->tenant)->archived()->create();
    $viewer = memberOf($workCenter->tenant, TenantRole::Viewer);
    $tenant = $workCenter->tenant;

    $this->actingAs($viewer)->post(route('tenant.work-centers.store', $tenant), workCenterForm(['name' => 'Nuevo']))->assertForbidden();
    $this->actingAs($viewer)->put(route('tenant.work-centers.update', [$tenant, $workCenter->id]), workCenterForm(['name' => 'Otro']))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.work-centers.archive', [$tenant, $workCenter->id]))->assertForbidden();
    $this->actingAs($viewer)->post(route('tenant.work-centers.restore', [$tenant, $archived->id]))->assertForbidden();

    expect(WorkCenter::query()->withoutGlobalScopes()->count())->toBe(2)
        ->and(reloaded($workCenter)->name)->toBe('Planta Norte')
        ->and(reloaded($workCenter)->archived_at)->toBeNull()
        ->and(reloaded($archived)->archived_at)->not->toBeNull();
});

it('lets hr add, correct and archive work centers', function (): void {
    $tenant = Tenant::factory()->create();
    $hr = memberOf($tenant, TenantRole::Hr);

    $this->actingAs($hr)->post(route('tenant.work-centers.store', $tenant), workCenterForm())->assertSessionHasNoErrors();
    $id = WorkCenter::query()->withoutGlobalScopes()->sole()->id;
    $this->actingAs($hr)->put(route('tenant.work-centers.update', [$tenant, $id]), workCenterForm(['name' => 'Planta Nueva']))->assertSessionHasNoErrors();
    $this->actingAs($hr)->post(route('tenant.work-centers.archive', [$tenant, $id]))->assertRedirect();

    $workCenter = WorkCenter::query()->withoutGlobalScopes()->sole();

    expect($workCenter->name)->toBe('Planta Nueva')
        ->and($workCenter->archived_at)->not->toBeNull();
});
