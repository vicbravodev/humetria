<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\CompanyRoleSummary;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Http\Middleware\ResolveTenant;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyMembers;
use App\Modules\Tenancy\Support\CompanyRoles;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CompanyRoles::class, CompanyMembers::class, ResolveTenant::class);

// A person whose role is one of the company's own is a member like any other: listed, counted by permission and let in.
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
});

/** A member of the company whose role is one of its own, labelled `$label`, with `$permissions`. */
function ownRoleMember(Tenant $tenant, User $owner, array $permissions, string $label = 'Vacaciones'): User
{
    $role = app(CreateCompanyRole::class)->handle($tenant, $label, $permissions, null, $owner);
    $person = User::factory()->create(['name' => 'Persona con rol propio']);
    TenantMembership::factory()->for($tenant)->for($person)->viewer()->create();
    resolve(CompanyRoles::class)->assign($person, $tenant, $role);

    return $person;
}

it('lists a person with a role of the company\'s own, by its label, and counts their permissions', function (): void {
    $person = ownRoleMember($this->tenant, $this->owner, ['view-vacations', 'handle-complaints'], 'Atención externa');
    $role = resolve(CompanyRoles::class)->roleOf($person, $this->tenant);
    workIn($this->tenant);
    $members = resolve(Members::class);

    expect($members->find($person->id))->toEqual(new MemberSummary($person->id, 'Persona con rol propio', $person->email, new CompanyRoleSummary($role?->name ?? '', 'Atención externa', false)))
        ->and(array_map(fn (MemberSummary $member): int => $member->userId, $members->holding('handle-complaints')))->toContain($person->id)
        ->and($members->find($this->owner->id)?->role)->toEqual(new CompanyRoleSummary('owner', 'Administra todo', true));
});

it('lets a person with a role of the company\'s own into the company, on what they may see', function (): void {
    $person = ownRoleMember($this->tenant, $this->owner, ['view-vacations']);
    $role = resolve(CompanyRoles::class)->roleOf($person, $this->tenant);

    $this->actingAs($person)->followingRedirects()->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('leave/index')
            ->where('tenant.role', $role?->name)
            ->where('tenant.role_label', 'Vacaciones'));
});

it('tells a person with a role of the company\'s own that opens nothing whom to ask', function (): void {
    $person = ownRoleMember($this->tenant, $this->owner, ['use-assistant']);

    $this->actingAs($person)->followingRedirects()->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('tenancy/no-access'));
});

it('still keeps out a person of another company with a role of their own', function (): void {
    $other = Tenant::factory()->create();
    $person = ownRoleMember($other, ownerOf($other), ['view-vacations']);

    $this->actingAs($person)->get(route('tenant.vacations.index', $this->tenant))->assertNotFound();
});
