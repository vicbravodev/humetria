<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;

it('gives view and manage to owner and hr only', function (TenantRole $role, bool $allowed): void {
    $tenant = Tenant::factory()->create();
    $this->actingAs(memberOf($tenant, $role));
    workIn($tenant);

    expect(Gate::allows('view-compensation'))->toBe($allowed)
        ->and(Gate::allows('manage-compensation'))->toBe($allowed);
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
    'committee' => [TenantRole::Committee, false],
]);

it('opens the Compensación page to owner and hr, in a company and in a union, and closes it to the other roles', function (bool $union, TenantRole $role, int $status): void {
    $tenant = $union ? Tenant::factory()->union()->create() : Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, $role))
        ->get(route('tenant.compensation.index', $tenant))
        ->assertStatus($status);
})->with([
    'company owner' => [false, TenantRole::Owner, 200],
    'company hr' => [false, TenantRole::Hr, 200],
    'company viewer' => [false, TenantRole::Viewer, 403],
    'company committee' => [false, TenantRole::Committee, 403],
    'union owner' => [true, TenantRole::Owner, 200],
    'union hr' => [true, TenantRole::Hr, 200],
    'union viewer' => [true, TenantRole::Viewer, 403],
    'union committee' => [true, TenantRole::Committee, 403],
]);

it('exposes the two flags in the tenant props', function (bool $union, TenantRole $role, bool $allowed): void {
    $tenant = $union ? Tenant::factory()->union()->create() : Tenant::factory()->create();
    $page = $union ? 'tenant.employees.index' : 'tenant.telework.index';

    $this->actingAs(memberOf($tenant, $role))->get(route($page, $tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.can.view_compensation', $allowed)
            ->where('tenant.can.manage_compensation', $allowed));
})->with([
    'company owner' => [false, TenantRole::Owner, true],
    'company hr' => [false, TenantRole::Hr, true],
    'company viewer' => [false, TenantRole::Viewer, false],
    'company committee' => [false, TenantRole::Committee, false],
    'union owner' => [true, TenantRole::Owner, true],
    'union hr' => [true, TenantRole::Hr, true],
]);

it('sends the panel of a union to Compensación', function (): void {
    $union = Tenant::factory()->union()->create();

    $this->actingAs(memberOf($union, TenantRole::Owner))
        ->get(route('tenant.dashboard', $union))
        ->assertRedirect(route('tenant.compensation.index', $union));
});

it('blocks Compensación of a suspended account like the other tools', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

    $this->actingAs(ownerOf($tenant))
        ->get(route('tenant.compensation.index', $tenant))
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/blocked'));
});

it('keeps another account out: a member of A gets 404 on the Compensación of B', function (): void {
    $other = Tenant::factory()->create();

    $this->actingAs(ownerOf(Tenant::factory()->create()))
        ->get(route('tenant.compensation.index', $other))
        ->assertNotFound();
});
