<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\InviteMember;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Controllers\MemberController;
use App\Modules\Tenancy\Http\Requests\InviteMemberRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Notifications\InvitationEmail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(MemberController::class, InviteMember::class, InviteMemberRequest::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-29 18:00 UTC');
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->owner = User::factory()->create(['name' => 'Olga Owner', 'email' => 'olga@acme.mx']);
    TenantMembership::factory()->for($this->tenant)->for($this->owner)->create();
});

it('lives at /admin/{slug}/members', function (): void {
    expect(route('tenant.members.index', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/members')
        ->and(route('tenant.members.invitations.store', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/members/invitations');
});

it('lists the people with access, the owner first, and the open invitations', function (): void {
    $hr = User::factory()->create(['name' => 'Beto RH', 'email' => 'beto@acme.mx']);
    TenantMembership::factory()->for($this->tenant)->for($hr)->hr()->create();
    TenantMembership::factory()->for($this->tenant)->for(User::factory()->create(['name' => 'Ana Lectora']))->viewer()->create();
    $invitation = TenantInvitation::factory()->for($this->tenant)->role(TenantRole::Viewer)->create(['name' => 'Carla', 'email' => 'carla@acme.mx']);
    TenantInvitation::factory()->for($this->tenant)->role(TenantRole::Hr)->accepted()->create();
    TenantInvitation::factory()->for($this->tenant)->create(['email' => 'owner-invite@acme.mx']);
    TenantInvitation::factory()->role(TenantRole::Hr)->create(['email' => 'otra@empresa.mx']);

    $this->actingAs($this->owner)
        ->get(route('tenant.members.index', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/members')
            ->where('members.data', fn ($members): bool => collect($members)->pluck('name')->all() === ['Olga Owner', 'Ana Lectora', 'Beto RH'])
            ->where('members.data.0.is_you', true)
            ->where('members.data.2', fn ($row): bool => collect($row)->except('id')->all() === [
                'name' => 'Beto RH', 'email' => 'beto@acme.mx', 'role' => 'hr', 'role_label' => 'RH', 'is_you' => false, 'since' => '29 oct 2026',
            ])
            ->where('invitations.total', 1)
            ->where('invitations.data.0', [
                'id' => $invitation->id, 'name' => 'Carla', 'email' => 'carla@acme.mx', 'role' => 'viewer', 'role_label' => 'Consulta',
                'status' => 'pending', 'status_label' => 'Invitación enviada', 'expires_on' => '5 nov 2026',
            ])
            ->where('roles', [['value' => 'hr', 'label' => 'RH'], ['value' => 'viewer', 'label' => 'Consulta']]));
});

it('pages the people with access and the invitations, each on its own, the owner always first', function (): void {
    foreach (range(1, 25) as $index) {
        TenantMembership::factory()->for($this->tenant)->for(User::factory()->create(['name' => sprintf('Aaa %02d', $index)]))->viewer()->create();
        TenantInvitation::factory()->for($this->tenant)->role($index % 2 === 0 ? TenantRole::Hr : TenantRole::Viewer)->create(['email' => "persona{$index}@acme.mx"]);
    }

    $this->actingAs($this->owner)
        ->get(route('tenant.members.index', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.total', 26)
            ->where('members.data.0.name', 'Olga Owner')
            ->where('members.data.1.name', 'Aaa 01')
            ->where('invitations.total', 25));

    $this->actingAs($this->owner)
        ->get(route('tenant.members.index', [$this->tenant, 'members' => 2, 'invitations' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.name', 'Aaa 25')
            ->has('invitations.data', 0));
});

it('is only for the owner', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))
        ->get(route('tenant.members.index', $this->tenant))
        ->assertForbidden();
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('answers 404 to someone outside the company', function (): void {
    $this->actingAs(ownerOf(Tenant::factory()->create()))
        ->get(route('tenant.members.index', $this->tenant))
        ->assertNotFound();
});

it('invites a person with a role and emails them the link', function (TenantRole $role, string $subject): void {
    Log::spy();

    $this->actingAs($this->owner)
        ->post(route('tenant.members.invitations.store', $this->tenant), ['name' => 'Ana Pérez', 'email' => ' Ana@Acme.MX ', 'role' => $role->value])
        ->assertRedirect(route('tenant.members.index', $this->tenant))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Invitación enviada.']]);

    $invitation = TenantInvitation::query()->sole();

    expect($invitation->only(['tenant_id', 'role', 'name', 'email', 'invited_by']))->toBe([
        'tenant_id' => $this->tenant->id, 'role' => $role, 'name' => 'Ana Pérez', 'email' => 'ana@acme.mx', 'invited_by' => $this->owner->id,
    ])->and($invitation->expires_at->toIso8601String())->toBe('2026-11-05T18:00:00+00:00');

    Notification::assertSentOnDemand(InvitationEmail::class, function (InvitationEmail $email, array $channels, AnonymousNotifiable $notifiable) use ($invitation, $subject): bool {
        $mail = $email->toMail($notifiable);

        return $notifiable->routes['mail'] === 'ana@acme.mx'
            && $mail->subject === $subject
            && TenantInvitation::hashToken(basename($mail->actionUrl ?? '')) === $invitation->token_hash;
    });

    Log::shouldHaveReceived('info')->with('tenant_invitation.sent', ['tenant_id' => $this->tenant->id, 'invitation_id' => $invitation->id, 'user_id' => $this->owner->id, 'role' => $role->value]);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('tenant_invitation.sent')
        ->and($entry->metadata)->toEqual(['tenant_id' => $this->tenant->id, 'role' => $role->value])
        ->and(json_encode($entry->toArray()))->not->toContain('ana@acme.mx');
})->with([
    'hr' => [TenantRole::Hr, 'Te invitaron a Humetría como RH de Acme'],
    'viewer' => [TenantRole::Viewer, 'Te invitaron a consultar Acme en Humetría'],
]);

it('writes what each role will do in the email', function (TenantRole $role, string $line): void {
    $mail = (new InvitationEmail('Acme', 'Ana', 'tok', now()->addDays(7)->toImmutable(), $role))->toMail(new AnonymousNotifiable);

    expect($mail->introLines)->toBe([$line]);
})->with([
    'hr' => [TenantRole::Hr, 'Vas a llevar sus centros de trabajo y su personal, y sus evaluaciones de NOM-035, NOM-037 y Ley Silla.'],
    'viewer' => [TenantRole::Viewer, 'Vas a poder ver sus centros de trabajo y, cuando haya evaluaciones, sus resultados.'],
]);

it('validates the invitation', function (array $payload, string $field, string $message): void {
    TenantMembership::factory()->for($this->tenant)->for(User::factory()->create(['email' => 'beto@acme.mx']))->hr()->create();
    TenantInvitation::factory()->for($this->tenant)->role(TenantRole::Hr)->create(['email' => 'carla@acme.mx']);

    $this->actingAs($this->owner)
        ->post(route('tenant.members.invitations.store', $this->tenant), ['name' => 'Ana', 'email' => 'ana@acme.mx', 'role' => 'hr', ...$payload])
        ->assertSessionHasErrors([$field => $message]);

    expect(TenantInvitation::query()->count())->toBe(1);
})->with([
    'owner role' => [['role' => 'owner'], 'role', 'Elige RH o Consulta.'],
    'unknown role' => [['role' => 'admin'], 'role', 'Elige RH o Consulta.'],
    'member already' => [['email' => 'BETO@acme.mx'], 'email', 'Esa persona ya tiene acceso a esta empresa.'],
    'the owner' => [['email' => 'olga@acme.mx'], 'email', 'Esa persona ya tiene acceso a esta empresa.'],
    'open invitation' => [['email' => 'carla@acme.mx'], 'email', 'Ya hay una invitación abierta para ese correo.'],
    'bad email' => [['email' => 'ana'], 'email', 'El campo correo electrónico debe ser un correo electrónico válido.'],
    'no name' => [['name' => ''], 'name', 'El campo nombre es obligatorio.'],
]);

it('lets an email with an open invitation in another company be invited here', function (): void {
    TenantInvitation::factory()->role(TenantRole::Hr)->create(['email' => 'ana@acme.mx']);

    $this->actingAs($this->owner)
        ->post(route('tenant.members.invitations.store', $this->tenant), ['name' => 'Ana', 'email' => 'ana@acme.mx', 'role' => 'viewer'])
        ->assertSessionHasNoErrors();
});

it('does not let hr or a viewer invite', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role))
        ->post(route('tenant.members.invitations.store', $this->tenant), ['name' => 'Ana', 'email' => 'ana@acme.mx', 'role' => 'hr'])
        ->assertForbidden();

    expect(TenantInvitation::query()->count())->toBe(0);
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('resends and cancels a member invitation of the company', function (): void {
    $invitation = TenantInvitation::factory()->for($this->tenant)->role(TenantRole::Hr)->create(['expires_at' => now()->addDay()]);
    $oldHash = $invitation->token_hash;

    $this->actingAs($this->owner)
        ->post(route('tenant.members.invitations.resend', [$this->tenant, $invitation->id]))
        ->assertRedirect(route('tenant.members.index', $this->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Invitación reenviada.']]);

    expect($invitation->refresh()->token_hash)->not->toBe($oldHash)
        ->and($invitation->expires_at->toIso8601String())->toBe('2026-11-05T18:00:00+00:00');
    Notification::assertSentOnDemandTimes(InvitationEmail::class, 1);

    $this->actingAs($this->owner)
        ->delete(route('tenant.members.invitations.cancel', [$this->tenant, $invitation->id]))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Invitación cancelada.']]);

    expect($invitation->refresh()->cancelled_at)->not->toBeNull();
});

it('answers 404 to the invitation of another company or to the owner invitation', function (string $action): void {
    $foreign = TenantInvitation::factory()->role(TenantRole::Hr)->create();
    $ownerInvitation = TenantInvitation::factory()->for($this->tenant)->create();
    $send = fn (TenantInvitation $invitation) => $action === 'resend'
        ? $this->actingAs($this->owner)->post(route('tenant.members.invitations.resend', [$this->tenant, $invitation->id]))
        : $this->actingAs($this->owner)->delete(route('tenant.members.invitations.cancel', [$this->tenant, $invitation->id]));

    $send($foreign)->assertNotFound();
    $send($ownerInvitation)->assertNotFound();

    expect($foreign->refresh()->cancelled_at)->toBeNull()
        ->and($ownerInvitation->refresh()->cancelled_at)->toBeNull();
})->with(['resend', 'cancel']);
