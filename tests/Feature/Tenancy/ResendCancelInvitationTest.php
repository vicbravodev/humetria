<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\CancelInvitation;
use App\Modules\Tenancy\Actions\ResendInvitation;
use App\Modules\Tenancy\Exceptions\InvitationNoLongerValid;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Notifications\InvitationEmail;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Support\SessionKey;

mutates(ResendInvitation::class, CancelInvitation::class);

const NO_LONGER_OPEN_TOAST = ['toast' => ['type' => 'error', 'message' => 'Esta invitación ya no está abierta.']];

beforeEach(function (): void {
    $this->travelTo('2026-10-28 18:00 UTC');
    Notification::fake();
});

function sentToken(): string
{
    $token = '';
    Notification::assertSentOnDemand(InvitationEmail::class, function (InvitationEmail $notification) use (&$token): bool {
        $token = basename($notification->toMail(new AnonymousNotifiable)->actionUrl ?? '');

        return true;
    });

    return $token;
}

it('lives under /admin/companies/{tenant}/invitations/{invitation}', function (): void {
    expect(route('companies.invitations.resend', ['abc', 'def'], absolute: false))->toBe('/admin/companies/abc/invitations/def/resend')
        ->and(route('companies.invitations.cancel', ['abc', 'def'], absolute: false))->toBe('/admin/companies/abc/invitations/def');
});

it('resends with a new link that works while the old one no longer does', function (bool $expired): void {
    $tenant = Tenant::factory()->create();
    $invitationFactory = TenantInvitation::factory()->for($tenant)->withToken('old-token');
    $invitation = ($expired ? $invitationFactory->expired() : $invitationFactory)->create();
    $admin = superadmin();

    $this->actingAs($admin)
        ->post(route('companies.invitations.resend', [$tenant, $invitation]))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Invitación reenviada.']]);

    $invitation->refresh();
    $token = sentToken();

    expect($invitation->token_hash)->not->toBe(TenantInvitation::hashToken('old-token'))
        ->and($invitation->token_hash)->toBe(TenantInvitation::hashToken($token))
        ->and(strlen($token))->toBe(64)
        ->and($invitation->expires_at->toIso8601String())->toBe('2026-11-04T18:00:00+00:00')
        ->and($invitation->invited_by)->toBe($admin->id)
        ->and(TenantInvitation::query()->count())->toBe(1);

    Notification::assertSentOnDemandTimes(InvitationEmail::class, 1);

    auth()->logout();
    $this->get(route('invitations.show', 'old-token'))->assertInertia(fn ($page) => $page->where('state', 'invalid'));
    $this->get(route('invitations.show', $token))->assertInertia(fn ($page) => $page->where('state', 'new_account'));
})->with([
    'pending' => [false],
    'expired' => [true],
]);

it('cancels an open invitation so its link stops working', function (bool $expired): void {
    $tenant = Tenant::factory()->create();
    $invitationFactory = TenantInvitation::factory()->for($tenant)->withToken('the-token');
    $invitation = ($expired ? $invitationFactory->expired() : $invitationFactory)->create();

    $this->actingAs(superadmin())
        ->delete(route('companies.invitations.cancel', [$tenant, $invitation]))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Invitación cancelada.']]);

    expect($invitation->refresh()->cancelled_at?->toIso8601String())->toBe('2026-10-28T18:00:00+00:00');
    Notification::assertNothingSent();

    $this->get(route('invitations.show', 'the-token'))->assertInertia(fn ($page) => $page->where('state', 'invalid'));
})->with([
    'pending' => [false],
    'expired' => [true],
]);

it('sends the console back with an error for an accepted or cancelled invitation', function (string $state): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->{$state}()->create();
    $before = $invitation->only(['token_hash', 'expires_at', 'accepted_at', 'cancelled_at']);

    $this->actingAs(superadmin())
        ->post(route('companies.invitations.resend', [$tenant, $invitation]))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA, NO_LONGER_OPEN_TOAST);
    $this->actingAs(superadmin())
        ->delete(route('companies.invitations.cancel', [$tenant, $invitation]))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA, NO_LONGER_OPEN_TOAST);

    expect($invitation->refresh()->only(['token_hash', 'expires_at', 'accepted_at', 'cancelled_at']))->toEqual($before);
    Notification::assertNothingSent();
    expect(AuditEntry::query()->count())->toBe(0);
})->with(['accepted', 'cancelled']);

it('answers 404 for an invitation of another company', function (): void {
    $tenant = Tenant::factory()->create();
    $foreign = TenantInvitation::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.invitations.resend', [$tenant, $foreign]))
        ->assertNotFound();
    $this->actingAs(superadmin())
        ->delete(route('companies.invitations.cancel', [$tenant, $foreign]))
        ->assertNotFound();

    expect($foreign->refresh()->cancelled_at)->toBeNull();
    Notification::assertNothingSent();
});

it('answers 404 to anyone who is not the superadmin', function (): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->create();
    $before = $invitation->token_hash;

    $this->actingAs(User::factory()->create())
        ->post(route('companies.invitations.resend', [$tenant, $invitation]))
        ->assertNotFound();
    $this->actingAs(User::factory()->create())
        ->delete(route('companies.invitations.cancel', [$tenant, $invitation]))
        ->assertNotFound();

    expect($invitation->refresh()->token_hash)->toBe($before)
        ->and($invitation->cancelled_at)->toBeNull();
    Notification::assertNothingSent();
});

it('sends guests to the login', function (): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->create();

    $this->post(route('companies.invitations.resend', [$tenant, $invitation]))->assertRedirect(route('login'));
    $this->delete(route('companies.invitations.cancel', [$tenant, $invitation]))->assertRedirect(route('login'));

    expect($invitation->refresh()->cancelled_at)->toBeNull();
});

it('logs and audits resend and cancel without the email, the name or the token', function (string $action, string $event): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);
    $admin = superadmin();
    Log::spy();

    $request = $action === 'resend'
        ? $this->actingAs($admin)->post(route('companies.invitations.resend', [$tenant, $invitation]))
        : $this->actingAs($admin)->delete(route('companies.invitations.cancel', [$tenant, $invitation]));
    $request->assertRedirect(route('companies.index'));

    Log::shouldHaveReceived('info')->once()->with($event, [
        'tenant_id' => $tenant->id,
        'invitation_id' => $invitation->id,
        'user_id' => $admin->id,
    ]);

    $entry = AuditEntry::query()->sole();
    $metadata = json_encode($entry->metadata, JSON_THROW_ON_ERROR);

    expect($entry->event)->toBe($event)
        ->and($entry->tenant_id)->toBeNull()
        ->and($entry->subject_id)->toBe($invitation->id)
        ->and($entry->metadata)->toBe(['tenant_id' => $tenant->id])
        ->and($metadata)->not->toContain('ana@acme.mx')
        ->not->toContain('Ana');

    if ($action === 'resend') {
        expect($metadata)->not->toContain(sentToken());
    }
})->with([
    'resend' => ['resend', 'tenant_invitation.resent'],
    'cancel' => ['cancel', 'tenant_invitation.cancelled'],
]);

it('answers 409 and changes nothing when the invitation stops being open after it was read', function (string $change, string $route): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->create();
    $before = $invitation->only(['token_hash', 'expires_at', 'invited_by']);

    // Same shape as an acceptance committing between the route binding and the action's transaction.
    $stale = TenantInvitation::query()->findOrFail($invitation->id);
    TenantInvitation::query()->whereKey($invitation->id)->update([$change => now()]);

    $admin = superadmin();
    $action = $route === 'resend' ? ResendInvitation::class : CancelInvitation::class;

    expect(fn () => app($action)->handle($stale, $admin))->toThrow(InvitationNoLongerValid::class);

    $fresh = $invitation->refresh();

    expect($fresh->only(['token_hash', 'expires_at', 'invited_by']))->toEqual($before)
        ->and(AuditEntry::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'accept then resend' => ['accepted_at', 'resend'],
    'accept then cancel' => ['accepted_at', 'cancel'],
    'cancel then resend' => ['cancelled_at', 'resend'],
    'cancel then cancel' => ['cancelled_at', 'cancel'],
]);

it('sends the console back with an error when an acceptance commits after the route binding', function (string $verb, string $route): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->create();
    $before = $invitation->only(['token_hash', 'expires_at', 'invited_by']);

    // The row is read as open by the route binding; the acceptance lands right after.
    $accepted = false;
    TenantInvitation::retrieved(function (TenantInvitation $retrieved) use (&$accepted): void {
        if (! $accepted) {
            $accepted = true;
            TenantInvitation::query()->whereKey($retrieved->id)->update(['accepted_at' => now()]);
        }
    });

    $this->actingAs(superadmin())
        ->{$verb}(route($route, [$tenant, $invitation]))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA, NO_LONGER_OPEN_TOAST);

    expect($invitation->refresh()->only(['token_hash', 'expires_at', 'invited_by']))->toEqual($before)
        ->and($invitation->cancelled_at)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
    Notification::assertNothingSent();
})->with([
    'resend' => ['post', 'companies.invitations.resend'],
    'cancel' => ['delete', 'companies.invitations.cancel'],
]);
