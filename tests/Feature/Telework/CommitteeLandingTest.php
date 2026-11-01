<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
});

it('sends a committee-only user from /admin and from the panel to NOM-037, not to a 403', function (): void {
    $cora = memberOf($this->tenant, TenantRole::Committee);

    $this->actingAs($cora)->get('/admin')->assertRedirect(route('tenant.telework.index', $this->tenant));
    $this->actingAs($cora)->get(route('tenant.dashboard', $this->tenant))->assertRedirect(route('tenant.telework.index', $this->tenant));
});

it('keeps sending the other roles to the panel', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);

    $this->actingAs($user)->get('/admin')->assertRedirect(route('tenant.dashboard', $this->tenant));
    $this->actingAs($user)->get(route('tenant.dashboard', $this->tenant))->assertOk();
})->with([TenantRole::Owner, TenantRole::Hr, TenantRole::Viewer]);

it('hides the NOM-035 shared data from the committee and keeps it for the others', function (): void {
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.telework.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('inCourse', null));
    $this->actingAs(memberOf($this->tenant, TenantRole::Owner))->get(route('tenant.telework.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->has('inCourse.count'));
});

it('tells the page what each role may do in NOM-037', function (TenantRole $role, bool $manage, bool $review): void {
    $this->actingAs(memberOf($this->tenant, $role))->get(route('tenant.telework.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('tenant.can.manage_telework', $manage)->where('tenant.can.review_workplaces', $review));
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'viewer' => [TenantRole::Viewer, false, false],
    'committee' => [TenantRole::Committee, false, true],
]);
