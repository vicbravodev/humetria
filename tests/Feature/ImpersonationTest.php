<?php

declare(strict_types=1);

use App\Actions\RecordAudit;
use App\Http\Impersonation;
use App\Http\Middleware\EndRevokedImpersonation;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(Impersonation::class, EndRevokedImpersonation::class);

/**
 * A superadmin, a company and its owner, with the superadmin already working as the owner through the real route.
 *
 * @return array{User, Tenant, User}
 */
function enterAsOwner(): array
{
    $superadmin = superadmin();
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);

    test()->actingAs($superadmin)->post(route('companies.impersonate', $tenant));

    return [$superadmin, $tenant, $owner];
}

it('lets the superadmin work as the owner, leaving the start in the audit trail', function (): void {
    $superadmin = superadmin();
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);

    $this->actingAs($superadmin)
        ->post(route('companies.impersonate', $tenant))
        ->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($owner);
    expect(session(Impersonation::SESSION_KEY))->toBe($superadmin->id)
        ->and(AuditEntry::query()->where('event', 'impersonation.started')->sole())
        ->actor_id->toBe($superadmin->id)
        ->impersonator_id->toBe($superadmin->id)
        ->subject_type->toBe($owner->getMorphClass())
        ->subject_id->toBe((string) $owner->id)
        ->tenant_id->toBe($tenant->id)
        ->metadata->toBe(['tenant_id' => $tenant->id]);
});

it('records what is done while impersonating as the owner, with the superadmin behind', function (): void {
    Route::post('/_test/impersonated-audit', fn () => app(RecordAudit::class)->handle('test.event'))->middleware('web');
    [$superadmin, , $owner] = enterAsOwner();

    $this->post('/_test/impersonated-audit');

    expect(AuditEntry::query()->where('event', 'test.event')->sole())
        ->actor_id->toBe($owner->id)
        ->impersonator_id->toBe($superadmin->id);
});

it('brings the superadmin back to the console, leaving the stop in the audit trail', function (): void {
    [$superadmin, , $owner] = enterAsOwner();

    $this->post(route('impersonation.stop'))
        ->assertRedirect(route('companies.index'));

    $this->assertAuthenticatedAs($superadmin);
    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse()
        ->and(AuditEntry::query()->where('event', 'impersonation.stopped')->sole())
        ->actor_id->toBe($owner->id)
        ->impersonator_id->toBe($superadmin->id)
        ->subject_type->toBe($owner->getMorphClass())
        ->subject_id->toBe((string) $owner->id);
});

it('answers a second stop with 404, changing nothing', function (): void {
    [$superadmin] = enterAsOwner();
    $this->post(route('impersonation.stop'));

    $this->post(route('impersonation.stop'))->assertNotFound();

    $this->assertAuthenticatedAs($superadmin);
    expect(AuditEntry::query()->where('event', 'impersonation.stopped')->count())->toBe(1);
});

it('answers stop with 404 to whoever is not impersonating', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('impersonation.stop'))->assertNotFound();

    $this->assertAuthenticatedAs($user);
});

it('asks for a session to stop', function (): void {
    $this->post(route('impersonation.stop'))->assertRedirect(route('login'));
});

it('lets nobody impersonate where it is not allowed', function (Closure $arrange): void {
    [$actor, $tenant] = $arrange();

    $this->actingAs($actor)
        ->post(route('companies.impersonate', $tenant))
        ->assertNotFound();

    $this->assertAuthenticatedAs($actor);
    expect(AuditEntry::query()->where('event', 'impersonation.started')->count())->toBe(0);
})->with([
    'someone who is not a superadmin' => function (): array {
        $tenant = Tenant::factory()->create();
        ownerOf($tenant);

        return [User::factory()->create(), $tenant];
    },
    'a company with no owner' => fn (): array => [superadmin(), Tenant::factory()->create()],
    'an owner who is a superadmin' => function (): array {
        $tenant = Tenant::factory()->create();
        TenantMembership::factory()->for($tenant)->for(superadmin())->create();

        return [superadmin(), $tenant];
    },
]);

it('does not let the superadmin impersonate while already impersonating', function (): void {
    [, , $owner] = enterAsOwner();
    $other = Tenant::factory()->create();
    ownerOf($other);

    $this->post(route('companies.impersonate', $other))->assertNotFound();

    $this->assertAuthenticatedAs($owner);
    expect(AuditEntry::query()->where('event', 'impersonation.started')->count())->toBe(1);
});

it('ends the impersonation on the next request once the superadmin is no longer one', function (Closure $revoke): void {
    [$superadmin, , $owner] = enterAsOwner();
    $revoke($superadmin);
    Log::spy();
    $token = session()->token();

    $this->get(route('dashboard'))->assertRedirect(route('login'));

    $this->assertGuest();
    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse()
        ->and(session()->token())->toBeString()->not->toBe($token);
    Log::shouldHaveReceived('warning')->once()->with('impersonation.revoked', [
        'user_id' => $owner->id,
        'impersonator_id' => $superadmin->id,
    ]);
})->with([
    'role taken away' => fn (User $superadmin) => $superadmin->removeRole(User::SUPERADMIN),
    'account deleted' => fn (User $superadmin) => $superadmin->delete(),
]);

it('lets the request through while the superadmin still is one', function (): void {
    [, $tenant, $owner] = enterAsOwner();

    $this->get(route('tenant.dashboard', $tenant))->assertOk();

    $this->assertAuthenticatedAs($owner);
});

it('signs out instead of coming back when the impersonator is no longer a superadmin', function (): void {
    $superadmin = superadmin();
    $owner = User::factory()->create();
    $this->actingAs($owner);
    session()->put(Impersonation::SESSION_KEY, $superadmin->id);
    $superadmin->removeRole(User::SUPERADMIN);

    $token = session()->token();

    expect(app(Impersonation::class)->stop())->toBeNull();

    $this->assertGuest();
    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse()
        ->and(session()->token())->toBeString()->not->toBe($token);
});

it('records each event with its own actor, even outside a request', function (): void {
    $superadmin = superadmin();
    $owner = User::factory()->create();
    $this->actingAs($superadmin);
    $impersonation = app(Impersonation::class);

    $impersonation->start($superadmin, $owner);
    $impersonation->stop();

    expect(AuditEntry::query()->where('event', 'impersonation.started')->sole())
        ->actor_id->toBe($superadmin->id)
        ->impersonator_id->toBe($superadmin->id)
        ->and(AuditEntry::query()->where('event', 'impersonation.stopped')->sole())
        ->actor_id->toBe($owner->id)
        ->impersonator_id->toBe($superadmin->id)
        ->subject_id->toBe((string) $owner->id);
});

it('gives the session a new id on start and on stop', function (): void {
    $superadmin = superadmin();
    $owner = User::factory()->create();
    $this->actingAs($superadmin);
    session()->start();
    $impersonation = app(Impersonation::class);

    $before = session()->getId();
    $impersonation->start($superadmin, $owner);
    $during = session()->getId();
    $back = $impersonation->stop();

    expect($back?->is($superadmin))->toBeTrue()
        ->and($during)->not->toBe($before)
        ->and(session()->getId())->not->toBe($during);
});

it('ends everything when signing out while impersonating', function (): void {
    enterAsOwner();

    $this->post(route('logout'));

    $this->assertGuest();
    expect(session()->has(Impersonation::SESSION_KEY))->toBeFalse();
});

it('writes ids, never names or emails, in the log and the audit trail', function (): void {
    $lines = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$lines): void {
        $lines[] = $event;
    });
    $superadmin = superadmin();
    $superadmin->forceFill(['name' => 'Sofía Admin', 'email' => 'sofia@humetria.mx'])->save();
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);
    $owner->forceFill(['name' => 'Óscar Dueño', 'email' => 'oscar@acme.mx'])->save();

    $this->actingAs($superadmin)->post(route('companies.impersonate', $tenant));
    $this->post(route('impersonation.stop'));

    $logged = collect($lines)->filter(fn (MessageLogged $line): bool => str_starts_with($line->message, 'impersonation.'));
    $written = json_encode([
        $logged->map(fn (MessageLogged $line): array => [$line->message, $line->context])->values()->all(),
        AuditEntry::query()->where('event', 'like', 'impersonation.%')->get()->toArray(),
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

    expect($logged->pluck('message')->all())->toBe(['impersonation.started', 'impersonation.stopped'])
        ->and($logged->pluck('context')->all())->toBe([['subject_id' => $owner->id], ['subject_id' => $owner->id]])
        ->and($written)->not->toContain('Sofía', 'sofia@', 'Óscar', 'oscar@');
});

it('shares the name of the superadmin behind the owner, and null otherwise', function (): void {
    $superadmin = superadmin();
    $superadmin->forceFill(['name' => 'Sofía Admin'])->save();
    $tenant = Tenant::factory()->create();
    ownerOf($tenant);

    $this->actingAs($superadmin)->post(route('companies.impersonate', $tenant));

    $this->get(route('tenant.dashboard', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('impersonator', 'Sofía Admin'));

    $this->post(route('impersonation.stop'));

    $this->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page->where('impersonator', null));
});
