<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Http\Middleware\RequireRecentProof;
use App\Modules\WorkerAccess\Http\Middleware\RequireWorkerSession;
use App\Modules\WorkerAccess\Http\Middleware\ResolveWorkerTenant;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Notifications\ProofCode;
use App\Modules\WorkerAccess\Support\ProofCodes;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SoftAuthenticator;

mutates(RequireRecentProof::class, ProofCodes::class);

/*
 * What is delicate asks for a recent proof (spec «Prueba reciente», Goal 8): a signature of the person's key, or a code by
 * email for a person without a key, made less than 5 minutes ago. Without it the server gives no content and takes no form.
 */
beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-16 15:00:00', 'UTC'));
    Notification::fake();
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López', 'email' => 'ana@acme.mx']);
    $this->phone = new SoftAuthenticator;
    // One browser: every request carries the same session cookie, as a phone does (the challenge and the code are tied to it).
    $this->withCredentials()->withCookie(config()->string('session.cookie'), Str::random(40));

    // A delicate page and form of the person, as a module declares them: the company, the session, then the recent proof.
    Route::middleware(['web', ResolveWorkerTenant::class, RequireWorkerSession::class, 'worker.proof'])->group(function (): void {
        Route::get('me/{tenant:slug}/secret', fn (): string => 'el secreto')->name('worker.test.secret');
        Route::post('me/{tenant:slug}/secret', fn (): string => 'aceptado')->name('worker.test.secret.store');
    });
    Route::getRoutes()->refreshNameLookups();
});

/** A key of `$person` that `$phone` holds, saved in their company. */
function proofKey(SoftAuthenticator $phone, Employee $person): WorkerCredential
{
    workIn(Tenant::query()->findOrFail($person->tenant_id));
    $keys = resolve(WorkerKeys::class);
    $made = $keys->verifyRegistration($person->id, $phone->create($keys->registrationOptions($person->id, $person->name))) ?? throw new LogicException;

    return WorkerCredential::factory()->create(['tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'credential_id' => $made->credentialId, 'public_key' => $made->publicKey]);
}

/** The code the last email to `$email` carried. */
function sentProofCode(string $email = 'ana@acme.mx'): string
{
    $code = null;
    Notification::assertSentOnDemand(ProofCode::class, function (ProofCode $notice, array $channels, AnonymousNotifiable $notifiable) use (&$code, $email): bool {
        $code = $notifiable->routes['mail'] === $email ? $notice->code : $code;

        return true;
    });

    return $code ?? throw new LogicException('No code was sent.');
}

/** The steps of a proof, as the page and the form's flag give them, for a person with a key, with a code, or with neither. */
function proofStep(string $mode): array
{
    return [
        'mode' => $mode,
        'optionsUrl' => route('worker.proof.options', 'acme'),
        'proveUrl' => route('worker.proof.store', 'acme'),
        'sendCodeUrl' => route('worker.proof.code.send', 'acme'),
        'checkCodeUrl' => route('worker.proof.code.check', 'acme'),
        'minutes' => 10,
    ];
}

it('asks for a recent proof on exactly the delicate pages and forms of the person', function (): void {
    $delicate = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (Illuminate\Routing\Route $route): bool => in_array(RequireRecentProof::class, Route::gatherRouteMiddleware($route), true))
        ->map(fn (Illuminate\Routing\Route $route): ?string => $route->getName())
        ->reject(fn (?string $name): bool => str_starts_with((string) $name, 'worker.test.'))
        ->sort()->values()->all();

    expect($delicate)->toBe([
        'portal.documents.acknowledge',
        'portal.equipment.respond',
        'portal.policy.acknowledge',
        'portal.seating.acknowledgment',
        'portal.seating.acknowledgments.store',
        'portal.support.show',
        'portal.vacations.requests.cancel',
        'portal.vacations.requests.store',
        'portal.vacations.statements.acknowledge',
        'portal.vacations.team.approve',
        'portal.vacations.team.reject',
        'portal.visits.confirm',
        'worker.device-key.options',
        'worker.device-key.store',
    ]);
});

it('lets a page and a form through for less than 5 minutes after the proof, and not from the fifth minute on', function (int $seconds, bool $passes): void {
    enteredAs($this->ana->id);
    $this->travel($seconds)->seconds();

    $page = $this->get('/me/acme/secret');
    $form = $this->from('/me/acme/vacations')->post('/me/acme/secret');

    if ($passes) {
        $page->assertOk()->assertSee('el secreto');
        $form->assertOk()->assertSee('aceptado');
    } else {
        $page->assertRedirect(route('worker.proof.show', 'acme'))->assertDontSee('el secreto');
        $form->assertRedirect('http://localhost/me/acme/vacations')->assertDontSee('aceptado');
    }
})->with([
    '4:59' => [299, true],
    '5:00' => [300, false],
    '5:01' => [301, false],
]);

it('counts entering with a key as a proof, and entering with a link not', function (EntryVia $via, bool $passes): void {
    enteredAs($this->ana->id, $via);

    $response = $this->get('/me/acme/secret');

    $passes ? $response->assertOk() : $response->assertRedirect(route('worker.proof.show', 'acme'));
})->with([
    'a key' => [EntryVia::Key, true],
    'a link RH issued' => [EntryVia::IssuedLink, false],
    'a link they asked for' => [EntryVia::RequestedLink, false],
]);

it('sends a visitor to enter first, and asks a client that wants JSON for its session or for the proof', function (): void {
    $this->get('/me/acme/secret')->assertRedirect(route('worker.home', 'acme'));
    $this->getJson('/me/acme/secret')->assertUnauthorized();

    enteredAs($this->ana->id, EntryVia::RequestedLink);

    $this->getJson('/me/acme/secret')->assertForbidden()->assertJson(['proofRequired' => true])->assertDontSee('el secreto');
    $this->postJson('/me/acme/secret')->assertForbidden()->assertDontSee('aceptado');
});

it('takes a form sent without a recent proof back to its page, with the step to prove', function (): void {
    proofKey($this->phone, $this->ana);
    enteredAs($this->ana->id, EntryVia::RequestedLink);

    $this->from('/me/acme/vacations?year=2026')->post('/me/acme/secret', ['days' => 3])
        ->assertRedirect('http://localhost/me/acme/vacations?year=2026')
        ->assertInertiaFlash('proofRequired', proofStep('key'));

    expect(session()->has('worker_destination'))->toBeFalse();
});

it('takes a form back to the start of the person when it came from anywhere else', function (string $from): void {
    Tenant::factory()->create(['slug' => 'other']);
    enteredAs($this->ana->id, EntryVia::RequestedLink);

    $this->from($from)->post('/me/acme/secret')->assertRedirect(route('worker.home', 'acme'))->assertInertiaFlash('proofRequired');
})->with([
    'another site' => '//evil.com/me/acme/vacations',
    'another site with its scheme' => 'https://evil.com/me/acme/vacations',
    'another company' => 'http://localhost/me/other/vacations',
    'the panel' => 'http://localhost/admin/acme',
]);

it('shows the step that fits the person: their key, a code by email, or the way to RH', function (?string $email, bool $key, string $mode): void {
    Employee::query()->whereKey($this->ana->id)->update(['email' => $email]);
    if ($key) {
        proofKey($this->phone, $this->ana);
    }
    enteredAs($this->ana->id, EntryVia::IssuedLink);

    $this->get(route('worker.proof.show', 'acme'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('worker-access/proof')
            ->where('company', 'Acme')
            ->where('step', proofStep($mode))
            ->where('homeUrl', route('worker.home', 'acme')))
        ->assertDontSee('ana@acme.mx');
})->with([
    'with a key, never the code' => ['ana@acme.mx', true, 'key'],
    'with a key and no email' => [null, true, 'key'],
    'without a key' => ['ana@acme.mx', false, 'code'],
    'without a key or an email' => [null, false, 'none'],
]);

it('sends a visitor of the proof page to enter', function (): void {
    $this->get(route('worker.proof.show', 'acme'))->assertRedirect(route('worker.home', 'acme'));
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertUnauthorized();
});

it('takes the person who signs with their key to the page they were going to, logging ids only', function (): void {
    $key = proofKey($this->phone, $this->ana);
    enteredAs($this->ana->id, EntryVia::RequestedLink);
    $this->get('/me/acme/secret?part=2')->assertRedirect(route('worker.proof.show', 'acme'));
    Log::spy();

    $options = $this->postJson(route('worker.proof.options', 'acme'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();
    $this->postJson(route('worker.proof.store', 'acme'), ['credential' => $this->phone->get($options, $this->ana->id)])
        ->assertOk()->assertJson(['redirect' => 'http://localhost/me/acme/secret?part=2']);

    Log::shouldHaveReceived('info')->with('worker_access.proof_given', ['employee_id' => $this->ana->id, 'via' => 'key', 'key_id' => $key->id]);
    expect(resolve(WorkerSessionStore::class)->open()?->proofAt?->equalTo(now()))->toBeTrue();
    $this->get('/me/acme/secret?part=2')->assertOk()->assertSee('el secreto');
});

it('gives the same answer to every signature that is not a key of the person in use, and no proof', function (string $case): void {
    $key = proofKey($this->phone, $this->ana);
    $signer = $this->phone;
    $handle = $this->ana->id;
    $optionsRoute = 'worker.proof.options';
    $tamper = false;

    match ($case) {
        'another person\'s key' => [$signer = new SoftAuthenticator, proofKey($signer, $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id])), $handle = $beto->id],
        'a key of another company' => [$signer = new SoftAuthenticator, proofKey($signer, $other = EmployeeFactory::new()->create(['tenant_id' => Tenant::factory()->create()->id])), $handle = $other->id],
        'a revoked key' => $key->update(['revoked_at' => now(), 'revoked_reason' => 'revoked_by_hr']),
        'a challenge to enter' => $optionsRoute = 'worker.enter.options',
        'a tampered signature' => $tamper = true,
    };
    workIn($this->tenant);
    enteredAs($this->ana->id, EntryVia::RequestedLink);

    $options = $this->postJson(route($optionsRoute, 'acme'))->assertOk()->json();
    $this->postJson(route('worker.proof.store', 'acme'), ['credential' => $signer->get($options, $handle, tamper: $tamper)])
        ->assertStatus(422)->assertExactJson(['message' => 'No pudimos comprobar su llave. Intente de nuevo.']);

    $this->get('/me/acme/secret')->assertRedirect(route('worker.proof.show', 'acme'));
})->with(['another person\'s key', 'a key of another company', 'a revoked key', 'a challenge to enter', 'a tampered signature']);

it('keeps a proof in its company: a session of A is a visitor in the address of B', function (): void {
    Tenant::factory()->create(['slug' => 'other']);
    enteredAs($this->ana->id);

    $this->get('/me/acme/secret')->assertOk();
    $this->get('/me/other/secret')->assertRedirect(route('worker.home', 'other'));
});

it('never offers a code to a person with a key, nor to one without an email', function (?string $email, bool $key): void {
    Employee::query()->whereKey($this->ana->id)->update(['email' => $email]);
    if ($key) {
        proofKey($this->phone, $this->ana);
    }
    enteredAs($this->ana->id, EntryVia::IssuedLink);

    $this->postJson(route('worker.proof.code.send', 'acme'))->assertForbidden();
    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => '123456'])->assertForbidden();

    Notification::assertNothingSent();
})->with([
    'with a key' => ['ana@acme.mx', true],
    'without a key or an email' => [null, false],
]);

it('emails a person without a key a code of six digits right away, and proves with it once', function (): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);
    $this->get('/me/acme/secret')->assertRedirect(route('worker.proof.show', 'acme'));
    Log::spy();

    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk()->assertExactJson(['sent' => true]);
    $code = sentProofCode();
    Notification::assertSentOnDemand(ProofCode::class, fn (ProofCode $notice): bool => ! $notice instanceof Illuminate\Contracts\Queue\ShouldQueue);

    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $code])->assertOk()->assertJson(['redirect' => 'http://localhost/me/acme/secret']);

    expect($code)->toMatch('/^\d{6}$/');
    Log::shouldHaveReceived('info')->with('worker_access.proof_code_sent', ['employee_id' => $this->ana->id]);
    Log::shouldHaveReceived('info')->with('worker_access.proof_given', ['employee_id' => $this->ana->id, 'via' => 'code']);
    $this->get('/me/acme/secret')->assertOk();
    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $code])->assertStatus(422)->assertExactJson(['reason' => 'expired']);
});

it('takes five tries at a code, and a sixth not even when it is right', function (): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    $code = sentProofCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $try) {
        $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $wrong])->assertStatus(422)->assertExactJson(['reason' => 'wrong']);
    }

    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $code])->assertStatus(422)->assertExactJson(['reason' => 'locked']);
    $this->get('/me/acme/secret')->assertRedirect(route('worker.proof.show', 'acme'));
});

it('takes the right code on the fifth try', function (): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    $code = sentProofCode();
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 4) as $try) {
        $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $wrong])->assertStatus(422);
    }

    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $code])->assertOk();
});

it('takes a code for less than 10 minutes', function (int $seconds, bool $passes): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    $code = sentProofCode();
    $this->travel($seconds)->seconds();

    $response = $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $code]);

    $passes ? $response->assertOk() : $response->assertStatus(422)->assertExactJson(['reason' => 'expired']);
})->with([
    '9:59' => [599, true],
    '10:00' => [600, false],
]);

it('takes only the newest code: a new one voids the one before, and is never the same', function (): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    $first = sentProofCode();
    Notification::fake();
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    $second = sentProofCode();

    expect($second)->not->toBe($first);
    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $first])->assertStatus(422)->assertExactJson(['reason' => 'wrong']);
    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $second])->assertOk();
});

it('ties a code to the session it was asked in', function (): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    $code = sentProofCode();

    $this->withCookie(config()->string('session.cookie'), Str::random(40));

    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $code])->assertStatus(422);
});

it('limits the codes a person asks for, in any session', function (): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);

    foreach (range(1, 3) as $send) {
        $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    }
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertTooManyRequests();

    $this->withCookie(config()->string('session.cookie'), Str::random(40));
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertTooManyRequests();
    Notification::assertSentOnDemandTimes(ProofCode::class, 3);
});

it('keeps only a hash of the code: not in the cache, the log nor the page; and the email address out of the log', function (): void {
    $cache = storage_path('framework/testing/proof-cache-'.getmypid());
    $log = storage_path('logs/worker-access-proof-'.getmypid().'.log');
    File::deleteDirectory($cache);
    File::delete($log);
    config(['cache.default' => 'file', 'cache.stores.file.path' => $cache, 'logging.default' => 'single', 'logging.channels.single.path' => $log, 'logging.channels.single.level' => 'debug']);
    Log::forgetChannel('single');
    enteredAs($this->ana->id, EntryVia::RequestedLink);

    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    $code = sentProofCode();
    $stored = collect(File::allFiles($cache))->map(fn (SplFileInfo $file): string => (string) File::get($file->getPathname()))->implode("\n");
    $page = json_encode($this->get(route('worker.proof.show', 'acme'))->viewData('page')['props'], JSON_THROW_ON_ERROR);
    $this->postJson(route('worker.proof.code.check', 'acme'), ['code' => $code])->assertOk();

    expect($stored)->not->toBeEmpty()->not->toContain($code)
        ->and($page)->not->toContain($code)->not->toContain('ana@acme.mx')
        ->and((string) File::get($log))->toContain('worker_access.proof_given')->not->toContain($code)->not->toContain('ana@acme.mx');

    File::deleteDirectory($cache);
    File::delete($log);
});

it('writes the code in the email in usted, with how long it lasts', function (): void {
    enteredAs($this->ana->id, EntryVia::RequestedLink);
    $this->postJson(route('worker.proof.code.send', 'acme'))->assertOk();
    $code = sentProofCode();

    Notification::assertSentOnDemand(ProofCode::class, function (ProofCode $notice, array $channels, AnonymousNotifiable $notifiable) use ($code): bool {
        $mail = $notice->toMail($notifiable);
        $text = implode(' ', [...$mail->introLines, ...$mail->outroLines]);

        return $channels === ['mail'] && $mail->subject === 'Su código para confirmar que es usted'
            && str_contains($text, $code) && str_contains($text, '10 minutos') && str_contains($text, 'Si usted no lo pidió');
    });
});
