<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->travelTo('2026-10-28 18:00 UTC');
    $this->token = Str::random(64);

    $this->logPath = storage_path('logs/accept-invitation-'.Str::uuid().'.log');
    config(['logging.channels.single.path' => $this->logPath]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');
});

afterEach(function (): void {
    File::delete($this->logPath);
});

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function newAccount(array $overrides = []): array
{
    return [
        'name' => 'Ana Pérez',
        'password' => 'secreto-largo-123',
        'password_confirmation' => 'secreto-largo-123',
        ...$overrides,
    ];
}

it('lives at /admin/invitations/{token}', function (): void {
    expect(route('invitations.show', 'abc', absolute: false))->toBe('/admin/invitations/abc')
        ->and(route('invitations.accept', 'abc', absolute: false))->toBe('/admin/invitations/abc');
});

it('shows the form for a valid link', function (): void {
    TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme']))
        ->withToken($this->token)->create(['name' => 'Ana Pérez', 'email' => 'ana@acme.mx']);

    $this->get(route('invitations.show', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/accept-invitation')
            ->where('state', 'new_account')
            ->where('trade_name', 'Acme')
            ->where('email', 'ana@acme.mx')
            ->where('name', 'Ana Pérez')
            ->where('token', $this->token));
});

it('shows the link as invalid when it is unknown, used, cancelled or expired', function (string $kind): void {
    $invitation = TenantInvitation::factory()->for(Tenant::factory()->create(['trade_name' => 'Acme']));

    match ($kind) {
        'unknown' => $invitation->create(),
        'used' => $invitation->withToken($this->token)->accepted()->create(),
        'cancelled' => $invitation->withToken($this->token)->cancelled()->create(),
        'expired' => $invitation->withToken($this->token)->expired()->create(),
    };

    $this->get(route('invitations.show', $this->token))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/accept-invitation')
            ->where('state', 'invalid')
            ->where('trade_name', null)
            ->where('email', null)
            ->where('name', null));
})->with(['unknown', 'used', 'cancelled', 'expired']);

it('creates the owner account, signs it in and lands on the dashboard', function (): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->withToken($this->token)->create(['email' => 'ana@acme.mx']);

    $this->post(route('invitations.accept', $this->token), newAccount())
        ->assertSessionHasNoErrors()
        ->assertRedirect('/admin')
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Bienvenido a Humetría.']]);

    $user = User::query()->sole();
    $membership = TenantMembership::query()->sole();

    expect($user->email)->toBe('ana@acme.mx')
        ->and($user->name)->toBe('Ana Pérez')
        ->and($user->email_verified_at?->toIso8601String())->toBe('2026-10-28T18:00:00+00:00')
        ->and(Hash::check('secreto-largo-123', $user->password))->toBeTrue()
        ->and($membership->tenant_id)->toBe($tenant->id)
        ->and($membership->user_id)->toBe($user->id)
        ->and($membership->role)->toBe(TenantRole::Owner)
        ->and($invitation->fresh()?->accepted_at?->toIso8601String())->toBe('2026-10-28T18:00:00+00:00');

    $this->assertAuthenticatedAs($user);
});

it('rejects a weak or unconfirmed password and creates nothing', function (array $payload, string $field): void {
    $invitation = TenantInvitation::factory()->withToken($this->token)->create();

    $this->post(route('invitations.accept', $this->token), newAccount($payload))
        ->assertSessionHasErrors($field);

    expect(User::query()->count())->toBe(0)
        ->and(TenantMembership::query()->count())->toBe(0)
        ->and($invitation->fresh()?->accepted_at)->toBeNull();

    $this->assertGuest();
})->with([
    'weak' => [['password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
    'unconfirmed' => [['password_confirmation' => 'otro-secreto-456'], 'password'],
    'no name' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 256)], 'name'],
]);

it('accepts a link only once', function (): void {
    TenantInvitation::factory()->withToken($this->token)->create();

    $this->post(route('invitations.accept', $this->token), newAccount())->assertRedirect('/admin');
    auth()->logout();

    $this->post(route('invitations.accept', $this->token), newAccount(['name' => 'Intruso']))
        ->assertRedirect(route('invitations.show', $this->token));

    expect(User::query()->sole()->name)->toBe('Ana Pérez');
    $this->assertGuest();

    $this->get(route('invitations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('state', 'invalid'));
});

it('accepts nothing when another request accepts the link between the check and the lock', function (): void {
    $invitation = TenantInvitation::factory()->withToken($this->token)->create();
    $raced = false;

    // The controller reads the invitation as pending; before the action locks it, someone else accepts it.
    Event::listen('eloquent.retrieved: '.TenantInvitation::class, function () use ($invitation, &$raced): void {
        if (! $raced) {
            $raced = true;
            DB::table('tenant_invitations')->where('id', $invitation->id)->update(['accepted_at' => now()]);
        }
    });

    $this->post(route('invitations.accept', $this->token), newAccount())
        ->assertRedirect(route('invitations.show', $this->token));

    expect(User::query()->count())->toBe(0)
        ->and(TenantMembership::query()->count())->toBe(0);
    $this->assertGuest();
});

it('sends an email that already has an account to the login, without touching that account', function (): void {
    $existing = User::factory()->create(['email' => 'ana@acme.mx', 'name' => 'Ana Original']);
    $invitation = TenantInvitation::factory()->withToken($this->token)->create(['email' => 'ana@acme.mx']);

    $this->post(route('invitations.accept', $this->token), newAccount())
        ->assertRedirect(route('login'));

    expect(User::query()->sole()->only(['id', 'name', 'password']))->toBe($existing->only(['id', 'name', 'password']))
        ->and(TenantMembership::query()->count())->toBe(0)
        ->and($invitation->fresh()?->accepted_at)->toBeNull();
    $this->assertGuest();
});

it('records the acceptance with the new owner as actor', function (): void {
    $tenant = Tenant::factory()->create();
    $invitation = TenantInvitation::factory()->for($tenant)->withToken($this->token)
        ->create(['name' => 'Ana Invitada', 'email' => 'ana@acme.mx']);

    $this->post(route('invitations.accept', $this->token), newAccount());

    $user = User::query()->sole();
    $entry = AuditEntry::query()->where('event', 'tenant_invitation.accepted')->sole();

    expect($entry->actor_id)->toBe($user->id)
        ->and($entry->tenant_id)->toBe($tenant->id)
        ->and($entry->subject_id)->toBe($invitation->id);

    $lines = collect(array_filter(explode("\n", File::get($this->logPath))))
        ->map(fn (string $line): array => (array) json_decode($line, true, flags: JSON_THROW_ON_ERROR));

    expect($lines->where('message', 'tenant_invitation.accepted')->sole()['context'])->toBe([
        'tenant_id' => $tenant->id,
        'invitation_id' => $invitation->id,
        'user_id' => $user->id,
    ]);

    $written = File::get($this->logPath).json_encode(AuditEntry::query()->get()->toArray(), JSON_THROW_ON_ERROR);

    expect($written)->not->toContain('ana@acme.mx')
        ->not->toContain('Ana')
        ->not->toContain($this->token)
        ->not->toContain('secreto-largo-123');
});

it('rate limits the link', function (): void {
    foreach (range(1, 10) as $attempt) {
        $this->post(route('invitations.accept', Str::random(64)), newAccount())->assertRedirect();
    }

    $this->post(route('invitations.accept', $this->token), newAccount())->assertTooManyRequests();
    $this->get(route('invitations.show', $this->token))->assertTooManyRequests();

    $this->travel(61)->seconds();

    $this->get(route('invitations.show', $this->token))->assertOk();
});
