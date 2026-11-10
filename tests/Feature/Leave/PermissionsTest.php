<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

it('gives each role the vacations permissions of the spec', function (TenantRole $role, bool $view, bool $manage): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('view-vacations'))->toBe($view)
        ->and(Gate::allows('manage-vacations'))->toBe($manage);
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
    'committee' => [TenantRole::Committee, false, false],
    'complaints' => [TenantRole::Complaints, false, false],
]);

it('tells the sidebar who may open vacations and who may change them', function (TenantRole $role, bool $view, bool $manage): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, $role))->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.can.view_vacations', $view)
            ->where('tenant.can.manage_vacations', $manage));
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
]);

it('reads the shared knobs of the module from its config', function (): void {
    expect(config('leave.escalation_business_days'))->toBe(3)
        ->and(config('leave.enjoy_window_warning_days'))->toBe(60)
        ->and(config('leave.import_max_rows'))->toBe(2000);
});
