<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Support\WorkerDestination;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Support\SoftAuthenticator;

mutates(WorkerDestination::class, RequireWorkerSession::class);

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
    $this->phone = new SoftAuthenticator;
    Notification::fake();

    // A page of the person, as every module's: the company of the slug, then the person's session.
    Route::middleware(['web', ResolveWorkerTenant::class, RequireWorkerSession::class])->group(function (): void {
        Route::get('me/{tenant:slug}/probe', fn (): string => 'la página')->name('worker.test.probe');
        Route::post('me/{tenant:slug}/probe', fn (): string => 'enviado')->name('worker.test.probe.store');
    });
    Route::getRoutes()->refreshNameLookups();
});

/** A one-time link of `$person`, issued by RH unless `requested`. */
function destinationLink(Employee $person, string $token, bool $requested = false): void
{
    $factory = WorkerAccessLink::factory()->withToken($token);
    ($requested ? $factory->requested() : $factory)->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id]);
}

/** A key of Ana that the phone holds. */
function destinationKey(SoftAuthenticator $phone, Employee $person): void
{
    $keys = resolve(WorkerKeys::class);
    $made = $keys->verifyRegistration($person->id, $phone->create($keys->registrationOptions($person->id, $person->name))) ?? throw new LogicException;
    WorkerCredential::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'credential_id' => $made->credentialId, 'public_key' => $made->publicKey]);
}

it('sends a visitor of a page of the person to the entrance of that company, keeping where they were going', function (): void {
    $this->get('/me/acme/probe?page=2')->assertRedirect(route('worker.home', 'acme'));

    expect(session('worker_destination'))->toBe('http://localhost/me/acme/probe?page=2')
        // The panel's own destination is not touched: a sign-in to the panel never lands on a page of the person.
        ->and(session()->has('url.intended'))->toBeFalse();
});

it('answers 401 to a client that asks for JSON without a session, and keeps sending a visitor of the panel to its sign-in', function (): void {
    $this->getJson('/me/acme/probe')->assertUnauthorized();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

it('takes a person who enters with their key to where they were going, once', function (): void {
    destinationKey($this->phone, $this->ana);
    $cookie = config()->string('session.cookie');
    $session = $this->get('/me/acme/probe?page=2')->assertRedirect(route('worker.home', 'acme'))->getCookie($cookie)?->getValue() ?? throw new LogicException;

    $options = $this->withCredentials()->withCookie($cookie, $session)->postJson(route('worker.enter.options', 'acme'))->assertOk()->json();
    $this->withCredentials()->withCookie($cookie, $session)->postJson(route('worker.enter', 'acme'), ['credential' => $this->phone->get($options, $this->ana->id)])
        ->assertOk()->assertJson(['redirect' => 'http://localhost/me/acme/probe?page=2']);

    expect(session()->has('worker_destination'))->toBeFalse();
    $this->get('/me/acme/probe?page=2')->assertOk()->assertSee('la página');
});

it('takes a person who opens a link they asked for to where they were going, once', function (): void {
    destinationLink($this->ana, 'asked', requested: true);
    $this->get('/me/acme/probe')->assertRedirect(route('worker.home', 'acme'));

    $this->post(route('worker.link.open', ['acme', 'asked']))->assertRedirect('http://localhost/me/acme/probe');

    expect(session()->has('worker_destination'))->toBeFalse();
});

it('takes a person with RH\'s link to register a key first, and then to where they were going', function (): void {
    destinationLink($this->ana, 'issued');
    $this->get('/me/acme/probe')->assertRedirect(route('worker.home', 'acme'));

    $this->post(route('worker.link.open', ['acme', 'issued']))->assertRedirect(route('worker.key.show', 'acme'));
    $options = $this->postJson(route('worker.key.options', 'acme'))->assertOk()->json();
    $this->postJson(route('worker.key.store', 'acme'), ['label' => 'iPhone', 'credential' => $this->phone->create($options)])
        ->assertOk()->assertJson(['redirect' => 'http://localhost/me/acme/probe']);

    expect(session()->has('worker_destination'))->toBeFalse();
});

it('takes a person who enters with nowhere to go to their start', function (): void {
    destinationLink($this->ana, 'asked', requested: true);

    $this->post(route('worker.link.open', ['acme', 'asked']))->assertRedirect(route('worker.home', 'acme'));
});

it('ignores a destination that is not a page of the person in this company, and forgets it', function (string $kept): void {
    Tenant::factory()->create(['slug' => 'other']);
    destinationLink($this->ana, 'asked', requested: true);
    session()->put('worker_destination', $kept);
    session()->put('worker_destination_at', now()->getTimestamp());

    $this->post(route('worker.link.open', ['acme', 'asked']))->assertRedirect(route('worker.home', 'acme'));

    expect(session()->has('worker_destination'))->toBeFalse();
})->with([
    'another site' => '//evil.com/me/acme/probe',
    'another site with its scheme' => 'https://evil.com/me/acme/probe',
    'another company' => 'http://localhost/me/other/probe',
    'the panel' => 'http://localhost/admin/acme',
    'a page that only starts like the slug' => 'http://localhost/me/acme-two/probe',
    'a path that climbs out' => 'http://localhost/me/acme/../../admin',
]);

it('keeps where they were going only for a page, never for a form sent without a session', function (): void {
    $this->post('/me/acme/probe')->assertRedirect(route('worker.home', 'acme'));

    expect(session()->has('worker_destination'))->toBeFalse();
});

it('lets the person of the session in, and stops them as soon as the session ends', function (): void {
    enteredAs($this->ana->id, EntryVia::Key, 'key-1');
    $this->get('/me/acme/probe')->assertOk();

    $this->post(route('worker.leave', 'acme'));

    $this->get('/me/acme/probe')->assertRedirect(route('worker.home', 'acme'));
});

it('takes a session of company A in the address of company B to B\'s entrance', function (): void {
    Tenant::factory()->create(['slug' => 'other']);
    enteredAs($this->ana->id, EntryVia::Key, 'key-1');

    $this->get('/me/other/probe')->assertRedirect(route('worker.home', 'other'));
});

it('ends the session of a person who left, and answers 404 for a blocked company or one that does not exist', function (): void {
    enteredAs($this->ana->id, EntryVia::Key, 'key-1');
    Employee::query()->whereKey($this->ana->id)->update(['deactivated_at' => now()]);

    $this->get('/me/acme/probe')->assertRedirect(route('worker.home', 'acme'));
    expect(resolve(WorkerSessionStore::class)->current())->toBeNull();

    $this->get('/me/nobody/probe')->assertNotFound();
    $this->tenant->update(suspension());
    $this->get('/me/acme/probe')->assertNotFound();
});

it('keeps where they were going for ten minutes, and forgets it after: a page left half way is not where they go hours later', function (int $seconds, bool $kept): void {
    $this->get('/me/acme/probe?page=2')->assertRedirect(route('worker.home', 'acme'));
    $this->travel($seconds)->seconds();

    expect(resolve(WorkerDestination::class)->pull('acme'))->toBe($kept ? 'http://localhost/me/acme/probe?page=2' : route('worker.home', 'acme'))
        ->and(session()->has('worker_destination'))->toBeFalse()
        ->and(session()->has('worker_destination_at'))->toBeFalse();
})->with([
    'just kept' => [0, true],
    'at ten minutes' => [600, true],
    'a second after ten minutes' => [601, false],
    'hours later' => [7200, false],
]);
