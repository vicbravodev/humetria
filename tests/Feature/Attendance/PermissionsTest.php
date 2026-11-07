<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

it('gives each role the attendance permissions of the spec', function (TenantRole $role, bool $view, bool $manage): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('view-attendance'))->toBe($view)
        ->and(Gate::allows('manage-attendance'))->toBe($manage);
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
    'committee' => [TenantRole::Committee, false, false],
]);

it('tells the sidebar who may open the registro', function (TenantRole $role, bool $view): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, $role))->get(route('tenant.telework.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.can.view_attendance', $view));
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, true],
    'committee' => [TenantRole::Committee, false],
]);
