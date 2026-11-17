<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Actions\RegisterKey;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Notifications\KeyRegistered;
use App\Modules\WorkerAccess\Support\KeyAllowance;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SoftAuthenticator;

mutates(RegisterKey::class, KeyAllowance::class);

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
    $this->phone = new SoftAuthenticator;
    Notification::fake();
});

/** The phone creates a key as the page does: options for the session, the device makes the key, it goes with its name. */
function registerWith(SoftAuthenticator $phone, string $label = 'iPhone'): TestResponse
{
    $options = test()->postJson(route('worker.key.options', 'acme'))->assertOk()->json();

    return test()->postJson(route('worker.key.store', 'acme'), ['label' => $label, 'credential' => $phone->create($options)]);
}

it('lets a session opened with RH\'s link register the first key, to the audit trail, the log and the person', function (): void {
    enteredAs($this->ana->id, EntryVia::IssuedLink);
    Log::spy();

    registerWith($this->phone)->assertOk()->assertJson(['redirect' => route('worker.home', 'acme')]);

    $key = WorkerCredential::query()->sole();
    expect($key)->employee_id->toBe($this->ana->id)->label->toBe('iPhone')->registered_via->toBe('issued_link')
        ->and(AuditEntry::query()->where('event', 'worker_access.credential_registered')->sole()->subject_id)->toBe($key->id);
    Log::shouldHaveReceived('info')->with('worker_access.key_registered', ['key_id' => $key->id, 'employee_id' => $this->ana->id, 'via' => 'issued_link']);
    Notification::assertSentOnDemand(KeyRegistered::class, function (KeyRegistered $notice, array $channels, object $notifiable) use ($key): bool {
        $mail = $notice->toMail($notifiable);

        expect($notifiable->routes['mail'])->toBe('ana@acme.mx')
            ->and($mail->subject)->toBe('Se registró una llave de acceso a su portal')
            ->and($mail->greeting)->toBe('Hola, Ana López')
            ->and(implode(' ', $mail->introLines))->toContain('«iPhone» el 16 de noviembre de 2026 a las 9:00')
            ->and($mail->actionText)->toBe('No fui yo')
            ->and((string) $mail->actionUrl)->toContain("/me/acme/not-me/{$key->id}?expires=")->toContain('signature=')
            ->and($notice->tries)->toBe(3)->and($notice->backoff())->toBe([60, 300]);

        return true;
    });
});

it('keeps the saved key and answers 200 when the email to the person cannot be queued, reporting the failure', function (): void {
    enteredAs($this->ana->id, EntryVia::IssuedLink);
    Exceptions::fake();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('queue down'));

    registerWith($this->phone)->assertOk()->assertJson(['redirect' => route('worker.home', 'acme')]);

    expect(WorkerCredential::query()->sole()->employee_id)->toBe($this->ana->id);
    Exceptions::assertReported(fn (RuntimeException $e): bool => $e->getMessage() === 'queue down');
});

it('lets a session opened with a key add another one, registered via the session', function (): void {
    $first = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    enteredAs($this->ana->id, EntryVia::Key, $first->id);

    registerWith($this->phone, 'Mac')->assertOk();

    expect(WorkerCredential::query()->where('label', 'Mac')->sole()->registered_via)->toBe('session');
});

it('never lets a session opened with a link the person asked for register a key', function (): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);

    $this->postJson(route('worker.key.options', 'acme'))->assertForbidden();
    $this->postJson(route('worker.key.store', 'acme'), ['label' => 'iPhone', 'credential' => ['id' => 'x']])->assertForbidden();
    $this->get(route('worker.key.show', 'acme'))->assertInertia(fn (Assert $page) => $page->component('worker-access/key')->where('blocked', 'forbidden')->etc());
    $this->get(route('worker.home', 'acme'))->assertInertia(fn (Assert $page) => $page->where('keyUrl', null)->etc());
    expect(WorkerCredential::query()->count())->toBe(0);
});

it('takes up to 3 keys in use and refuses the fourth', function (): void {
    WorkerCredential::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'revoked_at' => now(), 'revoked_reason' => 'revoked_by_hr']);
    enteredAs($this->ana->id, EntryVia::IssuedLink);

    registerWith($this->phone, 'Tercera')->assertOk();

    $this->postJson(route('worker.key.options', 'acme'))->assertStatus(409);
    $this->get(route('worker.key.show', 'acme'))->assertInertia(fn (Assert $page) => $page->where('blocked', 'full')->etc());
    expect(WorkerCredential::query()->whereNull('revoked_at')->count())->toBe(3);
});

it('refuses the fourth key even when it was asked for while there were fewer', function (): void {
    WorkerCredential::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    enteredAs($this->ana->id, EntryVia::IssuedLink);
    $options = $this->postJson(route('worker.key.options', 'acme'))->assertOk()->json();
    WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

    $this->postJson(route('worker.key.store', 'acme'), ['label' => 'iPhone', 'credential' => $this->phone->create($options)])->assertStatus(409);
});

it('sends a visitor to enter, and answers 403 to their options', function (): void {
    $this->get(route('worker.key.show', 'acme'))->assertRedirect(route('worker.home', 'acme'));
    $this->postJson(route('worker.key.options', 'acme'))->assertForbidden();
});

it('refuses a key that does not verify, saving nothing', function (): void {
    enteredAs($this->ana->id, EntryVia::IssuedLink);
    $this->postJson(route('worker.key.options', 'acme'))->assertOk();

    $this->postJson(route('worker.key.store', 'acme'), ['label' => 'iPhone', 'credential' => ['id' => 'nothing']])
        ->assertStatus(422)->assertExactJson(['message' => 'No se pudo guardar la llave. Intente de nuevo.']);
    expect(WorkerCredential::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('takes only a plain name for the device', function (): void {
    enteredAs($this->ana->id, EntryVia::IssuedLink);

    $this->postJson(route('worker.key.store', 'acme'), ['label' => '[x](https://evil.test)', 'credential' => ['id' => 'x']])
        ->assertUnprocessable()->assertJsonValidationErrors('label');
});

it('shows the page to create a key with the person\'s name and no one else\'s', function (): void {
    EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Beto Ruiz']);
    enteredAs($this->ana->id, EntryVia::IssuedLink);

    $this->get(route('worker.key.show', 'acme'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('worker-access/key')
            ->where('company', 'Acme')->where('name', 'Ana López')->where('blocked', null)
            ->where('optionsUrl', route('worker.key.options', 'acme'))->where('storeUrl', route('worker.key.store', 'acme'))->etc())
        ->assertDontSee('Beto Ruiz');
    $this->get(route('worker.home', 'acme'))->assertInertia(fn (Assert $page) => $page->where('keyUrl', route('worker.key.show', 'acme'))->etc());
});
