<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\AcceptInvitation;
use App\Modules\Tenancy\Actions\ChangeMemberRole;
use App\Modules\Tenancy\Actions\RemoveMember;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ChangeMemberRole::class, RemoveMember::class, AcceptInvitation::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->ownerMembership = TenantMembership::query()->where('user_id', $this->owner->id)->sole();
});

function membershipOf(Tenant $tenant, TenantRole $role): TenantMembership
{
    return TenantMembership::factory()->for($tenant)->role($role)->create();
}

function roleIn(Tenant $tenant, User|int $user): ?TenantRole
{
    return resolve(CompanyRoles::class)->of($user instanceof User ? $user : User::query()->findOrFail($user), $tenant);
}

it('lives at /admin/{slug}/members/{id}', function (): void {
    expect(route('tenant.members.update', ['tenant' => 'acme', 'member' => 7], absolute: false))->toBe('/admin/acme/members/7');
});

it('changes the role of a person, and records who did it', function (): void {
    $member = membershipOf($this->tenant, TenantRole::Hr);
    Log::spy();

    $this->actingAs($this->owner)
        ->put(route('tenant.members.update', [$this->tenant, $member->id]), ['role' => 'viewer'])
        ->assertRedirect(route('tenant.members.index', $this->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Rol actualizado.']]);

    expect(roleIn($this->tenant, $member->user_id))->toBe(TenantRole::Viewer);

    $change = ['member_user_id' => $member->user_id, 'from' => 'hr', 'to' => 'viewer'];
    Log::shouldHaveReceived('info')->once()->with('tenant_member.role_changed', ['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, ...$change]);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('tenant_member.role_changed')
        ->and($entry->subject_id)->toBe((string) $member->id)
        ->and($entry->metadata)->toEqual($change);
});

it('records nothing when the role stays the same', function (): void {
    $member = membershipOf($this->tenant, TenantRole::Hr);

    $this->actingAs($this->owner)->put(route('tenant.members.update', [$this->tenant, $member->id]), ['role' => 'hr'])->assertRedirect();

    expect(AuditEntry::query()->count())->toBe(0);
});

it('only moves between hr and viewer', function (string $role): void {
    $member = membershipOf($this->tenant, TenantRole::Hr);

    $this->actingAs($this->owner)
        ->put(route('tenant.members.update', [$this->tenant, $member->id]), ['role' => $role])
        ->assertSessionHasErrors(['role' => 'Elige RH o Consulta.']);

    expect(roleIn($this->tenant, $member->user_id))->toBe(TenantRole::Hr);
})->with(['owner', 'admin', '']);

it('removes the access of a person, keeping their account', function (): void {
    $member = membershipOf($this->tenant, TenantRole::Viewer);
    Log::spy();

    $this->actingAs($this->owner)
        ->delete(route('tenant.members.destroy', [$this->tenant, $member->id]))
        ->assertRedirect(route('tenant.members.index', $this->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Acceso quitado.']]);

    expect(TenantMembership::query()->find($member->id))->toBeNull()
        ->and(roleIn($this->tenant, $member->user_id))->toBeNull()
        ->and(User::query()->find($member->user_id))->not->toBeNull();

    Log::shouldHaveReceived('info')->once()->with('tenant_member.removed', ['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id, 'member_user_id' => $member->user_id, 'role' => 'viewer']);

    expect(AuditEntry::query()->sole()->only(['event', 'metadata']))->toEqual([
        'event' => 'tenant_member.removed',
        'metadata' => ['member_user_id' => $member->user_id, 'role' => 'viewer'],
    ]);
});

it('never changes nor removes the owner here, so nobody removes themself', function (): void {
    $this->actingAs($this->owner)->put(route('tenant.members.update', [$this->tenant, $this->ownerMembership->id]), ['role' => 'hr'])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('tenant.members.destroy', [$this->tenant, $this->ownerMembership->id]))->assertNotFound();

    expect(roleIn($this->tenant, $this->owner))->toBe(TenantRole::Owner);
});

it('answers 404 to a person of another company', function (): void {
    $foreign = membershipOf(Tenant::factory()->create(), TenantRole::Hr);

    $this->actingAs($this->owner)->put(route('tenant.members.update', [$this->tenant, $foreign->id]), ['role' => 'viewer'])->assertNotFound();
    $this->actingAs($this->owner)->delete(route('tenant.members.destroy', [$this->tenant, $foreign->id]))->assertNotFound();

    expect(roleIn($foreign->tenant()->sole(), $foreign->user_id))->toBe(TenantRole::Hr);
});

it('does not let hr or a viewer change access', function (TenantRole $role): void {
    $member = membershipOf($this->tenant, TenantRole::Viewer);
    $actor = memberOf($this->tenant, $role);

    $this->actingAs($actor)->put(route('tenant.members.update', [$this->tenant, $member->id]), ['role' => 'hr'])->assertForbidden();
    $this->actingAs($actor)->delete(route('tenant.members.destroy', [$this->tenant, $member->id]))->assertForbidden();

    expect(roleIn($this->tenant, $member->user_id))->toBe(TenantRole::Viewer);
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('joins the company with the invited role, without starting a trial', function (TenantRole $role): void {
    $token = Str::random(64);
    TenantInvitation::factory()->for($this->tenant)->role($role)->withToken($token)->create(['email' => 'ana@acme.mx']);

    $this->post(route('invitations.accept', $token), ['name' => 'Ana', 'password' => 'Una-Clave-Larga-123', 'password_confirmation' => 'Una-Clave-Larga-123'])
        ->assertRedirect('/admin');

    $user = User::query()->where('email', 'ana@acme.mx')->sole();

    expect(roleIn($this->tenant, $user))->toBe($role)
        ->and(TenantMembership::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($this->tenant->refresh()->trial_started_at)->toBeNull()
        ->and($this->ownerMembership->refresh()->user_id)->toBe($this->owner->id);
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('adds an existing account with the invited role', function (): void {
    $token = Str::random(64);
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    $invitation = TenantInvitation::factory()->for($this->tenant)->role(TenantRole::Viewer)->withToken($token)->create(['email' => 'ana@acme.mx']);

    resolve(AcceptInvitation::class)->handle($invitation, $ana);

    expect(roleIn($this->tenant, $ana))->toBe(TenantRole::Viewer);
});

it('keeps the role of someone who got access in the meantime', function (): void {
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    TenantMembership::factory()->for($this->tenant)->for($ana)->hr()->create();
    $invitation = TenantInvitation::factory()->for($this->tenant)->role(TenantRole::Viewer)->create(['email' => 'ana@acme.mx']);

    resolve(AcceptInvitation::class)->handle($invitation, $ana);

    expect(roleIn($this->tenant, $ana))->toBe(TenantRole::Hr)
        ->and($invitation->refresh()->accepted_at)->not->toBeNull();
});

it('makes a member the owner when they accept the owner invitation, leaving one membership', function (): void {
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    TenantMembership::factory()->for($this->tenant)->for($ana)->hr()->create();
    $invitation = TenantInvitation::factory()->for($this->tenant)->create(['email' => 'ana@acme.mx']);

    resolve(AcceptInvitation::class)->handle($invitation, $ana);

    expect(roleIn($this->tenant, $ana))->toBe(TenantRole::Owner)
        ->and(TenantMembership::query()->where('tenant_id', $this->tenant->id)->where('user_id', $ana->id)->count())->toBe(1)
        ->and(TenantMembership::query()->where('user_id', $this->owner->id)->exists())->toBeFalse();
});

it('says on the link page which role the invitation gives', function (TenantRole $role, string $title): void {
    $token = Str::random(64);
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme']))->role($role)->withToken($token)->create();

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('invited_as', $title));
})->with([
    'owner' => [TenantRole::Owner, 'Te invitaron como owner de Acme'],
    'hr' => [TenantRole::Hr, 'Te invitaron como RH de Acme'],
    'viewer' => [TenantRole::Viewer, 'Te invitaron a consultar Acme'],
]);

it('says nothing about a role on an invalid link', function (): void {
    $this->get(route('invitations.show', Str::random(64)))
        ->assertInertia(fn (Assert $page) => $page->where('invited_as', null));
});

it('says on the link page who sent the invitation and the role it gives', function (): void {
    $token = Str::random(64);
    $olga = User::factory()->create(['name' => 'Olga Owner']);
    TenantInvitation::factory()->role(TenantRole::Hr)->withToken($token)->create(['invited_by' => $olga->id]);

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('inviter', 'Olga Owner')
            ->where('role', 'hr')
            ->where('role_label', 'RH'));
});

it('names no sender when the invitation has none, nor anything on an invalid link', function (): void {
    $token = Str::random(64);
    TenantInvitation::factory()->role(TenantRole::Viewer)->withToken($token)->create(['invited_by' => null]);

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('inviter', null)->where('role', 'viewer')->where('role_label', 'Consulta'));

    $this->get(route('invitations.show', Str::random(64)))
        ->assertInertia(fn (Assert $page) => $page->where('inviter', null)->where('role', null)->where('role_label', null));
});
