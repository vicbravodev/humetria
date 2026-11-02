<?php

declare(strict_types=1);

use App\Modules\Telework\Contracts\Committee;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Support\CommitteeGate;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Middleware\BlockSuspendedTenant;
use App\Modules\Tenancy\Http\Middleware\LimitCommitteeToTelework;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(LimitCommitteeToTelework::class, CommitteeGate::class);

it('gives each role the Ley Silla permissions of the spec', function (TenantRole $role, bool $view, bool $manage, bool $assess): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('view-seating'))->toBe($view)
        ->and(Gate::allows('manage-seating'))->toBe($manage)
        ->and(Gate::allows('assess-seating'))->toBe($assess);
})->with([
    'owner' => [TenantRole::Owner, true, true, true],
    'hr' => [TenantRole::Hr, true, true, true],
    'viewer' => [TenantRole::Viewer, true, false, false],
    'committee' => [TenantRole::Committee, true, false, true],
]);

it('lets the committee reach tenant.seating routes and still 403 on others', function (): void {
    $tenant = Tenant::factory()->create();
    $committee = memberOf($tenant, TenantRole::Committee);

    Route::middleware(['web', 'auth', 'verified', ResolveTenant::class, BlockSuspendedTenant::class, LimitCommitteeToTelework::class])
        ->get('admin/{tenant:slug}/seating-probe', fn () => 'ok')->name('tenant.seating.probe');
    Route::getRoutes()->refreshNameLookups();

    $this->actingAs($committee)->get(route('tenant.seating.probe', $tenant))->assertOk();
    $this->actingAs($committee)->get(route('tenant.employees.index', $tenant))->assertForbidden();
});

it('exposes the three flags in the tenant props', function (TenantRole $role, bool $view, bool $manage, bool $assess): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, $role))->get(route('tenant.telework.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.can.view_seating', $view)
            ->where('tenant.can.manage_seating', $manage)
            ->where('tenant.can.assess_seating', $assess));
})->with([
    'owner' => [TenantRole::Owner, true, true, true],
    'viewer' => [TenantRole::Viewer, true, false, false],
    'committee' => [TenantRole::Committee, true, false, true],
]);

it('answers Committee::isMember and workCenterIds like CommitteeGate', function (): void {
    $tenant = Tenant::factory()->create();
    $cora = memberOf($tenant, TenantRole::Committee);
    $center = WorkCenter::factory()->for($tenant)->create();
    $other = WorkCenter::factory()->for($tenant)->create();
    CommitteeAssignment::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id, 'user_id' => $cora->id]);
    workIn($tenant);
    $committee = resolve(Committee::class);

    expect($committee)->toBeInstanceOf(CommitteeGate::class)
        ->and($committee->isMember($cora->id, $center->id))->toBeTrue()
        ->and($committee->isMember($cora->id, $other->id))->toBeFalse()
        ->and($committee->workCenterIds($cora->id))->toBe([$center->id])
        ->and($committee->userIdsOf($center->id))->toBe([$cora->id])
        ->and($committee->userIdsOf($other->id))->toBe([]);
});
