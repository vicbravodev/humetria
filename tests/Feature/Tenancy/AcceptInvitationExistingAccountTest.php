<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use App\Modules\Tenancy\Notifications\OwnerInvitation;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-28 18:00 UTC');
    $this->token = Str::random(64);
});

it('lives at /admin/invitations/{token}/switch-account', function (): void {
    expect(route('invitations.switch-account', 'abc', absolute: false))->toBe('/admin/invitations/abc/switch-account');
});

it('asks an existing account to sign in and returns to the link afterwards', function (): void {
    User::factory()->create(['email' => 'ana@acme.mx']);
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme']))
        ->withToken($this->token)->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);

    $this->get(route('invitations.show', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/accept-invitation')
            ->where('state', 'sign_in')
            ->where('trade_name', 'Acme')
            ->where('email', 'ana@acme.mx'));

    $this->post(route('login.store'), ['email' => 'ana@acme.mx', 'password' => 'password'])
        ->assertRedirect(route('invitations.show', $this->token));
});

it('adds a signed-in existing account as owner without touching its password or name', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $ana = User::factory()->create(['email' => 'ana@acme.mx', 'name' => 'Ana Original', 'email_verified_at' => '2025-01-15 12:00 UTC']);
    $invitation = TenantInvitation::factory()->for($tenant)->withToken($this->token)
        ->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);
    $before = $ana->fresh()?->only(['name', 'password', 'email_verified_at']);

    $this->actingAs($ana)
        ->get(route('invitations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('state', 'confirm')
            ->where('trade_name', 'Acme'));

    $this->post(route('invitations.accept', $this->token))
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin');

    $membership = TenantMembership::query()->sole();

    expect(User::query()->sole()->only(['name', 'password', 'email_verified_at']))->toEqual($before)
        ->and($membership->tenant_id)->toBe($tenant->id)
        ->and($membership->user_id)->toBe($ana->id)
        ->and($membership->role)->toBe(TenantRole::Owner)
        ->and($invitation->fresh()?->accepted_at?->toIso8601String())->toBe('2026-10-28T18:00:00+00:00');

    $this->assertAuthenticatedAs($ana);
});

it('finds the existing account whatever the case of the invited email', function (string $accountEmail): void {
    Notification::fake();
    $tenant = Tenant::factory()->create();
    $ana = User::factory()->create(['email' => $accountEmail]);

    $this->actingAs(superadmin())
        ->post(route('companies.invitations.store', $tenant), ['name' => 'Ana Pérez', 'email' => 'Ana@Acme.MX']);
    auth()->logout();

    $token = '';
    Notification::assertSentOnDemand(OwnerInvitation::class, function (OwnerInvitation $notification) use (&$token): bool {
        $token = basename($notification->toMail(new AnonymousNotifiable)->actionUrl ?? '');

        return true;
    });

    $this->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('state', 'sign_in'));

    $this->actingAs($ana)
        ->get(route('invitations.show', $token))
        ->assertInertia(fn (Assert $page) => $page->where('state', 'confirm'));

    $this->post(route('invitations.accept', $token))->assertRedirect('/admin');

    expect(User::query()->whereRaw('lower(email) = ?', ['ana@acme.mx'])->count())->toBe(1)
        ->and(TenantMembership::query()->sole()->user_id)->toBe($ana->id);
})->with(['ana@acme.mx', 'Ana@Acme.MX']);

it('refuses to accept for a different signed-in account', function (bool $invitedHasAccount, bool $asSuperadmin): void {
    $invited = $invitedHasAccount ? User::factory()->create(['email' => 'ana@acme.mx', 'name' => 'Ana Original']) : null;
    $invitation = TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme']))
        ->withToken($this->token)->create(['email' => 'ana@acme.mx']);
    $other = $asSuperadmin ? superadmin() : User::factory()->create(['email' => 'beto@otra.mx']);

    $this->actingAs($other)
        ->get(route('invitations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            ->where('state', 'other_account')
            ->where('trade_name', 'Acme')
            ->where('email', 'ana@acme.mx'));

    $this->post(route('invitations.accept', $this->token), ['name' => 'Intruso', 'password' => 'secreto-largo-123', 'password_confirmation' => 'secreto-largo-123'])
        ->assertForbidden();

    expect(TenantMembership::query()->count())->toBe(0)
        ->and($invitation->fresh()?->accepted_at)->toBeNull()
        ->and(User::query()->count())->toBe($invitedHasAccount ? 2 : 1)
        ->and($invited?->fresh()?->only(['name', 'password']))->toBe($invited?->only(['name', 'password']));

    $this->assertAuthenticatedAs($other);
})->with([
    'invited email has an account' => [true, false],
    'invited email has no account' => [false, false],
    'signed in as the superadmin' => [false, true],
]);

it('switches account and comes back to the link', function (): void {
    $other = User::factory()->create(['email' => 'beto@otra.mx']);
    TenantInvitation::factory()->withToken($this->token)->create(['email' => 'ana@acme.mx']);

    $this->actingAs($other)->withSession(['marker' => 'old-session']);

    $this->post(route('invitations.switch-account', $this->token))
        ->assertRedirect(route('invitations.show', $this->token))
        ->assertSessionMissing('marker');

    $this->assertGuest();
});

it('asks a guest to sign in before switching account', function (): void {
    $this->post(route('invitations.switch-account', $this->token))
        ->assertRedirect(route('login'));
});

it('never offers the password form for an email that already has an account', function (): void {
    $ana = User::factory()->create(['email' => 'ana@acme.mx', 'name' => 'Ana Original']);
    $invitation = TenantInvitation::factory()->withToken($this->token)->create(['email' => 'ana@acme.mx']);

    $this->get(route('invitations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('state', 'sign_in'));

    $this->post(route('invitations.accept', $this->token), ['name' => 'Intruso', 'password' => 'otro-secreto-456', 'password_confirmation' => 'otro-secreto-456'])
        ->assertRedirect(route('invitations.show', $this->token));

    expect(User::query()->sole()->only(['id', 'name', 'password']))->toBe($ana->only(['id', 'name', 'password']))
        ->and(TenantMembership::query()->count())->toBe(0)
        ->and($invitation->fresh()?->accepted_at)->toBeNull();
    $this->assertGuest();
});

it('returns an existing account to the link after it signs in, even when it first submitted the form', function (): void {
    User::factory()->create(['email' => 'ana@acme.mx']);
    TenantInvitation::factory()->withToken($this->token)->create(['email' => 'ana@acme.mx']);

    $this->followingRedirects()
        ->post(route('invitations.accept', $this->token), ['name' => 'Ana', 'password' => 'otro-secreto-456', 'password_confirmation' => 'otro-secreto-456'])
        ->assertInertia(fn (Assert $page) => $page->where('state', 'sign_in'));

    $this->post(route('login.store'), ['email' => 'ana@acme.mx', 'password' => 'password'])
        ->assertRedirect(route('invitations.show', $this->token));
});
