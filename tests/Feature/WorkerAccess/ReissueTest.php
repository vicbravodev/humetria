<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\ReissueAccess;
use App\Modules\WorkerAccess\Data\InvitationOutcome;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

mutates(ReissueAccess::class);

/*
 * CUJ 7, «Teléfono perdido»: RH reissues a person's access. At once every key and session of theirs stops, every link of
 * theirs that still opened is revoked, and they get a new link RH issued, to create their key again.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    $this->rh = ownerOf($this->tenant);
    workIn($this->tenant);
    Context::add('user_id', $this->rh->id);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
    $this->phone = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->laptop = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    Notification::fake();
});

it('revokes every key of the person, ends their sessions and revokes their links, and emails a new link', function (): void {
    enteredAs($this->ana->id, EntryVia::Key, $this->phone->id);
    $issued = WorkerAccessLink::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $requested = WorkerAccessLink::factory()->requested()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->travel(1)->seconds();

    $outcome = resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);

    expect($outcome)->toEqual(new InvitationOutcome(InvitationOutcome::SENT))
        ->and($this->phone->refresh())->revoked_reason->toBe('reissued')->revoked_at->not->toBeNull()
        ->and($this->laptop->refresh())->revoked_reason->toBe('reissued')
        ->and($issued->refresh()->revoked_at)->not->toBeNull()
        ->and($requested->refresh()->revoked_at)->not->toBeNull()
        ->and(resolve(WorkerSessionStore::class)->current())->toBeNull();
    $new = WorkerAccessLink::query()->whereNull('revoked_at')->sole();
    expect($new)->kind->value->toBe('issued')->issued_by->toBe($this->rh->id);
    Notification::assertSentOnDemand(WorkerInvitation::class, fn (WorkerInvitation $notice, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'ana@acme.mx');
});

it('keeps why and when a key was revoked before', function (): void {
    $lost = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'revoked_at' => now()->subDay(), 'revoked_reason' => 'not_me']);

    resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);

    expect($lost->refresh())->revoked_reason->toBe('not_me')->revoked_at->toIso8601String()->toBe('2026-11-15T15:00:00+00:00');
});

it('leaves alone the keys of other people', function (): void {
    $beto = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])->id]);

    resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);

    expect($beto->refresh()->revoked_at)->toBeNull();
});

it('returns the new link once for a person without email, for RH to deliver by hand', function (): void {
    $this->ana->forceFill(['email' => null])->save();

    $outcome = resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);

    expect($outcome->status)->toBe(InvitationOutcome::WITHOUT_EMAIL)
        ->and(WorkerAccessLink::findPending(basename((string) parse_url((string) $outcome->url, PHP_URL_PATH)))?->employee_id)->toBe($this->ana->id)
        ->and($this->phone->refresh()->revoked_reason)->toBe('reissued');
    Notification::assertNothingSent();
});

it('writes the reissue to the audit trail and the log with ids and counts only', function (): void {
    Log::spy();

    resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);

    expect(AuditEntry::query()->where('event', 'worker_access.access_reissued')->sole())
        ->actor_id->toBe($this->rh->id)->subject_id->toBeNull()->metadata->toBe(['employee_id' => $this->ana->id, 'keys_revoked' => 2])
        ->and(AuditEntry::query()->where('event', 'worker_access.link_issued')->count())->toBe(1);
    Log::shouldHaveReceived('info')->with('worker_access.access_reissued', ['employee_id' => $this->ana->id, 'keys_revoked' => 2]);
});

it('changes nothing when anything of it fails: the keys still work and no link is left', function (): void {
    AuditEntry::creating(function (AuditEntry $entry): void {
        if ($entry->event === 'worker_access.link_issued') {
            throw new RuntimeException('audit down');
        }
    });

    expect(fn () => resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id))->toThrow(RuntimeException::class, 'audit down');

    expect($this->phone->refresh()->revoked_at)->toBeNull()
        ->and(WorkerAccessLink::query()->count())->toBe(0)
        ->and(AuditEntry::query()->where('event', 'worker_access.access_reissued')->count())->toBe(0);
    Notification::assertNothingSent();
});

it('touches nothing for a person who left or is not the company\'s', function (string $case): void {
    $person = $case === 'left'
        ? EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id])
        : EmployeeFactory::new()->create(['tenant_id' => Tenant::factory()->create()->id]);
    $key = WorkerCredential::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id]);
    workIn($this->tenant);

    expect(resolve(ReissueAccess::class)->handle($person->id, $this->rh->id))->toEqual(new InvitationOutcome(InvitationOutcome::UNAVAILABLE))
        ->and(WorkerCredential::query()->withoutGlobalScopes()->whereKey($key->id)->value('revoked_at'))->toBeNull()
        ->and(WorkerAccessLink::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(AuditEntry::query()->where('event', 'worker_access.access_reissued')->count())->toBe(0);
})->with(['left', 'of another company']);

it('revokes the device keys of the person too, with their own reason, and not those of other people', function (): void {
    $phoneKey = WorkerDeviceKey::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $other = WorkerDeviceKey::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])->id]);
    $earlier = WorkerDeviceKey::factory()->revoked('not_me', now()->subDay())->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->travel(1)->seconds();

    resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);

    expect($phoneKey->refresh())->revoked_reason->toBe('reissued')->revoked_at->toIso8601String()->toBe('2026-11-16T15:00:01+00:00')
        ->and($earlier->refresh())->revoked_reason->toBe('not_me')
        ->and($other->refresh()->revoked_at)->toBeNull();
});

it('counts the device keys revoked in the audit trail and the log, when there are some', function (): void {
    WorkerDeviceKey::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    Log::spy();

    resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);

    expect(AuditEntry::query()->where('event', 'worker_access.access_reissued')->sole()->metadata)->toBe(['employee_id' => $this->ana->id, 'keys_revoked' => 2, 'device_keys_revoked' => 2]);
    Log::shouldHaveReceived('info')->with('worker_access.access_reissued', ['employee_id' => $this->ana->id, 'keys_revoked' => 2, 'device_keys_revoked' => 2]);
});

it('keeps the device keys active when the new link cannot be issued', function (): void {
    $phoneKey = WorkerDeviceKey::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    AuditEntry::creating(function (AuditEntry $entry): void {
        if ($entry->event === 'worker_access.link_issued') {
            throw new RuntimeException('audit down');
        }
    });

    expect(fn () => resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id))->toThrow(RuntimeException::class, 'audit down');

    expect($phoneKey->refresh()->revoked_at)->toBeNull();
});

it('says that the access of the person was reissued, with no key in particular, even when they had no key', function (): void {
    Event::fake([WorkerAccessRevoked::class]);
    $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'email' => 'beto@acme.mx']);

    resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id);
    resolve(ReissueAccess::class)->handle($beto->id, $this->rh->id);

    Event::assertDispatchedTimes(WorkerAccessRevoked::class, 2);
    Event::assertDispatched(WorkerAccessRevoked::class, fn (WorkerAccessRevoked $event): bool => $event->employeeId === $this->ana->id && $event->reason === 'reissued' && $event->credentialId === null);
    Event::assertDispatched(WorkerAccessRevoked::class, fn (WorkerAccessRevoked $event): bool => $event->employeeId === $beto->id);
});

it('says nothing when the new link cannot be issued, so nothing was revoked', function (): void {
    Event::fake([WorkerAccessRevoked::class]);
    AuditEntry::creating(function (AuditEntry $entry): void {
        if ($entry->event === 'worker_access.link_issued') {
            throw new RuntimeException('audit down');
        }
    });

    expect(fn () => resolve(ReissueAccess::class)->handle($this->ana->id, $this->rh->id))->toThrow(RuntimeException::class, 'audit down');

    Event::assertNotDispatched(WorkerAccessRevoked::class);
});

it('says nothing for a person who left or is not the company\'s', function (): void {
    Event::fake([WorkerAccessRevoked::class]);
    $left = EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id]);

    resolve(ReissueAccess::class)->handle($left->id, $this->rh->id);

    Event::assertNotDispatched(WorkerAccessRevoked::class);
});
