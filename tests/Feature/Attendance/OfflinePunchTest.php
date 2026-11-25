<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceOfflineMark;
use App\Modules\Attendance\Support\OfflineChallenge;
use App\Modules\Attendance\Support\OfflineTicket;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Support\WebAuthnCeremony;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\SoftAuthenticator;

/*
 * `POST me/{slug}/punch/offline`: the delivery of marks a phone made without a connection. No session, no cookies and no
 * forgery check: after a blackout there may be none, and what authorizes each mark is its signature and its ticket.
 */
beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = opkPerson($this->tenant, $this->plant);
    agreementAccepted($this->ana['employee']->id);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));
});

/** A person with a passkey of their own, as the phone keeps it. */
function opkPerson(Tenant $tenant, WorkCenter $center): array
{
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $phone = new SoftAuthenticator;
    $ceremony = resolve(WebAuthnCeremony::class);
    $registered = $ceremony->verifyRegistration($employee->id, $phone->create($ceremony->registrationOptions($employee->id, 'Ana')));
    WorkerCredential::factory()->create([
        'tenant_id' => $tenant->id, 'employee_id' => $employee->id, 'credential_id' => $registered->credentialId, 'public_key' => $registered->publicKey,
        'sign_count' => $registered->signCount, 'backup_eligible' => $registered->backupEligible, 'backup_state' => $registered->backupState,
    ]);

    return ['employee' => $employee, 'phone' => $phone];
}

/**
 * One element of the delivery, as the phone sends it: signed with the person's passkey over the challenge of its fields.
 *
 * @param  array<string, mixed>  $o
 * @return array<string, mixed>
 */
function opkItem(array $o = []): array
{
    $who = $o['who'] ?? test()->ana;
    $type = $o['type'] ?? MarkType::Start;
    $declared = CarbonImmutable::parse('2026-11-09 '.($o['declared'] ?? '14:00:00'), 'UTC')->getTimestamp();
    $now = CarbonImmutable::now();
    test()->travelTo(CarbonImmutable::parse('2026-11-09 13:00:00', 'UTC'));
    $ticket = $o['ticket'] ?? resolve(OfflineTicket::class)->batch($who['employee']->id)[0]['ticket'];
    test()->travelTo($now);
    $seq = $o['seq'] ?? 0;
    $position = $o['position'] ?? [null, null, null];
    $challenge = OfflineChallenge::hash(OfflineChallenge::canonical(test()->tenant->id, $who['employee']->id, test()->plant->id, $type, null, $declared, $position[0], $position[1], $position[2], 1, $seq, null, $ticket));

    return [
        'seq' => $seq,
        'type' => $type->value,
        'ticket' => $ticket,
        'center_id' => test()->plant->id,
        'declared_at' => $declared,
        'agreement_version' => 1,
        ...($position[0] === null ? [] : ['lat_e5' => $position[0], 'lng_e5' => $position[1], 'accuracy_m' => $position[2]]),
        'signer' => 'passkey',
        'credential' => $who['phone']->get(['challenge' => Base64UrlSafe::encodeUnpadded($challenge)], $who['employee']->id, tamper: $o['tamper'] ?? false),
    ];
}

/**
 * @param  array<string, mixed>  $body
 */
function opkDeliver(array $body, string $slug = 'acme'): TestResponse
{
    return test()->postJson(route('attendance.device.punch.offline', $slug), $body);
}

it('delivers with no session, no cookie and no forgery check, and answers the results and what the phone needs next', function (): void {
    config(['session.driver' => 'database']);

    $response = opkDeliver(['items' => [opkItem()]])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('Set-Cookie');

    expect($response->json('results'))->toBe([['seq' => 0, 'status' => 'received']])
        ->and($response->json('tickets'))->toHaveCount(8)
        ->and($response->json('context.employeeId'))->toBe($this->ana['employee']->id)
        ->and(DB::table('sessions')->count())->toBe(0)
        ->and(AttendanceMark::query()->count())->toBe(1);
});

it('delivers a position from a center with no zones flagged zones_not_configured, and one with no position flagged no_position', function (): void {
    opkDeliver(['items' => [opkItem(['position' => [1943271, -9913320, 10]]), opkItem(['seq' => 1, 'type' => MarkType::End, 'declared' => '14:30:00'])]])->assertOk();

    expect(AttendanceOfflineMark::query()->orderBy('seq')->get()->map(fn (AttendanceOfflineMark $mark): array => $mark->review_flags->map->value->all())->all())
        ->toBe([['zones_not_configured'], ['no_position']]);
});

it('does not hand out tickets nor context when no signature verified', function (): void {
    $response = opkDeliver(['items' => [opkItem(['tamper' => true])]])->assertOk();

    expect($response->json('results'))->toBe([['seq' => 0, 'status' => 'rejected', 'reason' => 'signature_invalid']])
        ->and($response->json())->not->toHaveKeys(['tickets', 'context']);
});

it('answers each mark on its own: a signature that does not verify does not drop the others', function (): void {
    $response = opkDeliver(['items' => [opkItem(['seq' => 0]), opkItem(['seq' => 1, 'type' => MarkType::End, 'declared' => '14:30:00', 'tamper' => true]), opkItem(['seq' => 2, 'type' => MarkType::End, 'declared' => '15:00:00'])]])->assertOk();

    expect(array_column($response->json('results'), 'status'))->toBe(['received', 'rejected', 'received'])
        ->and(AttendanceMark::query()->count())->toBe(2);
});

it('processes the marks in the order of their own number, whatever the order they came in', function (): void {
    $response = opkDeliver(['items' => [opkItem(['seq' => 5, 'type' => MarkType::End, 'declared' => '15:00:00']), opkItem(['seq' => 3])]])->assertOk();

    expect(array_column($response->json('results'), 'seq'))->toBe([3, 5])
        ->and(array_column($response->json('results'), 'status'))->toBe(['received', 'received']);
});

/** @return array<string, mixed> an element that passes the shape and is turned away at the ticket */
function opkShape(int $seq): array
{
    return [
        'seq' => $seq, 'type' => 'start', 'ticket' => 'not-a-ticket', 'center_id' => test()->plant->id, 'declared_at' => 1_794_000_000, 'agreement_version' => 1,
        'signer' => 'passkey', 'credential' => ['id' => 'a', 'response' => ['clientDataJSON' => 'a', 'authenticatorData' => 'a', 'signature' => 'a']],
    ];
}

describe('the envelope', function (): void {
    it('takes 20 marks and refuses 21 with the errors of the envelope', function (): void {
        $twenty = array_map(opkShape(...), range(0, 19));

        opkDeliver(['items' => $twenty])->assertOk()->assertJsonCount(20, 'results');
        opkDeliver(['items' => [...$twenty, opkShape(20)]])->assertUnprocessable()->assertJsonValidationErrors('items')->assertJsonStructure(['message', 'errors']);
    });

    it('refuses an empty delivery, a repeated number and a field that does not fit', function (Closure $build, string $error): void {
        opkDeliver($build(opkShape(1)))->assertUnprocessable()->assertJsonValidationErrors($error);
    })->with([
        'no marks' => [fn (array $mark) => ['items' => []], 'items'],
        'a repeated number' => [fn (array $mark) => ['items' => [$mark, $mark]], 'items.0.seq'],
        'a bar in the ticket' => [fn (array $mark) => ['items' => [[...$mark, 'ticket' => 'v1.a|b.c']]], 'items.0.ticket'],
        'a bar in the QR' => [fn (array $mark) => ['items' => [[...$mark, 'qr' => '1.a|b']]], 'items.0.qr'],
        'a position of two numbers' => [fn (array $mark) => ['items' => [[...$mark, 'lat_e5' => 1943271, 'lng_e5' => -9913320]]], 'items.0.accuracy_m'],
        'a passkey with no assertion' => [fn (array $mark) => ['items' => [[...$mark, 'credential' => null]]], 'items.0.credential'],
        'a device key with no pass' => [fn (array $mark) => ['items' => [[...$mark, 'signer' => 'device_key', 'credential' => null]]], 'items.0.pass'],
        'a note of 501 characters' => [fn (array $mark) => ['items' => [[...$mark, 'justification' => str_repeat('a', 501)]]], 'items.0.justification'],
    ]);

    it('names the fields of the delivery in Spanish in its errors', function (): void {
        $response = opkDeliver(['items' => [['seq' => 0, 'type' => 'start', 'ticket' => '', 'signer' => 'passkey']]])->assertUnprocessable();

        $errors = $response->json('errors');

        expect($errors['items.0.ticket'][0])->toContain('boleto')
            ->and($errors['items.0.declared_at'][0])->toContain('hora declarada')
            ->and($errors['items.0.center_id'][0])->toContain('centro de trabajo')
            ->and($errors['items.0.agreement_version'][0])->toContain('versión del acuerdo')
            ->and($errors['items.0.credential'][0])->toContain('llave de acceso');
    });

    it('refuses a body of more than 64 KB before reading it', function (): void {
        opkDeliver(['items' => [opkShape(1)], 'padding' => str_repeat('a', 70_000)])->assertStatus(413);
    });

    it('refuses a delivery with the tickets of two people', function (): void {
        $beto = opkPerson($this->tenant, $this->plant);
        agreementAccepted($beto['employee']->id);

        opkDeliver(['items' => [opkItem(['seq' => 0]), opkItem(['seq' => 1, 'who' => $beto])]])->assertUnprocessable()->assertJsonPath('errors.items.0', 'mixed_people');

        expect(AttendanceOfflineMark::query()->count())->toBe(0);
    });
});

it('refuses a request from another site and takes one from the same origin', function (): void {
    $body = ['items' => [opkItem()]];

    $this->withHeader('Sec-Fetch-Site', 'cross-site')->postJson(route('attendance.device.punch.offline', 'acme'), $body)->assertForbidden();
    $this->withHeader('Sec-Fetch-Site', 'same-origin')->postJson(route('attendance.device.punch.offline', 'acme'), $body)->assertOk();
});

describe('the company', function (): void {
    it('answers ticket_invalid to the ticket of company A sent to B, and writes no row in either', function (): void {
        Tenant::factory()->create(['slug' => 'beta']);

        $response = opkDeliver(['items' => [opkItem()]], 'beta')->assertOk();

        expect($response->json('results.0.reason'))->toBe('ticket_invalid')
            ->and(AttendanceOfflineMark::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('answers a JSON 404 to a company that does not exist or has no registro de jornada', function (): void {
        opkDeliver(['items' => [opkItem()]], 'nowhere')->assertNotFound()->assertHeader('Content-Type', 'application/json');

        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));

        opkDeliver(['items' => [opkItem()]])->assertNotFound()->assertHeader('Content-Type', 'application/json');
    });
});

it('answers JSON, with no redirect nor session, to an error of the envelope or an unknown company when it is not asked for', function (): void {
    config(['session.driver' => 'database']);

    $this->post(route('attendance.device.punch.offline', 'acme'), ['items' => []])->assertUnprocessable()->assertHeader('Content-Type', 'application/json')->assertHeaderMissing('Set-Cookie');
    $this->post(route('attendance.device.punch.offline', 'nowhere'), [])->assertNotFound()->assertHeader('Content-Type', 'application/json');

    expect(DB::table('sessions')->count())->toBe(0);
});

it('keeps delivering with the offline feature of the phone turned off', function (): void {
    config(['attendance.offline.enabled' => false]);

    $response = opkDeliver(['items' => [opkItem()]])->assertOk();

    expect($response->json('results.0.status'))->toBe('received')->and($response->json('context.offline'))->toBeFalse();
});

it('takes no session nor forgery check, and refuses another site', function (): void {
    $middleware = Route::gatherRouteMiddleware(Route::getRoutes()->getByName('attendance.device.punch.offline') ?? throw new LogicException('The route is missing.'));

    expect($middleware)->not->toContain(Illuminate\Session\Middleware\StartSession::class)
        ->not->toContain(Illuminate\Cookie\Middleware\EncryptCookies::class)
        ->not->toContain(Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class)
        ->not->toContain(Illuminate\View\Middleware\ShareErrorsFromSession::class)
        ->not->toContain(Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class)
        ->toContain(App\Http\Middleware\RejectCrossSiteRequests::class);
});

it('limits the address to 600 requests a minute, counting requests and not marks', function (): void {
    $request = Request::create(route('attendance.device.punch.offline', 'acme'), 'POST');
    $limit = Illuminate\Support\Facades\RateLimiter::limiter('attendance-offline')?->__invoke($request);

    expect($limit)->maxAttempts->toBe(600)->decaySeconds->toBe(60)->key->toBe('attendance-offline:'.$request->ip());

    foreach (range(1, 600) as $ignored) {
        opkDeliver([]);
    }

    opkDeliver(['items' => [opkShape(0)]])->assertTooManyRequests();
});
