<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\RevokeDevice;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Http\Controllers\MarkController;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Support\PunchDay;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Attendance\Support\WebAuthnCeremony;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\Support\SoftAuthenticator;

mutates(MarkController::class, PunchDay::class);

/** Inside the plant's 50 m zone (about 11 m north of its center), and about 111 m north of it, outside. */
const PUNCH_INSIDE = ['lat' => 19.4327, 'lng' => -99.1332, 'accuracy' => 12];
const PUNCH_OUTSIDE = ['lat' => 19.4336, 'lng' => -99.1332, 'accuracy' => 12];

beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    // Monday 9 November 2026, 09:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->zone = AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Ana López']);
    $this->phone = new SoftAuthenticator;
    $this->device = punchDevice($this->phone, $this->ana);
});

/** The phone's key, linked to `$person` in their company. */
function punchDevice(SoftAuthenticator $phone, Employee $person): AttendanceDevice
{
    workIn(Tenant::query()->findOrFail($person->tenant_id));
    $ceremony = resolve(WebAuthnCeremony::class);
    $credential = $ceremony->verifyRegistration($person->id, $phone->create($ceremony->registrationOptions($person->id, 'Ana')));

    return AttendanceDevice::factory()->create([
        'tenant_id' => $person->tenant_id,
        'employee_id' => $person->id,
        'credential_id' => $credential->credentialId,
        'public_key' => $credential->publicKey,
    ]);
}

/** The session the page of Marcar opens, as the browser keeps it in its cookie. */
function punchSession(string $slug = 'acme'): string
{
    return test()->get(route('attendance.device.punch.home', $slug))->assertOk()->getCookie(config()->string('session.cookie'))?->getValue()
        ?? throw new LogicException('The page opened no session.');
}

/** The center's code on screen right now. */
function punchQr(string $tenantId, string $workCenterId): string
{
    return QrToken::issue($tenantId, $workCenterId, CarbonImmutable::now());
}

/**
 * The phone marks as the page does: options for its session, the key signs (with `$userHandle`), and the mark is sent
 * with `$body`.
 *
 * @param  array<string, mixed>  $body
 */
function punchWith(SoftAuthenticator $phone, string $userHandle, array $body, ?string $session = null, string $slug = 'acme'): TestResponse
{
    $cookie = config()->string('session.cookie');
    $session ??= punchSession($slug);
    $options = test()->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.options', $slug))->assertOk()->json();

    return test()->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.store', $slug), [
        'type' => 'start',
        'credential' => $phone->get($options, $userHandle),
        ...$body,
    ]);
}

/**
 * Ana marks on site with the plant's code, inside the zone, unless told otherwise.
 *
 * @param  array<string, mixed>  $body
 */
function anaPunches(array $body = []): TestResponse
{
    return punchWith(test()->phone, test()->ana->id, ['q' => punchQr(test()->tenant->id, test()->plant->id), ...PUNCH_INSIDE, ...$body]);
}

describe('the page', function (): void {
    it('opens for a device that has not marked here yet, asking for the location only when marking', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/punch')
                ->where('company', 'Acme')
                ->where('qr', null)
                ->where('person', null)
                ->where('askLocation', true)
                ->where('optionsUrl', route('attendance.device.punch.options', 'acme'))
                ->where('punchUrl', route('attendance.device.punch.store', 'acme')));
    });

    it('opens from the QR of the center\'s screen carrying its code', function (): void {
        $this->get(route('attendance.device.punch.show', ['acme', 'q' => 'abc123']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/punch')->where('qr', 'abc123'));
    });

    it('drops a code that cannot be one', function (): void {
        $this->get(route('attendance.device.punch.show', ['acme', 'q' => str_repeat('a', 200)]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('qr', null));
        $this->get('/a/acme/punch?q[]=x')->assertInertia(fn (AssertableInertia $page) => $page->where('qr', null));
    });

    it('answers 404 for a company that does not exist', function (): void {
        $this->get(route('attendance.device.punch.home', 'nadie'))->assertNotFound();
        $this->postJson(route('attendance.device.punch.options', 'nadie'))->assertNotFound();
    });

    it('remembers in this browser who marked, with their day and next step, and nothing about where', function (): void {
        $session = punchSession();
        punchWith($this->phone, $this->ana->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE], $session)->assertOk();

        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('person.name', 'Ana López')
                ->where('person.marks', [['type' => 'start', 'time' => '09:00']])
                ->where('person.next', ['break_start', 'end'])
                ->where('askLocation', true)
                ->missing('person.id')
                ->missing('person.marks.0.latitude')
                ->missing('person.marks.0.zone_id'));
    });

    it('forgets the person once their device is revoked or they leave', function (string $how): void {
        $session = punchSession();
        anaPunches()->assertOk();
        punchWith($this->phone, $this->ana->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE, 'type' => 'end'], $session)->assertOk();

        if ($how === 'revoked') {
            resolve(RevokeDevice::class)->handle($this->device->id);
        } else {
            $this->ana->update(['deactivated_at' => now()]);
        }

        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('person', null));
    })->with(['revoked', 'deactivated']);

    it('does not ask a remote person, nor trusted staff on the browser, for their location', function (string $who): void {
        if ($who === 'remote') {
            AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_mode' => WorkMode::Remote]);
        } else {
            $this->ana->update(['staff_group' => StaffGroup::Trusted]);
        }
        $session = punchSession();
        punchWith($this->phone, $this->ana->id, [], $session)->assertOk();

        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('askLocation', false)->where('person.name', 'Ana López'));
    })->with(['remote', 'trusted']);
});

describe('on site', function (): void {
    it('marks with QR and geofence: the zone, the position, its accuracy and the distance, at the server\'s time', function (): void {
        anaPunches()->assertOk()->assertExactJson([
            'marks' => [['type' => 'start', 'time' => '09:00']],
            'next' => ['break_start', 'end'],
            'flag' => null,
        ]);

        $mark = AttendanceMark::query()->sole();
        expect($mark)->employee_id->toBe($this->ana->id)->type->toBe(MarkType::Start)->method->toBe(MarkMethod::QrAndGeofence)
            ->zone_id->toBe($this->zone->id)->device_id->toBe($this->device->id)->work_center_id->toBe($this->plant->id)
            ->latitude->toBe('19.432700')->longitude->toBe('-99.133200')->accuracy_m->toBe(12.0)
            ->and($mark->distance_m)->toEqualWithDelta(11.1, 0.1)
            ->and($mark->marked_at?->utc()->toDateTimeString())->toBe('2026-11-09 15:00:00');
    });

    it('takes the time from the server even when the body brings another', function (): void {
        anaPunches(['marked_at' => '2020-01-01 08:00:00', 'time' => '08:00'])->assertOk();

        expect(AttendanceMark::query()->sole()->marked_at?->utc()->toDateTimeString())->toBe('2026-11-09 15:00:00');
    });

    it('blocks a mark outside every zone by default, recording nothing', function (): void {
        anaPunches(PUNCH_OUTSIDE)->assertUnprocessable()->assertExactJson(['reason' => 'outside_zone']);

        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('records a mark outside every zone with no zone, flagged, when the policy allows it', function (): void {
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'out_of_zone' => OutOfZoneRule::AllowAndFlag]);

        anaPunches(PUNCH_OUTSIDE)->assertOk()->assertJsonPath('flag', 'outside_zone');

        expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Qr)->zone_id->toBeNull()->latitude->toBe('19.433600');
    });

    it('rejects a reading less accurate than the policy allows', function (): void {
        anaPunches(['accuracy' => 101])->assertUnprocessable()->assertExactJson(['reason' => 'accuracy_too_low']);
        anaPunches(['accuracy' => 100])->assertOk();
    });

    it('asks for the location when the device gave none (permission denied)', function (): void {
        anaPunches(['lat' => null, 'lng' => null, 'accuracy' => null])->assertUnprocessable()->assertExactJson(['reason' => 'no_location']);
    });

    it('asks for the code of the center\'s screen', function (): void {
        anaPunches(['q' => null])->assertUnprocessable()->assertExactJson(['reason' => 'qr_required']);
    });

    it('rejects a code that is not the center\'s, or a photo of it a minute later', function (): void {
        $code = punchQr($this->tenant->id, $this->plant->id);
        anaPunches(['q' => 'not-a-code'])->assertUnprocessable()->assertExactJson(['reason' => 'qr_invalid']);

        $this->travel(61)->seconds();
        anaPunches(['q' => $code])->assertUnprocessable()->assertExactJson(['reason' => 'qr_invalid']);
        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('takes the code of the same center a few seconds later', function (): void {
        $code = punchQr($this->tenant->id, $this->plant->id);
        $this->travel(25)->seconds();

        anaPunches(['q' => $code])->assertOk();
    });

    it('checks the code and the zone against the person\'s current work center', function (): void {
        $warehouse = WorkCenter::factory()->for($this->tenant)->create();
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $warehouse->id, 'latitude' => 20.0, 'longitude' => -100.0]);
        $this->ana->update(['work_center_id' => $warehouse->id]);

        // The old center's code and zone no longer count.
        anaPunches()->assertUnprocessable()->assertExactJson(['reason' => 'qr_invalid']);
        anaPunches(['q' => punchQr($this->tenant->id, $warehouse->id)])->assertUnprocessable()->assertExactJson(['reason' => 'outside_zone']);
        anaPunches(['q' => punchQr($this->tenant->id, $warehouse->id), 'lat' => 20.0, 'lng' => -100.0])->assertOk();

        expect(AttendanceMark::query()->sole()->work_center_id)->toBe($warehouse->id);
    });

    it('marks with the QR alone in a center without zones, flagged for the panel', function (): void {
        $this->zone->delete();

        anaPunches(['lat' => null, 'lng' => null, 'accuracy' => null])->assertOk()->assertJsonPath('flag', 'center_without_zones');

        expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Qr)->zone_id->toBeNull()->latitude->toBeNull();
    });
});

it('marks remotely with no geofence, keeping no position', function (): void {
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_mode' => WorkMode::Remote]);

    punchWith($this->phone, $this->ana->id, PUNCH_OUTSIDE)->assertOk()->assertJsonPath('flag', null);

    expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Remote)->latitude->toBeNull()->accuracy_m->toBeNull()->zone_id->toBeNull();
});

it('marks trusted staff from the browser with no QR', function (): void {
    $this->ana->update(['staff_group' => StaffGroup::Trusted]);

    punchWith($this->phone, $this->ana->id, [])->assertOk();

    expect(AttendanceMark::query()->sole()->method)->toBe(MarkMethod::TrustedBrowser);
});

describe('the sequence', function (): void {
    it('rejects a second tap of the same mark without recording it twice', function (): void {
        anaPunches()->assertOk();
        anaPunches()->assertUnprocessable()->assertExactJson(['reason' => 'day_already_open']);

        expect(AttendanceMark::query()->count())->toBe(1);
    });

    it('rejects a mark out of sequence', function (): void {
        anaPunches(['type' => 'break_end'])->assertUnprocessable()->assertExactJson(['reason' => 'not_on_break']);
    });

    it('goes through the day and offers the next step after each mark', function (): void {
        anaPunches()->assertJsonPath('next', ['break_start', 'end']);
        $this->travel(4)->hours();
        anaPunches(['type' => 'break_start'])->assertJsonPath('next', ['break_end', 'end']);
        $this->travel(30)->minutes();
        anaPunches(['type' => 'break_end'])->assertJsonPath('next', ['break_start', 'end']);
        $this->travel(4)->hours();

        anaPunches(['type' => 'end'])->assertOk()->assertExactJson([
            'marks' => [
                ['type' => 'start', 'time' => '09:00'],
                ['type' => 'break_start', 'time' => '13:00'],
                ['type' => 'break_end', 'time' => '13:30'],
                ['type' => 'end', 'time' => '17:30'],
            ],
            'next' => ['start'],
            'flag' => null,
        ]);
    });

    it('keeps a day started at 23:59 open after midnight, and starts a clean day after one that ended', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-09 23:59:00', 'America/Mexico_City'));
        anaPunches()->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 00:01:00', 'America/Mexico_City'));

        anaPunches(['type' => 'end'])->assertOk()->assertExactJson([
            'marks' => [['type' => 'start', 'time' => '23:59'], ['type' => 'end', 'time' => '00:01']],
            'next' => ['start'],
            'flag' => null,
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-11-11 00:01:00', 'America/Mexico_City'));
        anaPunches()->assertOk()->assertJsonPath('marks', [['type' => 'start', 'time' => '00:01']]);
    });
});

describe('who marks', function (): void {
    it('is only the person whose key signed, whatever the body says', function (): void {
        $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

        anaPunches(['employee_id' => $other->id])->assertOk();

        expect(AttendanceMark::query()->sole()->employee_id)->toBe($this->ana->id);
    });

    it('answers 404 to a revoked device', function (): void {
        resolve(RevokeDevice::class)->handle($this->device->id);

        anaPunches()->assertNotFound();
        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('answers the same 404 to a person who left', function (): void {
        $this->ana->update(['deactivated_at' => now()]);

        anaPunches()->assertNotFound();
        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('answers the same 404 to a key nobody linked', function (): void {
        punchWith(new SoftAuthenticator, $this->ana->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE])->assertNotFound();
        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('answers 404 to a key of another company', function (): void {
        $globex = Tenant::factory()->create(['slug' => 'globex']);
        $center = WorkCenter::factory()->for($globex)->create();
        $bob = EmployeeFactory::new()->create(['tenant_id' => $globex->id, 'work_center_id' => $center->id]);
        $bobPhone = new SoftAuthenticator;
        punchDevice($bobPhone, $bob);
        workIn($this->tenant);

        punchWith($bobPhone, $bob->id, ['q' => punchQr($globex->id, $center->id), ...PUNCH_INSIDE])->assertNotFound();
        expect(AttendanceMark::query()->withoutGlobalScopes()->count())->toBe(0);
    });

    it('answers 404 to a signature replayed, and records one mark', function (): void {
        $cookie = config()->string('session.cookie');
        $session = punchSession();
        $options = $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.options', 'acme'))->json();
        $body = ['type' => 'start', 'credential' => $this->phone->get($options, $this->ana->id), 'q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE];

        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.store', 'acme'), $body)->assertOk();
        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.store', 'acme'), [...$body, 'type' => 'end'])->assertNotFound();

        expect(AttendanceMark::query()->count())->toBe(1);
    });

    it('answers 404 to a signature of a challenge given to another session', function (): void {
        $cookie = config()->string('session.cookie');
        $options = $this->withCredentials()->withCookie($cookie, punchSession())->postJson(route('attendance.device.punch.options', 'acme'))->json();

        // Another browser: a session id of its own (withCookie keeps the last cookie for later requests of the test).
        $this->withCredentials()->withCookie($cookie, Str::random(40))->postJson(route('attendance.device.punch.store', 'acme'), [
            'type' => 'start', 'credential' => $this->phone->get($options, $this->ana->id), 'q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE,
        ])->assertNotFound();
    });

    it('gives options bound to nobody: no person, no key listed, never cached', function (): void {
        $response = $this->postJson(route('attendance.device.punch.options', 'acme'))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        expect($response->json('userVerification'))->toBe('required')
            ->and($response->json('challenge'))->toBeString()
            ->and($response->json('allowCredentials') ?? [])->toBe([]);
    });
});

describe('validation', function (): void {
    it('refuses coordinates that cannot exist and a type that is not one', function (array $body): void {
        anaPunches($body)->assertUnprocessable()->assertJsonMissingPath('reason');

        expect(AttendanceMark::query()->count())->toBe(0);
    })->with([
        'latitude 91' => [['lat' => 91]],
        'latitude -91' => [['lat' => -91]],
        'longitude 181' => [['lng' => 181]],
        'longitude -181' => [['lng' => -181]],
        'latitude NaN' => [['lat' => 'NaN']],
        'longitude INF' => [['lng' => 'INF']],
        'negative accuracy' => [['accuracy' => -1]],
        'latitude without longitude' => [['lng' => null]],
        'position without accuracy' => [['accuracy' => null]],
        'unknown type' => [['type' => 'lunch']],
        'no type' => [['type' => null]],
        'no credential' => [['credential' => null]],
        'code too long' => [['q' => str_repeat('a', 129)]],
    ]);

    it('accepts the edges of the ranges', function (): void {
        $this->zone->delete();

        anaPunches(['lat' => 90, 'lng' => -180, 'accuracy' => 0])->assertOk();
    });

    it('names its fields in Spanish', function (): void {
        anaPunches(['lat' => 91])->assertJsonValidationErrors(['lat' => 'latitud']);
    });
});

describe('throttling', function (): void {
    it('limits the address generously, for a whole shift behind one router, and each key strictly', function (): void {
        $request = Request::create(route('attendance.device.punch.store', 'acme'), 'POST', ['credential' => ['id' => 'abc']]);
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));
        $limits = RateLimiter::limiter('attendance-punch')?->__invoke($request);

        expect($limits)->toHaveCount(2)
            ->and($limits[0])->maxAttempts->toBe(300)->decaySeconds->toBe(60)->key->toBe('attendance-punch-ip:'.$request->ip())
            ->and($limits[1])->maxAttempts->toBe(12)->decaySeconds->toBe(60)->key->toBe('attendance-punch-key:'.hash('sha256', 'abc'));
    });

    it('limits only the address before a key is sent', function (): void {
        $request = Request::create(route('attendance.device.punch.options', 'acme'), 'POST');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        expect(RateLimiter::limiter('attendance-punch')?->__invoke($request))->toHaveCount(1);
    });

    it('stops a key after 12 marks in a minute', function (): void {
        foreach (range(1, 12) as $ignored) {
            expect(anaPunches()->status())->toBeIn([200, 422]);
        }

        anaPunches()->assertTooManyRequests();
    });
});

it('never writes the position, the code or the key to a log line', function (): void {
    $path = storage_path('logs/punch-'.Str::random(12).'.log');
    config(['logging.default' => 'single', 'logging.channels.single.path' => $path]);
    Log::forgetChannel('single');
    $code = punchQr($this->tenant->id, $this->plant->id);

    anaPunches(['q' => $code])->assertOk();
    anaPunches(['q' => $code, ...PUNCH_OUTSIDE, 'type' => 'end'])->assertUnprocessable();

    $log = File::get($path);
    File::delete($path);
    expect($log)->toContain('attendance.mark_recorded')->toContain('attendance.mark_rejected')
        ->not->toContain('19.4327')->not->toContain('19.4336')->not->toContain('99.1332')
        ->not->toContain($code)->not->toContain($this->device->credential_id);
});
