<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\InviteOwner;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Http\Requests\StoreInvitationRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Notifications\OwnerInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;

mutates(InviteOwner::class, StoreInvitationRequest::class, OwnerInvitation::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-28 18:00 UTC');
    Notification::fake();
});

it('lives at /admin/companies/{tenant}/invitations', function (): void {
    expect(route('companies.invitations.store', 'abc', absolute: false))->toBe('/admin/companies/abc/invitations');
});

it('lets the superadmin invite the owner of a company', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $admin = superadmin();

    $this->actingAs($admin)
        ->post(route('companies.invitations.store', $tenant), ['name' => 'Ana Pérez', 'email' => ' Ana@Acme.MX '])
        ->assertRedirect(route('companies.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Invitación enviada.']]);

    $invitation = TenantInvitation::query()->sole();

    expect($invitation->tenant_id)->toBe($tenant->id)
        ->and($invitation->invited_by)->toBe($admin->id)
        ->and($invitation->role)->toBe(TenantRole::Owner)
        ->and($invitation->name)->toBe('Ana Pérez')
        ->and($invitation->email)->toBe('ana@acme.mx')
        ->and($invitation->token_hash)->toMatch('/\A[0-9a-f]{64}\z/')
        ->and($invitation->expires_at->toIso8601String())->toBe('2026-11-04T18:00:00+00:00');

    Notification::assertSentOnDemand(OwnerInvitation::class, function (OwnerInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation): bool {
        $mail = $notification->toMail($notifiable);
        $url = $mail->actionUrl ?? '';
        $token = basename($url);

        return $notifiable->routes['mail'] === 'ana@acme.mx'
            && $channels === ['mail']
            && str_starts_with($url, url('admin/invitations/'))
            && strlen($token) === 64
            && TenantInvitation::hashToken($token) === $invitation->token_hash;
    });
});

it('writes the email in Spanish, with the company, the person and the expiry', function (): void {
    $notification = new OwnerInvitation('Acme', 'Ana Pérez', 'tok', now()->addDays(7)->toImmutable());

    $mail = $notification->toMail(new AnonymousNotifiable);

    expect($mail)->toBeInstanceOf(MailMessage::class)
        ->and($mail->subject)->toBe('Te invitaron a Humetría como owner de Acme')
        ->and($mail->greeting)->toBe('Hola, Ana Pérez')
        ->and($mail->actionText)->toBe('Aceptar invitación')
        ->and($mail->actionUrl)->toBe(url('admin/invitations/tok'))
        ->and($mail->outroLines)->toBe(['El enlace vence el 4 nov 2026.', 'Si no esperabas este correo, ignóralo.']);
});

it('renders the email with no English left in the default template', function (): void {
    $notification = new OwnerInvitation('Acme', 'Ana Pérez', 'tok', now()->addDays(7)->toImmutable());

    $html = (string) $notification->toMail(new AnonymousNotifiable)->render();

    expect($html)->toContain('Hola, Ana Pérez')
        ->toContain('Te invitaron a Humetría como owner de Acme')
        ->toContain('¿El botón no abre? Copia este enlace en tu navegador:')
        ->not->toContain('Regards')
        ->not->toContain('trouble clicking')
        ->not->toContain('All rights reserved');
});

it('cancels the open invitation when inviting again', function (Closure $previous): void {
    $tenant = Tenant::factory()->create();
    $old = $previous($tenant);

    $this->actingAs(superadmin())
        ->post(route('companies.invitations.store', $tenant), ['name' => 'Beto', 'email' => 'beto@acme.mx'])
        ->assertSessionHasNoErrors();

    expect($old->refresh()->cancelled_at?->toIso8601String())->toBe('2026-10-28T18:00:00+00:00')
        ->and(TenantInvitation::query()->open()->sole()->email)->toBe('beto@acme.mx')
        ->and(TenantInvitation::query()->count())->toBe(2);
})->with([
    'pending' => [fn (Tenant $tenant) => TenantInvitation::factory()->for($tenant)->create()],
    'expired' => [fn (Tenant $tenant) => TenantInvitation::factory()->for($tenant)->expired()->create()],
]);

it('leaves the invitations of other companies and the finished ones as they were', function (): void {
    $tenant = Tenant::factory()->create();
    $accepted = TenantInvitation::factory()->for($tenant)->accepted()->create();
    $other = TenantInvitation::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.invitations.store', $tenant), ['name' => 'Beto', 'email' => 'beto@acme.mx']);

    expect($accepted->refresh()->cancelled_at)->toBeNull()
        ->and($other->refresh()->cancelled_at)->toBeNull();
});

it('rejects an invalid email, an empty name and the current owner', function (array $input, string $field, string $message): void {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['email' => 'owner@acme.mx']);
    TenantMembership::factory()->for($tenant)->for($owner)->create();

    $this->actingAs(superadmin())
        ->post(route('companies.invitations.store', $tenant), $input)
        ->assertSessionHasErrors([$field => $message]);

    expect(TenantInvitation::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'invalid email' => [['name' => 'Ana', 'email' => 'no-es-correo'], 'email', 'El campo correo electrónico debe ser un correo electrónico válido.'],
    'missing email' => [['name' => 'Ana'], 'email', 'El campo correo electrónico es obligatorio.'],
    'empty name' => [['name' => '', 'email' => 'ana@acme.mx'], 'name', 'El campo nombre es obligatorio.'],
    'long name' => [['name' => str_repeat('a', 256), 'email' => 'ana@acme.mx'], 'name', 'El campo nombre no debe tener más de 255 caracteres.'],
    'array name' => [['name' => ['Ana'], 'email' => 'ana@acme.mx'], 'name', 'El campo nombre debe ser una cadena de texto.'],
    'array email' => [['name' => 'Ana', 'email' => ['ana@acme.mx']], 'email', 'El campo correo electrónico debe ser una cadena de texto.'],
    // A well-formed address of 311 characters: only the length rule can refuse it.
    'email too long' => [['name' => 'Ana', 'email' => str_repeat('a', 64).'@'.implode('.', array_fill(0, 4, str_repeat('b', 60))).'.mx'], 'email', 'El campo correo electrónico no debe tener más de 255 caracteres.'],
    'current owner' => [['name' => 'Otro', 'email' => ' OWNER@acme.mx '], 'email', 'Esta persona ya es el owner.'],
]);

it('fits a name of 255 characters, the longest an account name can be', function (): void {
    $this->actingAs(superadmin())
        ->post(route('companies.invitations.store', Tenant::factory()->create()), ['name' => str_repeat('a', 255), 'email' => 'ana@acme.mx'])
        ->assertSessionHasNoErrors();

    expect(TenantInvitation::query()->sole()->name)->toBe(str_repeat('a', 255));
});

it('lets another company invite the person who owns this one', function (): void {
    $owner = User::factory()->create(['email' => 'owner@acme.mx']);
    TenantMembership::factory()->for($owner)->create();

    $this->actingAs(superadmin())
        ->post(route('companies.invitations.store', Tenant::factory()->create()), ['name' => 'Ana', 'email' => 'owner@acme.mx'])
        ->assertSessionHasNoErrors();

    expect(TenantInvitation::query()->count())->toBe(1);
});

it('logs and audits the invitation without the email, the name or the token', function (): void {
    $tenant = Tenant::factory()->create();
    $admin = superadmin();
    Log::spy();

    $this->actingAs($admin)
        ->post(route('companies.invitations.store', $tenant), ['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);

    $invitation = TenantInvitation::query()->sole();

    Log::shouldHaveReceived('info')->once()->with('tenant_invitation.sent', [
        'tenant_id' => $tenant->id,
        'invitation_id' => $invitation->id,
        'user_id' => $admin->id,
    ]);

    $entry = AuditEntry::query()->sole();
    $token = '';
    Notification::assertSentOnDemand(OwnerInvitation::class, function (OwnerInvitation $notification) use (&$token): bool {
        $token = basename($notification->toMail(new AnonymousNotifiable)->actionUrl ?? '');

        return true;
    });
    $metadata = json_encode($entry->metadata, JSON_THROW_ON_ERROR);

    expect($entry->event)->toBe('tenant_invitation.sent')
        ->and($entry->tenant_id)->toBeNull()
        ->and($entry->subject_id)->toBe($invitation->id)
        ->and($entry->metadata)->toBe(['tenant_id' => $tenant->id])
        ->and($metadata)->not->toContain('ana@acme.mx')
        ->not->toContain('Ana')
        ->not->toContain($token);
});

it('logs and audits which open invitation the new one replaced', function (): void {
    $tenant = Tenant::factory()->create();
    $old = TenantInvitation::factory()->for($tenant)->create();
    $admin = superadmin();
    Log::spy();

    $this->actingAs($admin)
        ->post(route('companies.invitations.store', $tenant), ['name' => 'Beto', 'email' => 'beto@acme.mx']);

    $invitation = TenantInvitation::query()->open()->sole();

    Log::shouldHaveReceived('info')->once()->with('tenant_invitation.sent', [
        'tenant_id' => $tenant->id,
        'invitation_id' => $invitation->id,
        'user_id' => $admin->id,
        'replaced_invitation_id' => $old->id,
    ]);

    expect(AuditEntry::query()->sole()->metadata)->toBe(['tenant_id' => $tenant->id, 'replaced_invitation_id' => $old->id]);
});

it('answers 404 to anyone who is not the superadmin', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('companies.invitations.store', $tenant), ['name' => 'Ana', 'email' => 'ana@acme.mx'])
        ->assertNotFound();

    $this->actingAs(superadmin())
        ->post(route('companies.invitations.store', '0198c3a2-0000-4000-8000-000000000000'), ['name' => 'Ana', 'email' => 'ana@acme.mx'])
        ->assertNotFound();

    expect(TenantInvitation::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('sends guests to the login', function (): void {
    $tenant = Tenant::factory()->create();

    $this->post(route('companies.invitations.store', $tenant), ['name' => 'Ana', 'email' => 'ana@acme.mx'])
        ->assertRedirect(route('login'));

    expect(TenantInvitation::query()->count())->toBe(0);
});
