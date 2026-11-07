<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\RevokeDevice;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Http\Controllers\WeekController;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\PersonWeek;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SoftAuthenticator;

mutates(WeekController::class, PersonWeek::class);

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    // Wednesday 11 November 2026, 12:00 in Mexico City: the week of 2 November is over, the one of the 9th is not.
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
    // Eight hours Monday to Friday, weekends off.
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->phone = new SoftAuthenticator;
    $this->device = weekDevice($this->phone, $this->ana);
});

/** The phone's key, linked to `$person` in their company. */
function weekDevice(SoftAuthenticator $phone, Employee $person): AttendanceDevice
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
function weekPunch(Employee $person, MarkType $type, string $local): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => $person->tenant_id, 'employee_id' => $person->id, 'work_center_id' => $person->work_center_id,
        'type' => $type, 'marked_at' => CarbonImmutable::parse($local, 'America/Mexico_City')->utc(),
        'latitude' => '19.432712', 'longitude' => '-99.133298', 'accuracy_m' => 12.5, 'distance_m' => 11.25,
    ]);
}

/** The session the page of Mi semana opens. */
function weekSession(string $slug = 'acme'): string
{
    return test()->get(route('attendance.device.week.show', $slug))->assertOk()->getCookie(config()->string('session.cookie'))?->getValue()
        ?? throw new LogicException('The page opened no session.');
}

/**
 * The phone signs a challenge of the week's options for its session and sends it to `$url` with `$body`.
 *
 * @param  array<string, mixed>  $body
 */
function weekSigned(SoftAuthenticator $phone, string $userHandle, string $url, array $body = [], ?string $session = null, string $slug = 'acme'): TestResponse
{
    $cookie = config()->string('session.cookie');
    $session ??= weekSession($slug);
    $options = test()->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.week.options', $slug))->assertOk()->json();

    return test()->withCredentials()->withCookie($cookie, $session)->postJson($url, ['credential' => $phone->get($options, $userHandle), ...$body]);
}

/**
 * Ana asks for a week with her phone.
 *
 * @param  array<string, mixed>  $body
 */
function anaReadsWeek(array $body = []): TestResponse
{
    return weekSigned(test()->phone, test()->ana->id, route('attendance.device.week.read', 'acme'), $body);
}

function anaAnswers(string $answer, string $start = '2026-11-02', array $body = []): TestResponse
{
    return weekSigned(test()->phone, test()->ana->id, route("attendance.device.week.{$answer}", ['acme', $start]), $body);
}

/** Last week: Monday with a break and an hour over, Tuesday of 14 hours, and a Sunday night on her rest day that ends on Monday. */
function anaWorkedLastWeek(): void
{
    $ana = test()->ana;
    weekPunch($ana, MarkType::Start, '2026-11-02 09:00');
    weekPunch($ana, MarkType::BreakStart, '2026-11-02 14:00');
    weekPunch($ana, MarkType::BreakEnd, '2026-11-02 15:00');
    weekPunch($ana, MarkType::End, '2026-11-02 19:00');
    weekPunch($ana, MarkType::Start, '2026-11-03 07:00');
    weekPunch($ana, MarkType::End, '2026-11-03 21:00');
    weekPunch($ana, MarkType::Start, '2026-11-08 22:00');
    weekPunch($ana, MarkType::End, '2026-11-09 06:00');
}

describe('the page', function (): void {
    it('opens with nothing of anybody until a key signs', function (): void {
        $this->get(route('attendance.device.week.show', 'acme'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/week')
                ->where('company', 'Acme')
                ->where('start', null)
                ->where('optionsUrl', route('attendance.device.week.options', 'acme'))
                ->where('weekUrl', route('attendance.device.week.read', 'acme'))
                ->where('punchUrl', route('attendance.device.punch.home', 'acme'))
                ->missing('week')
                ->missing('days'));
    });

    it('opens on the week asked for', function (): void {
        $this->get(route('attendance.device.week.show', ['acme', '2026-11-05']))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('start', '2026-11-05'));
    });

    it('answers 404 to a date that does not exist and to a company that does not exist', function (): void {
        $this->get('/a/acme/week/2026-02-30')->assertNotFound();
        $this->get('/a/acme/week/ayer')->assertNotFound();
        $this->get(route('attendance.device.week.show', 'nadie'))->assertNotFound();
        $this->postJson(route('attendance.device.week.options', 'nadie'))->assertNotFound();
    });

    it('is linked from the screen of Marcar', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('weekUrl', route('attendance.device.week.show', 'acme')));
    });
});

describe('reading the week', function (): void {
    it('gives the person whose key signed their marks, hours, flags and answer: never a position', function (): void {
        anaWorkedLastWeek();

        $response = anaReadsWeek(['start' => '2026-11-02'])->assertOk()
            ->assertJsonPath('name', 'Ana López')
            ->assertJsonPath('week', ['start' => '2026-11-02', 'end' => '2026-11-08'])
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('answeredAt', null)
            ->assertJsonPath('disputeReason', null)
            ->assertJsonCount(7, 'days')
            ->assertJsonPath('days.0', [
                'date' => '2026-11-02',
                'marks' => [
                    ['type' => 'start', 'time' => '09:00', 'nextDay' => false, 'corrected' => false],
                    ['type' => 'break_start', 'time' => '14:00', 'nextDay' => false, 'corrected' => false],
                    ['type' => 'break_end', 'time' => '15:00', 'nextDay' => false, 'corrected' => false],
                    ['type' => 'end', 'time' => '19:00', 'nextDay' => false, 'corrected' => false],
                ],
                'workedMinutes' => 540, 'scheduledMinutes' => 480, 'restDay' => false, 'missingEnd' => false,
            ])
            ->assertJsonPath('days.1.workedMinutes', 840)
            ->assertJsonPath('days.2', ['date' => '2026-11-04', 'marks' => [], 'workedMinutes' => 0, 'scheduledMinutes' => 480, 'restDay' => false, 'missingEnd' => false])
            ->assertJsonPath('days.6.marks.1', ['type' => 'end', 'time' => '06:00', 'nextDay' => true, 'corrected' => false])
            ->assertJsonPath('days.6.restDay', true)
            ->assertJsonPath('summary', [
                'ordinaryMinutes' => 960, 'overtimeMinutes' => 420, 'doubleMinutes' => 420, 'tripleMinutes' => 0, 'restDayMinutes' => 480,
                'flags' => ['day_over_12_hours', 'daily_overtime_over_4'],
            ])
            ->assertJsonPath('confirmUrl', route('attendance.device.week.confirm', ['acme', '2026-11-02']))
            ->assertJsonPath('disputeUrl', route('attendance.device.week.dispute', ['acme', '2026-11-02']))
            ->assertJsonPath('previousUrl', route('attendance.device.week.show', ['acme', '2026-10-26']))
            ->assertJsonPath('nextUrl', route('attendance.device.week.show', ['acme', '2026-11-09']))
            ->assertHeader('Cache-Control', 'no-store, private');

        expect($response->getContent())->not->toContain('19.4327')->not->toContain('99.1332')->not->toContain('latitude')
            ->not->toContain('accuracy')->not->toContain('zone')->not->toContain('distance')->not->toContain($this->device->id);
    });

    it('opens on the last week that is over when none is asked for', function (): void {
        anaReadsWeek()->assertOk()->assertJsonPath('week.start', '2026-11-02');
    });

    it('shows the current week as still open, with nothing to answer and no week after it', function (): void {
        weekPunch($this->ana, MarkType::Start, '2026-11-11 09:00');

        anaReadsWeek(['start' => '2026-11-12'])->assertOk()
            ->assertJsonPath('week.start', '2026-11-09')
            ->assertJsonPath('status', 'open')
            ->assertJsonPath('confirmUrl', null)
            ->assertJsonPath('disputeUrl', null)
            ->assertJsonPath('nextUrl', null)
            // Today's day is still open: not flagged.
            ->assertJsonPath('days.2.missingEnd', false);
    });

    it('flags a past day with no end, and marks a correction as such', function (): void {
        $start = weekPunch($this->ana, MarkType::Start, '2026-11-03 09:20');
        AttendanceMark::factory()->correcting($start)->create(['marked_at' => CarbonImmutable::parse('2026-11-03 09:00', 'America/Mexico_City')->utc()]);
        weekPunch($this->ana, MarkType::Start, '2026-11-05 09:00');
        weekPunch($this->ana, MarkType::End, '2026-11-05 17:00');

        anaReadsWeek(['start' => '2026-11-02'])->assertOk()
            ->assertJsonPath('days.1.marks', [['type' => 'start', 'time' => '09:00', 'nextDay' => false, 'corrected' => true]])
            ->assertJsonPath('days.1.missingEnd', true)
            ->assertJsonPath('days.3.missingEnd', false);
    });

    it('tells the answer already given, with its moment and the person\'s own reason', function (): void {
        WeeklyConfirmation::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'week_start' => '2026-11-02', 'status' => WeekStatus::Disputed,
            'disputed_at' => CarbonImmutable::parse('2026-11-10 16:30', 'UTC'), 'reason' => 'El martes salí a las 20:00.', 'device_id' => $this->device->id,
        ]);

        anaReadsWeek(['start' => '2026-11-02'])->assertOk()
            ->assertJsonPath('status', 'disputed')
            ->assertJsonPath('answeredAt', '2026-11-10 10:30')
            ->assertJsonPath('disputeReason', 'El martes salí a las 20:00.')
            ->assertJsonPath('confirmUrl', null)
            ->assertJsonPath('disputeUrl', null);
    });

    it('refuses a start that is not a date', function (): void {
        anaReadsWeek(['start' => '2026-02-30'])->assertUnprocessable()->assertJsonValidationErrors('start');
    });
});

describe('answering', function (): void {
    it('confirms the week with its own signature and gives the week back confirmed', function (): void {
        anaWorkedLastWeek();

        anaAnswers('confirm')->assertOk()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('answeredAt', '2026-11-11 12:00')
            ->assertJsonPath('confirmUrl', null)
            ->assertJsonPath('disputeUrl', route('attendance.device.week.dispute', ['acme', '2026-11-02']));

        expect(WeeklyConfirmation::query()->sole())->status->toBe(WeekStatus::Confirmed)->device_id->toBe($this->device->id);
    });

    it('disputes the week with a reason', function (): void {
        anaAnswers('dispute', body: ['reason' => 'El martes salí a las 20:00.'])->assertOk()
            ->assertJsonPath('status', 'disputed')
            ->assertJsonPath('disputeReason', 'El martes salí a las 20:00.');
    });

    it('asks for the reason of a dispute, in Spanish', function (array $body): void {
        anaAnswers('dispute', body: $body)->assertJsonValidationErrors(['reason' => 'motivo']);
        expect(WeeklyConfirmation::query()->count())->toBe(0);
    })->with(['none' => [[]], 'too short' => [['reason' => 'No es así']], 'too long' => [['reason' => str_repeat('a', 1001)]]]);

    it('rejects the current week', function (string $answer): void {
        anaAnswers($answer, '2026-11-09', ['reason' => 'El martes salí a las 20:00.'])->assertUnprocessable()->assertExactJson(['reason' => 'week_not_over']);
        expect(WeeklyConfirmation::query()->count())->toBe(0);
    })->with(['confirm', 'dispute']);

    it('rejects confirming over a dispute', function (): void {
        anaAnswers('dispute', body: ['reason' => 'El martes salí a las 20:00.'])->assertOk();

        anaAnswers('confirm')->assertUnprocessable()->assertExactJson(['reason' => 'week_disputed']);
    });

    it('answers 404 to a signature replayed, and confirms once', function (): void {
        $cookie = config()->string('session.cookie');
        $session = weekSession();
        $options = $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.week.options', 'acme'))->json();
        $credential = $this->phone->get($options, $this->ana->id);

        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.week.read', 'acme'), ['credential' => $credential])->assertOk();
        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.week.confirm', ['acme', '2026-11-02']), ['credential' => $credential])->assertNotFound();

        expect(WeeklyConfirmation::query()->count())->toBe(0);
    });

    it('answers 404 to a signature of a challenge given to mark, not to the week', function (): void {
        $cookie = config()->string('session.cookie');
        $session = weekSession();
        $options = $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.options', 'acme'))->json();

        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.week.confirm', ['acme', '2026-11-02']), ['credential' => $this->phone->get($options, $this->ana->id)])
            ->assertNotFound();
    });

    it('answers 404 to a week that is not a date', function (): void {
        anaAnswers('confirm', '2026-13-01')->assertNotFound();
    });
});

describe('who reads and answers', function (): void {
    it('answers the same 404 to a revoked device and to a person who left', function (string $how): void {
        $how === 'revoked' ? resolve(RevokeDevice::class)->handle($this->device->id) : $this->ana->update(['deactivated_at' => now()]);

        anaReadsWeek()->assertNotFound();
        anaAnswers('confirm')->assertNotFound();
        expect(WeeklyConfirmation::query()->count())->toBe(0);
    })->with(['revoked', 'left']);

    it('answers the same 404 to a key nobody linked', function (): void {
        weekSigned(new SoftAuthenticator, $this->ana->id, route('attendance.device.week.read', 'acme'))->assertNotFound();
    });

    it('answers 404 to a key of another company: B cannot read or confirm its week through A, nor A through B', function (): void {
        $globex = Tenant::factory()->create(['slug' => 'globex']);
        $bob = EmployeeFactory::new()->create(['tenant_id' => $globex->id]);
        $bobPhone = new SoftAuthenticator;
        weekDevice($bobPhone, $bob);
        workIn($this->tenant);

        weekSigned($bobPhone, $bob->id, route('attendance.device.week.read', 'acme'))->assertNotFound();
        weekSigned($bobPhone, $bob->id, route('attendance.device.week.confirm', ['acme', '2026-11-02']))->assertNotFound();
        weekSigned($this->phone, $this->ana->id, route('attendance.device.week.confirm', ['globex', '2026-11-02']), slug: 'globex')->assertNotFound();

        expect(WeeklyConfirmation::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('logs a signature that did not verify with the company and the request only', function (): void {
        Log::spy();
        resolve(RevokeDevice::class)->handle($this->device->id);

        anaAnswers('confirm')->assertNotFound();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $event, array $context): bool => $event === 'attendance.week_unverified'
            && array_keys($context) === ['tenant_id', 'request_id'] && $context['tenant_id'] === $this->tenant->id);
    });

    it('gives options bound to nobody and never cached', function (): void {
        $response = $this->postJson(route('attendance.device.week.options', 'acme'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        expect($response->json('userVerification'))->toBe('required')->and($response->json('allowCredentials') ?? [])->toBe([]);
    });
});

describe('throttling', function (): void {
    it('has its own limiter: generous per address and per key', function (): void {
        $request = Request::create(route('attendance.device.week.read', 'acme'), 'POST', ['credential' => ['id' => 'abc']]);
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
        $limits = RateLimiter::limiter('attendance-week')?->__invoke($request);

        expect(Route::getRoutes()->getByName('attendance.device.week.read')?->gatherMiddleware())->toContain('throttle:attendance-week')
            ->and($limits)->toHaveCount(2)
            ->and($limits[0])->maxAttempts->toBe(300)->key->toBe('attendance-week-ip:'.$request->ip())
            ->and($limits[1])->maxAttempts->toBe(30)->key->toBe('attendance-week-key:'.hash('sha256', 'abc'));
    });
});
