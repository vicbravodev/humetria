<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Contracts\WorkerLinks;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SoftAuthenticator;

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->phone = new SoftAuthenticator;
    $this->key = keyOf($this->phone, $this->ana);
});

/** A key of `$person` that `$phone` holds, saved in their company. */
function keyOf(SoftAuthenticator $phone, Employee $person): WorkerCredential
{
    workIn(Tenant::query()->findOrFail($person->tenant_id));
    $keys = resolve(WorkerKeys::class);
    $made = $keys->verifyRegistration($person->id, $phone->create($keys->registrationOptions($person->id, $person->name))) ?? throw new LogicException;

    return WorkerCredential::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'credential_id' => $made->credentialId, 'public_key' => $made->publicKey]);
}

/** The phone enters as the page does: options for the browser's session, the key signs (with `$userHandle`), the signature goes. */
function enterWith(SoftAuthenticator $phone, string $userHandle, string $slug = 'acme', bool $tamper = false, string $optionsRoute = 'worker.enter.options'): TestResponse
{
    $cookie = config()->string('session.cookie');
    $session = test()->get(route('worker.home', $slug))->assertOk()->getCookie($cookie)?->getValue() ?? throw new LogicException;
    $options = test()->withCredentials()->withCookie($cookie, $session)->postJson(route($optionsRoute, $slug))->assertOk()->json();

    return test()->withCredentials()->withCookie($cookie, $session)->postJson(route('worker.enter', $slug), ['credential' => $phone->get($options, $userHandle, tamper: $tamper)]);
}

it('shows a visitor the screen to enter, naming nobody', function (): void {
    $this->get(route('worker.home', 'acme'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('worker-access/enter')
            ->where('company', 'Acme')
            ->where('optionsUrl', route('worker.enter.options', 'acme'))
            ->where('enterUrl', route('worker.enter', 'acme'))
            ->etc())
        ->assertDontSee('Ana López');
});

it('answers the same 404 for a company that does not exist and for a blocked one', function (): void {
    Tenant::factory()->create(['slug' => 'blocked', ...suspension()]);

    $this->get(route('worker.home', 'nobody'))->assertNotFound();
    $this->get(route('worker.home', 'blocked'))->assertNotFound();
    $this->postJson(route('worker.enter.options', 'blocked'))->assertNotFound();
});

it('opens the session of the person whose key signed, with a new session id, logging ids only', function (): void {
    Log::spy();

    enterWith($this->phone, $this->ana->id)->assertOk()->assertJson(['redirect' => route('worker.home', 'acme')])->assertHeader('Cache-Control', 'no-store, private');

    $session = resolve(WorkerSessionStore::class)->open();
    expect($session?->identity->employeeId)->toBe($this->ana->id)->and($session?->credentialId)->toBe($this->key->id);
    Log::shouldHaveReceived('info')->with('worker_access.entered', ['employee_id' => $this->ana->id, 'via' => 'key', 'key_id' => $this->key->id]);
    $this->get(route('worker.home', 'acme'))->assertInertia(fn (Assert $page) => $page->component('worker-access/home')
        ->where('company', 'Acme')->where('name', 'Ana López')->where('leaveUrl', route('worker.leave', 'acme')));
});

it('gives the same answer to every signature that does not verify, and opens no session', function (string $case): void {
    $refusal = ['message' => 'No pudimos comprobar su llave. Intente de nuevo o pida un enlace por correo.'];
    $handle = $this->ana->id;
    $tamper = false;
    $optionsRoute = 'worker.enter.options';

    match ($case) {
        'tampered' => $tamper = true,
        'revoked' => $this->key->update(['revoked_at' => now(), 'revoked_reason' => 'revoked_by_hr']),
        'left' => Employee::query()->whereKey($this->ana->id)->update(['deactivated_at' => now()]),
        'another context' => $optionsRoute = 'attendance.device.punch.options',
        'another company' => Tenant::factory()->create(['slug' => 'other']),
    };

    enterWith($this->phone, $handle, $case === 'another company' ? 'other' : 'acme', $tamper, $optionsRoute)
        ->assertStatus(422)->assertExactJson($refusal);

    expect(session()->has('worker'))->toBeFalse();
})->with(['tampered', 'revoked', 'left', 'another context', 'another company']);

it('leaves: the start shows the screen to enter again, and the panel\'s sign-in stays', function (): void {
    enteredAs($this->ana->id, credentialId: $this->key->id);
    session()->put('login_web_x', 7);
    Log::spy();

    $this->post(route('worker.leave', 'acme'))->assertRedirect(route('worker.home', 'acme'));

    expect(session()->get('login_web_x'))->toBe(7);
    Log::shouldHaveReceived('info')->with('worker_access.left', ['employee_id' => $this->ana->id]);
    $this->get(route('worker.home', 'acme'))->assertInertia(fn (Assert $page) => $page->component('worker-access/enter'));
});

it('treats a session of company A as a visitor in the address of company B', function (): void {
    Tenant::factory()->create(['slug' => 'other']);
    enteredAs($this->ana->id, credentialId: $this->key->id);

    $this->get(route('worker.home', 'other'))->assertInertia(fn (Assert $page) => $page->component('worker-access/enter'))->assertDontSee('Ana López');
});

it('opens nothing of a session once its company is blocked', function (): void {
    enteredAs($this->ana->id, credentialId: $this->key->id);

    $this->tenant->update(suspension());

    $this->get(route('worker.home', 'acme'))->assertNotFound();
});

it('stops a key that signs too often, whatever the address', function (): void {
    $credential = ['id' => 'the-same-key'];

    foreach (range(1, 12) as $ignored) {
        $this->postJson(route('worker.enter', 'acme'), ['credential' => $credential])->assertStatus(422);
    }

    $this->postJson(route('worker.enter', 'acme'), ['credential' => $credential])->assertTooManyRequests();
});

it('builds the addresses of the person\'s pages on the app\'s address, with no credential', function (): void {
    config(['app.url' => 'https://humetria.test']);

    expect(resolve(WorkerLinks::class)->home())->toBe('https://humetria.test/me/acme')
        ->and(resolve(WorkerLinks::class)->to('worker.enter'))->toBe('https://humetria.test/me/acme/enter');
});
