<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\RequestCorrection;
use App\Modules\Attendance\Actions\RevokeDevice;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Http\Controllers\CorrectionRequestController;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SoftAuthenticator;

mutates(RequestCorrection::class, CorrectionRequestController::class);

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    // Wednesday 11 November 2026, 12:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
    $this->phone = new SoftAuthenticator;
    $this->device = correctionDevice($this->phone, $this->ana);
    $this->start = correctionPunch($this->ana, MarkType::Start, '2026-11-10 09:20');
});

/** The phone's key, linked to `$person` in their company. */
function correctionDevice(SoftAuthenticator $phone, Employee $person): AttendanceDevice
{
    workIn(Tenant::query()->findOrFail($person->tenant_id));
    $ceremony = resolve(WebAuthnCeremony::class);
    $credential = $ceremony->verifyRegistration($person->id, $phone->create($ceremony->registrationOptions($person->id, 'Ana')));

    return AttendanceDevice::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id,
        'credential_id' => $credential->credentialId, 'public_key' => $credential->publicKey,
    ]);
}

/** A mark of `$person` at a local time of Mexico City, taken on site with a position. */
function correctionPunch(Employee $person, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
        'latitude' => '19.432712', 'longitude' => '-99.133298', 'accuracy_m' => 12.5, 'distance_m' => 11.25,
    ]);
}

/** Ana asks, from the action, for a correction at a local time of Mexico City. */
function anaAsks(?string $markId, MarkType $type, string $local, string $reason = 'Llegué a las 9, el QR no cargaba.'): CorrectionRequest
{
    return resolve(RequestCorrection::class)->handle(test()->ana->id, $markId, $type, CarbonImmutable::parse($local, 'America/Mexico_City'), $reason, test()->device->id);
}

/**
 * The phone signs a challenge of `$optionsRoute` for a new session of the page of Mi semana and sends it with `$body`.
 *
 * @param  array<string, mixed>  $body
 */
function correctionSigned(SoftAuthenticator $phone, string $userHandle, array $body, string $optionsRoute = 'attendance.device.correction.options', string $slug = 'acme'): TestResponse
{
    $cookie = config()->string('session.cookie');
    $session = test()->get(route('attendance.device.week.show', $slug))->getCookie($cookie)?->getValue() ?? throw new LogicException('No session.');
    $options = test()->withCredentials()->withCookie($cookie, $session)->postJson(route($optionsRoute, $slug))->assertOk()->json();

    return test()->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.correction.store', $slug), ['credential' => $phone->get($options, $userHandle), ...$body]);
}

/**
 * Ana asks with her phone for the start she marked late to be 9:00.
 *
 * @param  array<string, mixed>  $body
 */
function anaSends(array $body = []): TestResponse
{
    return correctionSigned(test()->phone, test()->ana->id, [
        'mark_id' => test()->start->id, 'mark_type' => 'start', 'proposed_at' => '2026-11-10 09:00', 'reason' => 'Llegué a las 9, el QR no cargaba.', ...$body,
    ]);
}

describe('asking', function (): void {
    it('keeps the request pending, with the mark, the type, the time in UTC, the reason and the device, logging ids only', function (): void {
        Log::spy();

        $request = anaAsks($this->start->id, MarkType::Start, '2026-11-10 09:00');

        expect($request->fresh())
            ->status->toBe(CorrectionStatus::Pending)
            ->employee_id->toBe($this->ana->id)
            ->mark_id->toBe($this->start->id)
            ->type->toBe(MarkType::Start)
            ->reason->toBe('Llegué a las 9, el QR no cargaba.')
            ->device_id->toBe($this->device->id)
            ->and($request->fresh()?->proposed_at->toIso8601String())->toBe('2026-11-10T15:00:00+00:00');
        Log::shouldHaveReceived('info')->once()->with('attendance.correction_requested', ['request_id' => $request->id, 'employee_id' => $this->ana->id, 'mark_id' => $this->start->id]);
    });

    it('asks for a mark the person forgot, with its type', function (): void {
        $request = anaAsks(null, MarkType::End, '2026-11-10 18:00', 'Olvidé marcar mi salida.');

        expect($request->fresh())->mark_id->toBeNull()->type->toBe(MarkType::End);
    });

    it('keeps a local time near midnight on its UTC instant: 23:50 in Mexico City is 05:50 of the next day in UTC', function (): void {
        $request = anaAsks(null, MarkType::End, '2026-11-09 23:50', 'Salí casi a medianoche.');

        expect($request->fresh()?->proposed_at->toIso8601String())->toBe('2026-11-10T05:50:00+00:00');
    });

    it('rejects a time in the future and one older than 35 days', function (string $local, string $reason): void {
        expect(fn () => anaAsks(null, MarkType::Start, $local))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe($reason))
            ->and(CorrectionRequest::query()->count())->toBe(0);
    })->with([
        'a minute ahead' => ['2026-11-11 12:01', 'future_time'],
        '35 days and a minute ago' => ['2026-10-07 11:59', 'too_old'],
    ]);

    it('takes a time of now and one of exactly 35 days ago', function (): void {
        anaAsks(null, MarkType::Start, '2026-11-11 12:00');
        anaAsks(null, MarkType::Start, '2026-10-07 12:00');

        expect(CorrectionRequest::query()->count())->toBe(2);
    });

    it('rejects a mark of another person, a mark already replaced and one of another type', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
        $bobs = correctionPunch($bob, MarkType::Start, '2026-11-10 08:00');
        $replaced = correctionPunch($this->ana, MarkType::End, '2026-11-10 18:00');
        AttendanceMark::factory()->correcting($replaced)->create();

        expect(fn () => anaAsks($bobs->id, MarkType::Start, '2026-11-10 09:00'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('mark_not_found'))
            ->and(fn () => anaAsks($replaced->id, MarkType::End, '2026-11-10 17:00'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('mark_not_found'))
            ->and(fn () => anaAsks($this->start->id, MarkType::End, '2026-11-10 09:00'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('type_mismatch'))
            ->and(CorrectionRequest::query()->count())->toBe(0);
    });

    it('rejects a second pending request of the same mark, and takes it again once the first is resolved', function (): void {
        $first = anaAsks($this->start->id, MarkType::Start, '2026-11-10 09:00');

        expect(fn () => anaAsks($this->start->id, MarkType::Start, '2026-11-10 08:55'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('already_requested'));

        $first->update(['status' => CorrectionStatus::Rejected, 'resolved_by' => App\Models\User::factory()->create()->id, 'resolved_at' => now(), 'note' => 'Llegaste a las 9:20.']);
        anaAsks($this->start->id, MarkType::Start, '2026-11-10 08:55');

        expect(CorrectionRequest::query()->count())->toBe(2);
    });

    it('keeps one pending request per mark in the database too', function (): void {
        anaAsks($this->start->id, MarkType::Start, '2026-11-10 09:00');

        expect(fn () => CorrectionRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'mark_id' => $this->start->id]))
            ->toThrow(QueryException::class);
    });

    it('takes ten pending requests of a person, not eleven', function (): void {
        CorrectionRequest::factory()->count(9)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        anaAsks(null, MarkType::Start, '2026-11-09 09:00');

        expect(fn () => anaAsks(null, MarkType::End, '2026-11-09 18:00'))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('too_many_pending'))
            ->and(CorrectionRequest::query()->count())->toBe(10);
    });

    it('needs a reason of 10 characters, without its spaces', function (): void {
        expect(fn () => anaAsks(null, MarkType::Start, '2026-11-10 09:00', '  Olvidé.   '))->toThrow(InvalidArgumentException::class);
    });

    it('refuses a reason over 500 characters or with control characters, whoever calls it', function (string $reason): void {
        expect(fn () => anaAsks(null, MarkType::Start, '2026-11-10 09:00', $reason))->toThrow(fn (CorrectionRejected $e) => expect($e->reason)->toBe('reason_invalid'))
            ->and(CorrectionRequest::query()->count())->toBe(0);
    })->with(['501 characters' => [str_repeat('a', 501)], 'a bell' => ["Llegué a las 9\u{0007} en punto."]]);

    it('takes a reason of exactly 500 characters, with line breaks', function (): void {
        anaAsks(null, MarkType::Start, '2026-11-10 09:00', "Llegué a las 9.\n".str_repeat('a', 484));

        expect(CorrectionRequest::query()->sole()->reason)->toHaveLength(500);
    });

    it('takes no request of a person who left', function (): void {
        $this->ana->update(['deactivated_at' => now()]);

        expect(fn () => anaAsks(null, MarkType::Start, '2026-11-10 09:00'))->toThrow(InvalidArgumentException::class);
    });
});

describe('from the device', function (): void {
    it('takes the request with its own signature and gives back the week it falls in, with the request in it', function (): void {
        $response = anaSends()->assertOk()
            ->assertJsonPath('week.start', '2026-11-09')
            ->assertJsonPath('requests.0.markId', $this->start->id)
            ->assertJsonPath('requests.0.type', 'start')
            ->assertJsonPath('requests.0.date', '2026-11-10')
            ->assertJsonPath('requests.0.time', '09:00')
            ->assertJsonPath('requests.0.status', 'pending')
            ->assertJsonPath('requests.0.note', null)
            ->assertHeader('Cache-Control', 'no-store, private');

        expect(CorrectionRequest::query()->sole())->device_id->toBe($this->device->id)->proposed_at->toIso8601String()->toBe('2026-11-10T15:00:00+00:00')
            ->and($response->getContent())->not->toContain('19.4327')->not->toContain('latitude')->not->toContain($this->device->id);
    });

    it('gives back the week asked for', function (): void {
        anaSends(['start' => '2026-11-02'])->assertOk()->assertJsonPath('week.start', '2026-11-02');
    });

    it('asks for a reason, a type and a local time, in Spanish', function (array $body, string $field, string $words): void {
        anaSends($body)->assertJsonValidationErrors([$field => $words]);
        expect(CorrectionRequest::query()->count())->toBe(0);
    })->with([
        'no reason' => [['reason' => ''], 'reason', 'motivo'],
        'a short reason' => [['reason' => 'Olvidé.'], 'reason', 'motivo'],
        'a long reason' => [['reason' => str_repeat('a', 501)], 'reason', 'motivo'],
        'a reason with control characters' => [['reason' => "Llegué a las 9\u{0007} en punto."], 'reason', 'motivo'],
        'no type' => [['mark_type' => 'lunch'], 'mark_type', 'tipo de marca'],
        'no time' => [['proposed_at' => '2026-11-10T09:00:00Z'], 'proposed_at', 'hora propuesta'],
        'no mark id' => [['mark_id' => 'x'], 'mark_id', 'marca'],
    ]);

    it('tells why a request is not taken', function (array $body, string $reason): void {
        anaSends($body)->assertUnprocessable()->assertExactJson(['reason' => $reason]);
    })->with([
        'in the future' => [['proposed_at' => '2026-11-11 12:30'], 'future_time'],
        'too old' => [['proposed_at' => '2026-10-01 09:00'], 'too_old'],
        'of another type' => [['mark_type' => 'end'], 'type_mismatch'],
    ]);

    it('tells the person when they already asked, and when they have ten waiting', function (): void {
        anaSends()->assertOk();
        anaSends()->assertUnprocessable()->assertExactJson(['reason' => 'already_requested']);

        CorrectionRequest::factory()->count(9)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        anaSends(['mark_id' => null, 'mark_type' => 'end', 'proposed_at' => '2026-11-10 18:00'])->assertUnprocessable()->assertExactJson(['reason' => 'too_many_pending']);
    });

    it('answers 404 to a mark of another person', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

        anaSends(['mark_id' => correctionPunch($bob, MarkType::Start, '2026-11-10 08:00')->id])->assertNotFound();
        expect(CorrectionRequest::query()->count())->toBe(0);
    });

    it('answers 404 to a signature replayed, and to one of a challenge given to read the week', function (): void {
        $cookie = config()->string('session.cookie');
        $session = $this->get(route('attendance.device.week.show', 'acme'))->getCookie($cookie)?->getValue();
        $options = $this->withCredentials()->withCookie($cookie, (string) $session)->postJson(route('attendance.device.correction.options', 'acme'))->json();
        $body = ['credential' => $this->phone->get($options, $this->ana->id), 'mark_id' => null, 'mark_type' => 'end', 'proposed_at' => '2026-11-10 18:00', 'reason' => 'Olvidé marcar mi salida.'];

        $this->withCredentials()->withCookie($cookie, (string) $session)->postJson(route('attendance.device.correction.store', 'acme'), $body)->assertOk();
        $this->withCredentials()->withCookie($cookie, (string) $session)->postJson(route('attendance.device.correction.store', 'acme'), $body)->assertNotFound();
        correctionSigned($this->phone, $this->ana->id, [...$body, 'proposed_at' => '2026-11-09 18:00'], 'attendance.device.week.options')->assertNotFound();

        expect(CorrectionRequest::query()->count())->toBe(1);
    });

    it('answers the same 404 to a revoked device, a person who left, a key nobody linked and a key of another company', function (): void {
        $globex = Tenant::factory()->create(['slug' => 'globex']);
        $bob = EmployeeFactory::new()->create(['tenant_id' => $globex->id]);
        $bobPhone = new SoftAuthenticator;
        correctionDevice($bobPhone, $bob);
        workIn($this->tenant);
        $body = ['mark_id' => null, 'mark_type' => 'end', 'proposed_at' => '2026-11-10 18:00', 'reason' => 'Olvidé marcar mi salida.'];

        correctionSigned($bobPhone, $bob->id, $body)->assertNotFound();
        correctionSigned(new SoftAuthenticator, $this->ana->id, $body)->assertNotFound();
        correctionSigned($this->phone, $this->ana->id, $body, slug: 'globex')->assertNotFound();
        workIn($this->tenant);
        resolve(RevokeDevice::class)->handle($this->device->id);
        anaSends()->assertNotFound();

        expect(CorrectionRequest::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('logs a signature that did not verify with the company and the request only', function (): void {
        Log::spy();
        resolve(RevokeDevice::class)->handle($this->device->id);

        anaSends()->assertNotFound();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $event, array $context): bool => $event === 'attendance.correction_unverified'
            && array_keys($context) === ['tenant_id', 'request_id'] && $context['tenant_id'] === $this->tenant->id);
    });

    it('is offered from the pages of Mi semana and Marcar', function (string $route): void {
        $this->get(route($route, 'acme'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('correctionOptionsUrl', route('attendance.device.correction.options', 'acme'))
            ->where('correctionUrl', route('attendance.device.correction.store', 'acme')));
    })->with(['attendance.device.week.show', 'attendance.device.punch.home']);

    it('gives Marcar the company\'s time zone, to read the time of a mark refused on its clock', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))->assertInertia(fn (AssertableInertia $page) => $page->where('timezone', 'America/Mexico_City'));
    });

    it('has its own limiter: generous per address and per key', function (): void {
        $request = Request::create(route('attendance.device.correction.store', 'acme'), 'POST', ['credential' => ['id' => 'abc']]);
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
        $limits = RateLimiter::limiter('attendance-correction')?->__invoke($request);

        expect(Route::getRoutes()->getByName('attendance.device.correction.store')?->gatherMiddleware())->toContain('throttle:attendance-correction')
            ->and($limits)->toHaveCount(2)
            ->and($limits[0])->maxAttempts->toBe(300)->key->toBe('attendance-correction-ip:'.$request->ip())
            ->and($limits[1])->maxAttempts->toBe(30)->key->toBe('attendance-correction-key:'.hash('sha256', 'abc'));
    });
});

describe('in Mi semana', function (): void {
    it('shows the person their own requests of the week with RH\'s note, never another person\'s', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
        CorrectionRequest::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $bob->id, 'proposed_at' => CarbonImmutable::parse('2026-11-10 10:00', 'America/Mexico_City')->utc()]);
        $rejected = anaAsks(null, MarkType::End, '2026-11-10 18:00', 'Olvidé marcar mi salida.');
        $rejected->update(['status' => CorrectionStatus::Rejected, 'resolved_by' => App\Models\User::factory()->create()->id, 'resolved_at' => now(), 'note' => 'Saliste a las 17:00.']);
        anaAsks(null, MarkType::Start, '2026-11-02 09:00', 'Olvidé marcar el lunes pasado.');

        $response = weekReadBy($this->phone, $this->ana->id, '2026-11-09')->assertOk()
            ->assertJsonCount(1, 'requests')
            ->assertJsonPath('requests.0', ['id' => $rejected->id, 'markId' => null, 'type' => 'end', 'date' => '2026-11-10', 'time' => '18:00', 'status' => 'rejected', 'note' => 'Saliste a las 17:00.']);

        expect($response->getContent())->not->toContain($bob->id);
    });

    it('gives each mark its id, and a corrected one the reason of its correction (criterio 8.1)', function (): void {
        $correction = AttendanceMark::factory()->correcting($this->start)->create(['marked_at' => CarbonImmutable::parse('2026-11-10 09:00', 'America/Mexico_City')->utc(), 'reason' => 'El QR no cargaba.']);

        weekReadBy($this->phone, $this->ana->id, '2026-11-09')->assertOk()
            ->assertJsonPath('days.1.marks.0', ['id' => $correction->id, 'type' => 'start', 'time' => '09:00', 'nextDay' => false, 'corrected' => true, 'reason' => 'El QR no cargaba.']);
    });
});

/** The phone signs a read of the week of `$start`. */
function weekReadBy(SoftAuthenticator $phone, string $userHandle, string $start): TestResponse
{
    $cookie = config()->string('session.cookie');
    $session = test()->get(route('attendance.device.week.show', 'acme'))->getCookie($cookie)?->getValue() ?? throw new LogicException('No session.');
    $options = test()->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.week.options', 'acme'))->json();

    return test()->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.week.read', 'acme'), ['credential' => $phone->get($options, $userHandle), 'start' => $start]);
}
