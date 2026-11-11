<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\ComplianceRoutes;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

it('gives each role the org chart permissions of the spec', function (TenantRole $role, bool $view, bool $manage): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('view-org-chart'))->toBe($view)
        ->and(Gate::allows('manage-org-chart'))->toBe($manage);
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
    'committee' => [TenantRole::Committee, false, false],
    'complaints' => [TenantRole::Complaints, false, false],
]);

it('tells the sidebar that a viewer may open the org chart and not change it', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, TenantRole::Viewer))->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.can.view_org_chart', true)
            ->where('tenant.can.manage_org_chart', false));
});

it('hides the org chart from a union account: its roster has no hierarchy', function (): void {
    expect(ComplianceRoutes::patterns())->toContain('tenant.org-chart.*');
});
