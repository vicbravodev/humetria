<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\AcceptInvitation;
use App\Modules\Tenancy\Actions\CreateCompanyRole;
use App\Modules\Tenancy\Actions\DeleteCompanyRole;
use App\Modules\Tenancy\Actions\InviteMember;
use App\Modules\Tenancy\Actions\UpdateCompanyRole;
use App\Modules\Tenancy\Data\MemberInvitationData;
use App\Modules\Tenancy\Data\NewAccountData;
use App\Modules\Tenancy\Exceptions\CompanyRoleInUse;
use App\Modules\Tenancy\Exceptions\RoleNotAllowedInAccount;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Models\TenantModule;
use App\Modules\Tenancy\Notifications\InvitationEmail;
use App\Modules\Tenancy\Support\CompanyRoles;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

mutates(InviteMember::class, AcceptInvitation::class, InvitationEmail::class);

beforeEach(function (): void {
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->owner = User::factory()->create(['name' => 'Olga Owner']);
    TenantMembership::factory()->for($this->tenant)->for($this->owner)->create();
    $this->role = app(CreateCompanyRole::class)->handle($this->tenant, 'Auditoría', ['view-dashboard', 'view-work-centers'], null, $this->owner);
    $this->invite = fn (array $payload = []) => $this->actingAs($this->owner)->post(route('tenant.members.invitations.store', $this->tenant), [
        'name' => 'Ana Pérez', 'email' => 'ana@acme.mx', 'role' => $this->role->name, 'permissions' => ['view-vacations'], ...$payload,
    ]);
});

it('invites with a role of the company and extra permissions, and says which ones in the log and the audit trail', function (): void {
    Log::spy();

    ($this->invite)(['permissions' => ['view-vacations', 'view-compensation']])->assertSessionHasNoErrors()->assertRedirect(route('tenant.members.index', $this->tenant));

    $invitation = TenantInvitation::query()->sole();
    expect($invitation->role?->is($this->role))->toBeTrue()
        ->and($invitation->extra_permissions)->toBe(['view-vacations', 'view-compensation']);
    Log::shouldHaveReceived('info')->with('tenant_invitation.sent', ['tenant_id' => $this->tenant->id, 'invitation_id' => $invitation->id, 'user_id' => $this->owner->id, 'role' => $this->role->name]);
    expect(AuditEntry::query()->where('event', 'tenant_invitation.sent')->sole()->metadata)->toEqual([
        'tenant_id' => $this->tenant->id, 'role' => $this->role->name, 'permissions' => ['view-vacations', 'view-compensation'], 'sensitive' => true,
    ]);
});

it('invites with a template and no extras as before', function (): void {
    ($this->invite)(['role' => 'viewer', 'permissions' => []])->assertSessionHasNoErrors();

    $invitation = TenantInvitation::query()->sole();
    expect($invitation->role?->name)->toBe('viewer')->and($invitation->extra_permissions)->toBe([]);
});

it('emails the label of the role', function (): void {
    ($this->invite)()->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(InvitationEmail::class, function (InvitationEmail $email, array $channels, AnonymousNotifiable $notifiable): bool {
        $html = (string) $email->toMail($notifiable)->render();

        return str_contains($html, 'Entrarás con el rol <strong')
            && str_contains($html, 'Auditoría</strong>: podrás entrar a Acme con los permisos que te asignaron.');
    });
});

it('says on the link page the label of the role', function (): void {
    TenantInvitation::factory()->for($this->tenant)->role($this->role)->withToken($token = str_repeat('a', 64))->create();

    $this->get(route('invitations.show', $token))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('invited_as', 'Te invitaron como Auditoría de Acme')
        ->where('role', $this->role->name)
        ->where('role_label', 'Auditoría'));
});

it('gives the role and the extras when the invitation is accepted, as the role is at that moment', function (): void {
    ($this->invite)()->assertSessionHasNoErrors();
    app(UpdateCompanyRole::class)->handle($this->role, 'Auditoría', ['view-dashboard'], $this->owner);

    $ana = app(AcceptInvitation::class)->handle(TenantInvitation::query()->sole(), new NewAccountData('Ana Pérez', 'secreto-largo-123'));

    $roles = resolve(CompanyRoles::class);
    expect($roles->roleOf($ana, $this->tenant)?->is($this->role))->toBeTrue()
        ->and($roles->effectivePermissions($ana, $this->tenant))->toBe(['view-dashboard' => 'role', 'view-vacations' => 'direct'])
        ->and(AuditEntry::query()->where('event', 'tenant_invitation.accepted')->sole()->metadata)->toEqual(['permissions' => ['view-vacations']]);
});

it('leaves someone who already had access with what they have', function (): void {
    ($this->invite)()->assertSessionHasNoErrors();
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    TenantMembership::factory()->for($this->tenant)->for($ana)->viewer()->create();

    app(AcceptInvitation::class)->handle(TenantInvitation::query()->sole(), $ana);

    $roles = resolve(CompanyRoles::class);
    expect($roles->roleOf($ana, $this->tenant)?->name)->toBe('viewer')
        ->and($roles->directPermissionsOf($ana, $this->tenant))->toBe([]);
});

it('rejects a role of another company, the owner and a role that does not exist', function (string $role): void {
    ($this->invite)(['role' => $role])->assertSessionHasErrors('role');

    expect(TenantInvitation::query()->exists())->toBeFalse();
})->with([
    'another company' => fn (): string => app(CreateCompanyRole::class)->handle(Tenant::factory()->create(), 'De otra', ['view-dashboard'], null, User::factory()->create())->name,
    'owner' => ['owner'],
    'unknown' => ['custom_nope'],
]);

it('stops a role of another company in the action too', function (): void {
    $theirs = app(CreateCompanyRole::class)->handle(Tenant::factory()->create(), 'De otra', ['view-dashboard'], null, $this->owner);

    expect(fn () => app(InviteMember::class)->handle($this->tenant, new MemberInvitationData('Ana', 'ana@acme.mx', $theirs->name, []), $this->owner))
        ->toThrow(RoleNotAllowedInAccount::class)
        ->and(fn () => app(InviteMember::class)->handle($this->tenant, new MemberInvitationData('Ana', 'ana@acme.mx', 'owner', []), $this->owner))
        ->toThrow(RoleNotAllowedInAccount::class);
    expect(TenantInvitation::query()->exists())->toBeFalse();
});

it('only takes extra permissions the company can hand out', function (array $permissions): void {
    TenantModule::factory()->for($this->tenant)->create(['module' => 'vacations', 'source' => 'revoke', 'reason' => 'Prueba']);

    ($this->invite)(['permissions' => $permissions])->assertSessionHasErrors(['permissions.0' => 'El permiso adicional seleccionado no es válido.']);

    expect(TenantInvitation::query()->exists())->toBeFalse();
})->with([
    'owner only' => [['manage-members']],
    'unknown' => [['fly-to-moon']],
    'not contracted' => [['view-vacations']],
]);

it('lets a union invite only with roles and extras of compensation', function (): void {
    $union = Tenant::factory()->union()->create();
    $owner = ownerOf($union);
    $compensation = app(CreateCompanyRole::class)->handle($union, 'Tesorería', ['view-compensation'], null, $owner);
    // CreateCompanyRole refuses it: built by hand to reach the action's own check.
    $compliance = Role::query()->create(['name' => 'custom_'.Str::uuid(), 'guard_name' => 'web', 'tenant_id' => $union->id, 'label' => 'Cumplimiento']);
    $compliance->givePermissionTo('view-dashboard');
    $invite = fn (string $role, array $permissions = []) => app(InviteMember::class)->handle($union, new MemberInvitationData('Ana', 'ana@sindicato.mx', $role, $permissions), $owner);

    expect(fn () => $invite($compliance->name))->toThrow(RoleNotAllowedInAccount::class)
        ->and(fn () => $invite($compensation->name, ['view-dashboard']))->toThrow(InvalidArgumentException::class)
        ->and($invite($compensation->name, ['manage-compensation'])->extra_permissions)->toBe(['manage-compensation']);
});

it('keeps a role with an open invitation, and lets it go once the invitation is used', function (): void {
    ($this->invite)()->assertSessionHasNoErrors();

    expect(fn () => app(DeleteCompanyRole::class)->handle($this->role, $this->owner))->toThrow(CompanyRoleInUse::class);

    TenantInvitation::query()->sole()->update(['cancelled_at' => now()]);
    app(DeleteCompanyRole::class)->handle($this->role, $this->owner);

    expect(Role::query()->whereKey($this->role->id)->exists())->toBeFalse()
        ->and(TenantInvitation::query()->sole()->role)->toBeNull();
});
