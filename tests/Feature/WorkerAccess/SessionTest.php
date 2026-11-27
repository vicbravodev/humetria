<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use App\Modules\WorkerAccess\Models\WorkerAccessState;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Support\RequestWorkerSession;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

mutates(WorkerSessionStore::class, RequestWorkerSession::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Demo']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->store = resolve(WorkerSessionStore::class);
});

it('opens a session for a person of the company, which the contract and the guard name', function (): void {
    $this->store->start($this->ana->id, EntryVia::IssuedLink);

    expect(resolve(WorkerSession::class)->current())->toEqual(new WorkerIdentity($this->tenant->id, $this->ana->id, 'Autopartes Demo', 'Ana López'))
        ->and(Auth::guard('worker')->id())->toBe($this->ana->id)
        ->and($this->store->open()?->via)->toBe(EntryVia::IssuedLink);
});

it('has no session for a visitor', function (): void {
    expect(resolve(WorkerSession::class)->current())->toBeNull()
        ->and(Auth::guard('worker')->check())->toBeFalse();
});

it('keeps the key it entered with and counts the signature as a recent proof', function (): void {
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    $session = $this->store->open();

    expect($session?->credentialId)->toBe('key-1')
        ->and($session?->proofAt?->toIso8601String())->toBe('2026-11-16T15:00:00+00:00');
});

it('gives no proof to a session opened with a link', function (): void {
    $this->store->start($this->ana->id, EntryVia::RequestedLink);

    expect($this->store->open()?->proofAt)->toBeNull()->and($this->store->open()?->credentialId)->toBeNull();
});

it('keeps a session used exactly 30 minutes ago, and every request moves that on', function (): void {
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    $this->travel(30)->minutes();
    expect($this->store->current())->not->toBeNull();

    $this->travel(30)->minutes();
    expect($this->store->current())->not->toBeNull();
});

it('ends a session a second past 30 minutes without use, and forgets it', function (): void {
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    $this->travel(30 * 60 + 1)->seconds();

    expect($this->store->current())->toBeNull()
        ->and(session()->has('worker'))->toBeFalse();
});

it('ends a session 8 hours after it opened, however much it is used', function (): void {
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    foreach (range(1, 16) as $ignored) {
        $this->travel(30)->minutes();
        expect($this->store->current())->not->toBeNull();
    }

    $this->travel(1)->seconds();
    expect($this->store->current())->toBeNull();
});

it('does not take a session of company A in company B', function (): void {
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    workIn(Tenant::factory()->create());

    expect($this->store->current())->toBeNull()->and(session()->has('worker'))->toBeFalse();
});

it('ends every session opened before the person\'s valid_after', function (): void {
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');
    $this->travel(1)->minutes();

    WorkerAccessState::moveValidAfter($this->tenant->id, $this->ana->id);

    expect($this->store->current())->toBeNull();
});

it('keeps a session opened after valid_after moved', function (): void {
    WorkerAccessState::moveValidAfter($this->tenant->id, $this->ana->id);
    $this->travel(1)->seconds();

    $this->store->start($this->ana->id, EntryVia::IssuedLink);

    expect($this->store->current())->not->toBeNull();
});

it('ends the session of a person who left the company', function (): void {
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    Employee::query()->whereKey($this->ana->id)->update(['deactivated_at' => now()]);

    expect($this->store->current())->toBeNull();
});

it('ends the session of a person of a company that was blocked', function (): void {
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    $this->tenant->update(suspension());
    workIn($this->tenant->refresh());

    expect($this->store->current())->toBeNull();
});

it('changes the session id when a person enters and when they leave', function (): void {
    $before = session()->getId();

    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');
    $entered = session()->getId();
    $this->store->end();

    expect($entered)->not->toBe($before)->and(session()->getId())->not->toBe($entered);
});

it('leaves only the person\'s entry: the rest of the session stays', function (): void {
    session()->put('login_web_x', 7);
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    $this->store->end();

    expect(session()->get('login_web_x'))->toBe(7)->and($this->store->current())->toBeNull();
});

it('does not revoke the device key when the person leaves: «Salir» only forgets the session', function (): void {
    $phoneKey = WorkerDeviceKey::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    $this->post(route('worker.leave', $this->tenant->slug))->assertRedirect();

    expect($phoneKey->refresh()->revoked_at)->toBeNull()->and($this->store->current())->toBeNull();
});

it('dispatches no event of WorkerAccess when the person leaves', function (): void {
    Event::fake([WorkerAccessRevoked::class]);
    $this->store->start($this->ana->id, EntryVia::Key, 'key-1');

    $this->post(route('worker.leave', $this->tenant->slug))->assertRedirect();

    Event::assertNotDispatched(WorkerAccessRevoked::class);
});
