<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\AcceptInvitation;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Exceptions\InvitationForAnotherAccount;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\Log;

mutates(AcceptInvitation::class);

beforeEach(function (): void {
    $this->travelTo('2026-10-28 18:00 UTC');
});

it('keeps the current owner until the new one accepts', function (): void {
    $previous = User::factory()->create();
    $tenant = Tenant::factory()->inTrial(now()->subDays(3))->create();
    TenantMembership::factory()->for($tenant)->for($previous)->create();

    TenantInvitation::factory()->for($tenant)->create();

    expect(TenantMembership::query()->sole()->user_id)->toBe($previous->id);
});

it('removes the previous owner from the company when the new one accepts', function (): void {
    $previous = User::factory()->create();
    $tenant = Tenant::factory()->inTrial(now()->subDays(3))->create();
    $other = Tenant::factory()->create();
    TenantMembership::factory()->for($tenant)->for($previous)->create();
    TenantMembership::factory()->for($other)->for($previous)->create();
    $next = User::factory()->create();

    app(AcceptInvitation::class)->handle(TenantInvitation::factory()->for($tenant)->create(['email' => $next->email]), $next);

    $owner = $tenant->ownerMembership()->sole();

    expect($owner->user_id)->toBe($next->id)
        ->and(TenantMembership::query()->where('tenant_id', $tenant->id)->where('role', TenantRole::Owner)->count())->toBe(1)
        ->and($previous->fresh())->not->toBeNull()
        ->and(TenantMembership::query()->where('tenant_id', $other->id)->sole()->user_id)->toBe($previous->id);
});

it('does not restart the trial when the owner changes', function (): void {
    $started = now()->subDays(3);
    $tenant = Tenant::factory()->inTrial($started)->create();
    TenantMembership::factory()->for($tenant)->create();
    Log::spy();

    $next = User::factory()->create();

    app(AcceptInvitation::class)->handle(TenantInvitation::factory()->for($tenant)->create(['email' => $next->email]), $next);

    expect($tenant->fresh()?->trial_started_at?->toIso8601String())->toBe($started->toIso8601String())
        ->and(AuditEntry::query()->where('event', 'tenant.trial_started')->exists())->toBeFalse();

    Log::shouldNotHaveReceived('info', ['tenant.trial_started', Mockery::any()]);
});

it('records the owner change with the previous and new user ids only', function (): void {
    $previous = User::factory()->create();
    $next = User::factory()->create();
    $tenant = Tenant::factory()->inTrial(now()->subDays(3))->create();
    TenantMembership::factory()->for($tenant)->for($previous)->create();
    Log::spy();

    app(AcceptInvitation::class)->handle(TenantInvitation::factory()->for($tenant)->create(['email' => $next->email]), $next);

    $entry = AuditEntry::query()->where('event', 'tenant.owner_changed')->sole();

    expect($entry->tenant_id)->toBe($tenant->id)
        ->and($entry->actor_id)->toBe($next->id)
        ->and($entry->subject_id)->toBe($tenant->id)
        ->and($entry->metadata)->toEqualCanonicalizing(['previous_user_id' => $previous->id, 'user_id' => $next->id]);

    Log::shouldHaveReceived('info', ['tenant.owner_changed', [
        'tenant_id' => $tenant->id,
        'previous_user_id' => $previous->id,
        'user_id' => $next->id,
    ]])->once();
});

it('neither removes nor logs a change when the accepter already is the owner', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->inTrial(now()->subDays(3))->create();
    TenantMembership::factory()->for($tenant)->for($owner)->create();
    Log::spy();

    app(AcceptInvitation::class)->handle(TenantInvitation::factory()->for($tenant)->create(['email' => $owner->email]), $owner);

    expect(TenantMembership::query()->sole()->user_id)->toBe($owner->id)
        ->and(AuditEntry::query()->where('event', 'tenant.owner_changed')->exists())->toBeFalse();

    Log::shouldNotHaveReceived('info', ['tenant.owner_changed', Mockery::any()]);
});

it('records no owner change for the first owner of a company', function (): void {
    $tenant = Tenant::factory()->create();

    $next = User::factory()->create();

    app(AcceptInvitation::class)->handle(TenantInvitation::factory()->for($tenant)->create(['email' => $next->email]), $next);

    expect(AuditEntry::query()->where('event', 'tenant.owner_changed')->exists())->toBeFalse();
});

it('refuses an existing account whose email is not the invited one', function (): void {
    $previous = User::factory()->create();
    $tenant = Tenant::factory()->inTrial(now()->subDays(3))->create();
    TenantMembership::factory()->for($tenant)->for($previous)->create();
    $invitation = TenantInvitation::factory()->for($tenant)->create(['email' => 'ana@acme.mx']);
    $intruder = User::factory()->create(['email' => 'beto@otra.mx']);

    expect(fn () => app(AcceptInvitation::class)->handle($invitation, $intruder))
        ->toThrow(InvitationForAnotherAccount::class);

    expect(TenantMembership::query()->sole()->user_id)->toBe($previous->id)
        ->and($invitation->fresh()?->accepted_at)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('accepts an existing account whatever the case of its email', function (): void {
    $tenant = Tenant::factory()->create();
    $ana = User::factory()->create(['email' => 'Ana@Acme.MX']);

    app(AcceptInvitation::class)->handle(TenantInvitation::factory()->for($tenant)->create(['email' => 'ana@acme.mx']), $ana);

    expect($tenant->ownerMembership()->sole()->user_id)->toBe($ana->id);
});
