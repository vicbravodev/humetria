<?php

declare(strict_types=1);

use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['slug' => 'acme']));
});

it('gives each role the pulse permissions of the spec', function (TenantRole $role, bool $view, bool $manage): void {
    $this->actingAs(memberOf($this->tenant, $role));

    expect(Gate::allows('view-pulse'))->toBe($view)->and(Gate::allows('manage-pulse'))->toBe($manage);
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
    'committee' => [TenantRole::Committee, false, false],
    'complaints' => [TenantRole::Complaints, false, false],
]);

it('tells the sidebar who may open the pulse and who may change it', function (TenantRole $role, bool $view, bool $manage): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.dashboard', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.can.view_pulse', $view)->where('tenant.can.manage_pulse', $manage));
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, true, false],
]);

it('lives under the pulse of the company', function (): void {
    expect(route('tenant.pulse.index', $this->tenant, absolute: false))->toBe('/admin/acme/pulse');
});

it('opens the pulse home to whoever views it', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.pulse.index', $this->tenant))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('engagement/index')->where('kind', 'pulse'));
});

it('tells the pages which kind of survey they show', function (): void {
    $pulse = Survey::factory()->pulse()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.pulse.show', [$this->tenant, $pulse]))->assertInertia(fn (Assert $page) => $page->where('kind', 'pulse'));
    $this->get(route('tenant.climate.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('kind', 'climate'));
});

it('keeps out a role that views climate but not pulse', function (): void {
    $user = memberOf($this->tenant, TenantRole::Viewer);
    $this->actingAs($user);
    // Only the climate permission is granted: the pulse is another door.
    DB::table('role_has_permissions')->whereIn('permission_id', DB::table('permissions')->where('name', 'view-pulse')->select('id'))->delete();
    resolve(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->get(route('tenant.climate.index', $this->tenant))->assertOk();
    $this->get(route('tenant.pulse.index', $this->tenant))->assertForbidden();
});

it('does not open a pulse by the climate URL, nor a climate survey by the pulse URL', function (): void {
    $pulse = Survey::factory()->pulse()->create(['tenant_id' => $this->tenant->id]);
    $climate = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs(memberOf($this->tenant, TenantRole::Hr));

    $this->get(route('tenant.climate.show', [$this->tenant, $pulse]))->assertNotFound();
    $this->get(route('tenant.pulse.show', [$this->tenant, $climate]))->assertNotFound();
    $this->get(route('tenant.pulse.show', [$this->tenant, $pulse]))->assertOk();
});

it('answers 404 for a pulse of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Survey::factory()->pulse()->create(['tenant_id' => $other->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Hr))->get(route('tenant.pulse.show', [$this->tenant, $foreign]))->assertNotFound();
});
