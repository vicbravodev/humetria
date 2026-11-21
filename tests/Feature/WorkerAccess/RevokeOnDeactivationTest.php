<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Events\EmployeeDeactivated;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Listeners\RevokeOnDeactivation;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(RevokeOnDeactivation::class);

/*
 * Someone who left the company has no access: their keys stop, their links stop opening and their sessions end, whatever
 * the day they left. If they come back, they enter with a new link and a new key, not with what a phone kept from before.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'hired_on' => '2025-01-01']);
    $this->phone = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->laptop = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

it('revokes every key and link of the person who left and ends their sessions', function (): void {
    enteredAs($this->ana->id, EntryVia::Key, $this->phone->id);
    $issued = WorkerAccessLink::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $requested = WorkerAccessLink::factory()->requested()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->travel(1)->seconds();
    Log::spy();

    resolve(DeactivateEmployee::class)->handle($this->ana, effectiveOn: CarbonImmutable::parse('2026-11-10'));

    expect($this->phone->refresh())->revoked_reason->toBe('left_company')->revoked_at->toIso8601String()->toBe('2026-11-16T15:00:01+00:00')
        ->and($this->laptop->refresh()->revoked_reason)->toBe('left_company')
        ->and($issued->refresh()->revoked_at)->not->toBeNull()
        ->and($requested->refresh()->revoked_at)->not->toBeNull()
        ->and(resolve(WorkerSessionStore::class)->current())->toBeNull()
        ->and(AuditEntry::query()->where('event', 'worker_access.access_revoked_on_leaving')->sole()->metadata)->toEqual(['employee_id' => $this->ana->id, 'keys_revoked' => 2]);
    Log::shouldHaveReceived('info')->with('worker_access.access_revoked_on_leaving', ['employee_id' => $this->ana->id, 'keys_revoked' => 2])->once();
});

it('ends the session of someone with no key and no link too, so coming back soon after does not bring it back', function (): void {
    $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'hired_on' => '2025-01-01']);
    // They entered with a link they already used: nothing of theirs to revoke, only the session.
    enteredAs($beto->id, EntryVia::RequestedLink);
    expect(resolve(WorkerSessionStore::class)->current())->not->toBeNull();
    $this->travel(1)->seconds();

    resolve(DeactivateEmployee::class)->handle($beto);
    $this->travel(5)->minutes();
    $beto->update(['deactivated_at' => null, 'terminated_on' => null, 'deactivation_reason' => null]);

    expect(resolve(WorkerSessionStore::class)->current())->toBeNull();
});

it('keeps why and when a key was revoked before', function (): void {
    $lost = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'revoked_at' => now()->subDay(), 'revoked_reason' => 'not_me']);

    resolve(DeactivateEmployee::class)->handle($this->ana);

    expect($lost->refresh())->revoked_reason->toBe('not_me')->revoked_at->toIso8601String()->toBe('2026-11-15T15:00:00+00:00')
        ->and($this->phone->refresh()->revoked_reason)->toBe('left_company');
});

it('leaves alone the keys, links and sessions of other people', function (): void {
    $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $key = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $beto->id]);
    $link = WorkerAccessLink::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $beto->id]);
    enteredAs($beto->id, EntryVia::Key, $key->id);
    $this->travel(1)->seconds();

    resolve(DeactivateEmployee::class)->handle($this->ana);

    expect($key->refresh()->revoked_at)->toBeNull()
        ->and($link->refresh()->revoked_at)->toBeNull()
        ->and(resolve(WorkerSessionStore::class)->current())->not->toBeNull();
});

it('does nothing, and writes nothing, for a person with no access', function (): void {
    $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);

    resolve(DeactivateEmployee::class)->handle($beto);

    expect(AuditEntry::query()->where('event', 'worker_access.access_revoked_on_leaving')->count())->toBe(0);
});

it('works in the company of the event, whatever company was current', function (): void {
    workIn(Tenant::factory()->create());

    event(new EmployeeDeactivated($this->tenant->id, $this->ana->id, now()->toImmutable()));

    workIn($this->tenant);
    expect($this->phone->refresh()->revoked_reason)->toBe('left_company');
});

it('undoes the revocation when the deactivation does not go through', function (): void {
    try {
        DB::transaction(function (): void {
            resolve(DeactivateEmployee::class)->handle($this->ana);

            throw new RuntimeException('The deactivation failed.');
        });
    } catch (RuntimeException) {
        // Rolled back with the rest.
    }

    expect($this->phone->refresh()->revoked_at)->toBeNull();
});
