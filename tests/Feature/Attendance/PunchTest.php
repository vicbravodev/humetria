<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\CenterMode;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Http\Controllers\MarkController;
use App\Modules\Attendance\Models\AgreementAcceptance;
use App\Modules\Attendance\Models\AttendanceCenterMode;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Attendance\Models\AttendanceSchedule;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Attendance\Support\PunchDay;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Actions\RevokeCredential;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Support\WebAuthnCeremony;
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

/** The phone's key, linked to `$person` in their company, who accepted the current agreement with it unless told otherwise. */
function punchDevice(SoftAuthenticator $phone, Employee $person, bool $accepted = true): WorkerCredential
{
    workIn(Tenant::query()->findOrFail($person->tenant_id));
    $ceremony = resolve(WebAuthnCeremony::class);
    $credential = $ceremony->verifyRegistration($person->id, $phone->create($ceremony->registrationOptions($person->id, 'Ana')));

    $device = WorkerCredential::factory()->create([
        'tenant_id' => $person->tenant_id,
        'employee_id' => $person->id,
        'credential_id' => $credential->credentialId,
        'public_key' => $credential->publicKey,
    ]);

    if ($accepted) {
        agreementAccepted($person->id, $device->id);
    }

    return $device;
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
        'mark_type' => 'start',
        'credential' => $phone->get($options, $userHandle),
        ...$body,
    ]);
}

/** The phone accepts `$version` of the agreement as the page does: options for its session, the key signs (with `$userHandle`). */
function acceptWith(SoftAuthenticator $phone, string $userHandle, int $version, ?string $session = null): TestResponse
{
    $cookie = config()->string('session.cookie');
    $session ??= punchSession();
    $options = test()->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.agreement.options', 'acme'))
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json();

    return test()->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.agreement.accept', 'acme'), [
        'agreement_version' => $version,
        'credential' => $phone->get($options, $userHandle),
    ]);
}

/**
 * Ana marks on site with the plant's code, inside the zone, unless told otherwise.
 *
 * @param  array<string, mixed>  $body
 */
function anaPunches(array $body = [], ?string $session = null): TestResponse
{
    return punchWith(test()->phone, test()->ana->id, ['q' => punchQr(test()->tenant->id, test()->plant->id), ...PUNCH_INSIDE, ...$body], $session);
}

describe('the page', function (): void {
    it('gives the screen where to accept the agreement', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/punch')
                ->where('agreementOptionsUrl', route('attendance.device.agreement.options', 'acme'))
                ->where('agreementUrl', route('attendance.device.agreement.accept', 'acme')));
    });

    it('opens for a device that has not marked here yet, asking for the location only when marking', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('attendance/punch')
                ->where('company', 'Acme')
                ->where('qr', null)
                ->where('person', null)
                ->missing('askLocation')
                ->where('optionsUrl', route('attendance.device.punch.options', 'acme'))
                ->where('punchUrl', route('attendance.device.punch.store', 'acme'))
                ->where('forgetUrl', route('attendance.device.punch.forget', 'acme')));
    });

    it('tells a person without a key where to add one: their entrance, with a session; the first comes from RH', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('enterUrl', route('worker.home', 'acme')));
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

    it('lets this page alone read the position, from its own origin only (Permissions-Policy)', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()');
        $this->get(route('attendance.device.punch.show', ['acme', 'q' => 'abc']))
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()');
        $this->get(route('login'))->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    });

    it('says how the person who marked here works: remote asks for no code nor zone, on site as it was, nobody known says nothing', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))->assertInertia(fn (AssertableInertia $page) => $page->where('mode', null));

        $session = punchSession();
        anaPunches([], $session)->assertOk();
        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('mode', 'onsite'));

        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_mode' => WorkMode::Remote]);
        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('mode', 'remote'));
    });

    it('says how the person works from their portal\'s session when nobody marked here yet, in their center\'s zone', function (): void {
        $this->plant->update(['timezone' => 'America/Tijuana']);
        AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_mode' => WorkMode::Remote]);
        enteredAs($this->ana->id);

        $this->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('mode', 'remote')->where('timezone', 'America/Tijuana')->where('person', null));
    });

    it('gives the server\'s time, for the clock of the page to follow it and not the phone\'s', function (): void {
        $this->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('now', CarbonImmutable::now()->getTimestampMs()));
    });

    it('answers 404 for a company that does not exist', function (): void {
        $this->get(route('attendance.device.punch.home', 'nadie'))->assertNotFound();
        $this->postJson(route('attendance.device.punch.options', 'nadie'))->assertNotFound();
    });

    it('remembers for 10 minutes who marked in this browser: their name and next step only, never their marks', function (): void {
        $session = punchSession();
        punchWith($this->phone, $this->ana->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE], $session)->assertOk();
        $this->travel(9)->minutes();
        $this->travel(59)->seconds();

        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('person', ['name' => 'Ana López', 'next' => ['break_start', 'end']])
                ->missing('person.marks')
                ->missing('person.id'));

        $this->travel(1)->seconds();
        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('person', null));
    });

    it('remembers the person for the minutes of config/attendance.php (10 by default)', function (): void {
        expect(config('attendance.punch.remember_minutes'))->toBe(10);
        config(['attendance.punch.remember_minutes' => 1]);
        $session = punchSession();
        punchWith($this->phone, $this->ana->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE], $session)->assertOk();
        $this->travel(60)->seconds();

        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('person', null));
    });

    it('forgets the person at once when they say it is not them («¿No es usted?»)', function (): void {
        $cookie = config()->string('session.cookie');
        $session = punchSession();
        punchWith($this->phone, $this->ana->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE], $session)->assertOk();

        $this->withCookie($cookie, $session)->from(route('attendance.device.punch.show', ['acme', 'q' => 'abc']))
            ->post(route('attendance.device.punch.forget', 'acme'))
            ->assertRedirect(route('attendance.device.punch.show', ['acme', 'q' => 'abc']));

        $this->withCookie($cookie, $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('person', null));
    });

    it('answers 404 to forgetting in a company that does not exist', function (): void {
        $this->post(route('attendance.device.punch.forget', 'nadie'))->assertNotFound();
    });

    it('tells who marked and their own steps when two people mark in one browser, and shows the first one nothing of the second', function (): void {
        $cookie = config()->string('session.cookie');
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Ruiz']);
        $bobPhone = new SoftAuthenticator;
        punchDevice($bobPhone, $bob);
        $session = punchSession();

        punchWith($this->phone, $this->ana->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE], $session)
            ->assertOk()->assertJsonPath('name', 'Ana López');
        // Bob, on the same browser, tries the step offered to Ana: rejected, told it is him and what he may do.
        punchWith($bobPhone, $bob->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE, 'mark_type' => 'break_start'], $session)
            ->assertUnprocessable()->assertExactJson(['reason' => 'day_not_open', 'name' => 'Bob Ruiz', 'next' => ['start']]);
        punchWith($bobPhone, $bob->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE], $session)
            ->assertOk()->assertExactJson(['name' => 'Bob Ruiz', 'marks' => [['type' => 'start', 'time' => '09:00', 'clock' => null]], 'next' => ['break_start', 'end'], 'flag' => null, 'evidence' => 'strong']);

        $this->withCookie($cookie, $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('person.name', 'Bob Ruiz'));
    });

    it('tells who marked and their next steps with a rejection of the policy too', function (): void {
        anaPunches(PUNCH_OUTSIDE)->assertUnprocessable()->assertExactJson(['reason' => 'outside_zone', 'name' => 'Ana López', 'next' => ['start']]);
    });

    it('forgets the person once their device is revoked or they leave', function (string $how): void {
        $session = punchSession();
        anaPunches()->assertOk();
        punchWith($this->phone, $this->ana->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE, 'mark_type' => 'end'], $session)->assertOk();

        if ($how === 'revoked') {
            resolve(RevokeCredential::class)->handle($this->device->id);
        } else {
            $this->ana->update(['deactivated_at' => now()]);
        }

        $this->withCookie(config()->string('session.cookie'), $session)->get(route('attendance.device.punch.home', 'acme'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('person', null));
    })->with(['revoked', 'deactivated']);
});

describe('on site', function (): void {
    it('marks with QR and geofence: the zone, the position, its accuracy and the distance, at the server\'s time', function (): void {
        anaPunches()->assertOk()->assertExactJson([
            'name' => 'Ana López',
            'marks' => [['type' => 'start', 'time' => '09:00', 'clock' => null]],
            'next' => ['break_start', 'end'],
            'flag' => null,
            'evidence' => 'strong',
        ]);

        $mark = AttendanceMark::query()->sole();
        expect($mark)->employee_id->toBe($this->ana->id)->type->toBe(MarkType::Start)->method->toBe(MarkMethod::QrAndGeofence)
            ->zone_id->toBe($this->zone->id)->credential_id->toBe($this->device->id)->work_center_id->toBe($this->plant->id)
            ->latitude->toBe('19.432700')->longitude->toBe('-99.133200')->accuracy_m->toBe(12.0)
            ->and($mark->distance_m)->toEqualWithDelta(11.1, 0.1)
            ->and($mark->marked_at?->utc()->toDateTimeString())->toBe('2026-11-09 15:00:00');
    });

    it('takes the time from the server even when the body brings another', function (): void {
        anaPunches(['marked_at' => '2020-01-01 08:00:00', 'time' => '08:00'])->assertOk();

        expect(AttendanceMark::query()->sole()->marked_at?->utc()->toDateTimeString())->toBe('2026-11-09 15:00:00');
    });

    it('blocks a mark outside every zone by default, recording nothing', function (): void {
        anaPunches(PUNCH_OUTSIDE)->assertUnprocessable()->assertJsonPath('reason', 'outside_zone');

        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('records a mark outside every zone with no zone, flagged, when the policy allows it', function (): void {
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'out_of_zone' => OutOfZoneRule::AllowAndFlag]);

        anaPunches(PUNCH_OUTSIDE)->assertOk()->assertJsonPath('flag', 'outside_zone')->assertJsonPath('evidence', 'medium');

        expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Qr)->zone_id->toBeNull()->latitude->toBe('19.433600');
    });

    it('records the QR alone, flagged as without a location, when the policy allows out of zone and there is no position', function (): void {
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'out_of_zone' => OutOfZoneRule::AllowAndFlag]);

        anaPunches(['lat' => null, 'lng' => null, 'accuracy' => null])->assertOk()->assertJsonPath('flag', 'no_location');

        expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Qr)->zone_id->toBeNull()->latitude->toBeNull();
    });

    it('rejects a reading less accurate than the policy allows', function (): void {
        anaPunches(['accuracy' => 101])->assertUnprocessable()->assertJsonPath('reason', 'accuracy_too_low');
        anaPunches(['accuracy' => 100])->assertOk();
    });

    it('asks for the location when the device gave none (permission denied)', function (): void {
        anaPunches(['lat' => null, 'lng' => null, 'accuracy' => null])->assertUnprocessable()->assertJsonPath('reason', 'no_location');
    });

    it('asks for the code of the center\'s screen', function (): void {
        anaPunches(['q' => null])->assertUnprocessable()->assertJsonPath('reason', 'qr_required');
    });

    it('rejects a code that is not the center\'s, or a photo of it a minute later', function (): void {
        $code = punchQr($this->tenant->id, $this->plant->id);
        anaPunches(['q' => 'not-a-code'])->assertUnprocessable()->assertJsonPath('reason', 'qr_invalid');

        $this->travel(61)->seconds();
        // Hallazgo 26: the photo of the center's code is told apart, as expired.
        anaPunches(['q' => $code])->assertUnprocessable()->assertJsonPath('reason', 'qr_expired');
        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('keeps the scan of a code that counted when the page opened for 3 minutes of this browser, to accept and mark (hallazgo 27)', function (): void {
        $code = punchQr($this->tenant->id, $this->plant->id);
        $cookie = config()->string('session.cookie');
        $session = $this->get(route('attendance.device.punch.show', ['acme', 'q' => $code]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('scannedAt', '09:00'))
            ->getCookie($cookie)?->getValue();

        $this->travel(179)->seconds();
        anaPunches(['q' => $code], $session)->assertOk()->assertJsonPath('evidence', 'strong');
        expect(AttendanceMark::query()->sole()->method)->toBe(MarkMethod::QrAndGeofence);

        $this->travel(2)->seconds();
        anaPunches(['q' => $code, 'mark_type' => 'end'], $session)->assertUnprocessable()->assertJsonPath('reason', 'qr_expired');
    });

    it('gives no scan for a code that no longer counts when the page opens, nor to another browser', function (): void {
        $code = punchQr($this->tenant->id, $this->plant->id);
        $this->travel(61)->seconds();
        $this->get(route('attendance.device.punch.show', ['acme', 'q' => $code]))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('scannedAt', null));
        $this->get(route('attendance.device.punch.home', 'acme'))->assertInertia(fn (AssertableInertia $page) => $page->where('scannedAt', null));

        $fresh = punchQr($this->tenant->id, $this->plant->id);
        $this->get(route('attendance.device.punch.show', ['acme', 'q' => $fresh]))->assertOk();
        $this->travel(90)->seconds();
        // Another browser's session: the scan was not its own.
        $this->flushSession();
        anaPunches(['q' => $fresh])->assertUnprocessable()->assertJsonPath('reason', 'qr_expired');
    });

    it('keeps the scan of another center\'s code as that center\'s, and a scan only for the code it was of', function (): void {
        $offices = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas']);
        $code = punchQr($this->tenant->id, $offices->id);
        $session = $this->get(route('attendance.device.punch.show', ['acme', 'q' => $code]))->getCookie(config()->string('session.cookie'))?->getValue();
        $this->travel(90)->seconds();

        anaPunches(['q' => $code], $session)->assertUnprocessable()->assertJsonPath('reason', 'qr_other_center')->assertJsonPath('center', 'Oficinas');
        anaPunches(['q' => 'not-a-code'], $session)->assertUnprocessable()->assertJsonPath('reason', 'qr_invalid');
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

        // The old center's code and zone no longer count: its code is another center's now (hallazgo 26).
        anaPunches()->assertUnprocessable()->assertJsonPath('reason', 'qr_other_center');
        anaPunches(['q' => punchQr($this->tenant->id, $warehouse->id)])->assertUnprocessable()->assertJsonPath('reason', 'outside_zone');
        anaPunches(['q' => punchQr($this->tenant->id, $warehouse->id), 'lat' => 20.0, 'lng' => -100.0])->assertOk();

        expect(AttendanceMark::query()->sole()->work_center_id)->toBe($warehouse->id);
    });

    it('marks with the QR alone in a center without zones, flagged for the panel', function (): void {
        $this->zone->delete();

        anaPunches(['lat' => null, 'lng' => null, 'accuracy' => null])->assertOk()->assertJsonPath('flag', 'center_without_zones');

        expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Qr)->zone_id->toBeNull()->latitude->toBeNull();
    });

    it('says the center is not ready when it has no zone and no active screen, and keeps the QR message once a screen is in use', function (): void {
        $this->zone->delete();

        anaPunches(['q' => null])->assertUnprocessable()->assertJsonPath('reason', 'center_not_ready');

        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'activated_at' => now(), 'last_seen_at' => now()]);

        anaPunches(['q' => null])->assertUnprocessable()->assertJsonPath('reason', 'qr_required');
    });

    it('keeps the phone linked after a refused mark: the next try in the same browser is refused or taken again, never «not linked»', function (): void {
        $this->zone->delete();
        $session = punchSession();
        $try = fn (array $body): TestResponse => punchWith($this->phone, $this->ana->id, ['q' => null, ...$body], $session);

        $try([])->assertUnprocessable()->assertJsonPath('reason', 'center_not_ready');
        $try([])->assertUnprocessable()->assertJsonPath('reason', 'center_not_ready');

        // And once RH sets the center up, the same browser marks with the same key.
        AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'latitude' => 19.4326, 'longitude' => -99.1332, 'radius_m' => 500]);
        $try(['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE])->assertOk();
        expect(WorkerCredential::query()->whereNull('revoked_at')->count())->toBe(1);
    });

    it('ignores a revoked or never-seen screen when telling whether the center is ready', function (): void {
        $this->zone->delete();
        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'activated_at' => now(), 'last_seen_at' => now(), 'revoked_at' => now()]);
        KioskDevice::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

        anaPunches(['q' => null])->assertUnprocessable()->assertJsonPath('reason', 'center_not_ready');
    });

    it('keeps the QR message for a center with a zone and no screen', function (): void {
        anaPunches(['q' => null])->assertUnprocessable()->assertJsonPath('reason', 'qr_required');
    });
});

describe('in a field center', function (): void {
    beforeEach(function (): void {
        AttendanceCenterMode::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'mode' => CenterMode::Field]);
    });

    it('marks from anywhere with no QR, keeping the position, as strong evidence', function (): void {
        punchWith($this->phone, $this->ana->id, PUNCH_OUTSIDE)->assertOk()->assertJsonPath('flag', 'field')->assertJsonPath('evidence', 'strong');

        expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Field)->zone_id->toBeNull()
            ->latitude->toBe('19.433600')->longitude->toBe('-99.133200')->accuracy_m->toBe(12.0);
    });

    it('marks in a center with neither a zone nor a screen: never center_not_ready', function (): void {
        $this->zone->delete();

        punchWith($this->phone, $this->ana->id, PUNCH_INSIDE)->assertOk()->assertJsonPath('evidence', 'strong');
    });

    it('takes a mark with no location or a poor one as medium, keeping what came', function (): void {
        punchWith($this->phone, $this->ana->id, ['lat' => null, 'lng' => null, 'accuracy' => null])->assertOk()->assertJsonPath('evidence', 'medium');
        $this->travel(1)->hours();
        punchWith($this->phone, $this->ana->id, ['mark_type' => 'end', ...PUNCH_OUTSIDE, 'accuracy' => 900])->assertOk()->assertJsonPath('evidence', 'medium');

        expect(AttendanceMark::query()->orderBy('marked_at')->get()->map(fn (AttendanceMark $mark): array => [$mark->method, $mark->accuracy_m])->all())
            ->toEqual([[MarkMethod::FieldUnlocated, null], [MarkMethod::FieldUnlocated, 900.0]]);
    });

    it('still takes the center\'s QR, optional, and refuses one that does not verify', function (): void {
        anaPunches()->assertOk()->assertJsonPath('evidence', 'strong');
        $this->travel(1)->hours();
        anaPunches(['mark_type' => 'end', 'q' => 'not-a-code'])->assertUnprocessable()->assertJsonPath('reason', 'qr_invalid');
    });

    it('lets a person new to the center mark with no schedule', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Ruiz']);
        $phone = new SoftAuthenticator;
        punchDevice($phone, $bob);

        punchWith($phone, $bob->id, PUNCH_OUTSIDE)->assertOk()->assertJsonPath('evidence', 'strong');
        expect(AttendanceSchedule::query()->where('employee_id', $bob->id)->exists())->toBeFalse();
    });

    it('leaves a center on the company\'s mode as it was', function (): void {
        AttendanceCenterMode::query()->update(['mode' => CenterMode::Company]);

        anaPunches(['q' => null])->assertUnprocessable()->assertJsonPath('reason', 'qr_required');
    });
});

it('marks remotely with no geofence, keeping no position', function (): void {
    AttendanceSchedule::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_mode' => WorkMode::Remote]);

    punchWith($this->phone, $this->ana->id, PUNCH_OUTSIDE)->assertOk()->assertJsonPath('flag', null)->assertJsonPath('evidence', 'basic');

    expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::Remote)->latitude->toBeNull()->accuracy_m->toBeNull()->zone_id->toBeNull();
});

it('marks trusted staff from the browser with no QR', function (): void {
    $this->ana->update(['staff_group' => StaffGroup::Trusted]);

    punchWith($this->phone, $this->ana->id, [])->assertOk()->assertJsonPath('evidence', 'basic');

    expect(AttendanceMark::query()->sole()->method)->toBe(MarkMethod::TrustedBrowser);
});

it('drops the position trusted staff send from the browser, as for remote staff', function (): void {
    $this->ana->update(['staff_group' => StaffGroup::Trusted]);

    punchWith($this->phone, $this->ana->id, PUNCH_OUTSIDE)->assertOk()->assertJsonPath('flag', null)->assertJsonPath('evidence', 'basic');

    expect(AttendanceMark::query()->sole())->method->toBe(MarkMethod::TrustedBrowser)
        ->latitude->toBeNull()->longitude->toBeNull()->accuracy_m->toBeNull()->distance_m->toBeNull()->zone_id->toBeNull();
});

describe('the sequence', function (): void {
    it('rejects a second tap of the same mark without recording it twice', function (): void {
        anaPunches()->assertOk();
        anaPunches()->assertUnprocessable()->assertJsonPath('reason', 'day_already_open');

        expect(AttendanceMark::query()->count())->toBe(1);
    });

    it('rejects a mark out of sequence', function (): void {
        anaPunches(['mark_type' => 'break_end'])->assertUnprocessable()->assertJsonPath('reason', 'not_on_break');
    });

    it('goes through the day and offers the next step after each mark', function (): void {
        anaPunches()->assertJsonPath('next', ['break_start', 'end']);
        $this->travel(4)->hours();
        anaPunches(['mark_type' => 'break_start'])->assertJsonPath('next', ['break_end', 'end']);
        $this->travel(30)->minutes();
        anaPunches(['mark_type' => 'break_end'])->assertJsonPath('next', ['break_start', 'end']);
        $this->travel(4)->hours();

        anaPunches(['mark_type' => 'end'])->assertOk()->assertExactJson([
            'name' => 'Ana López',
            'marks' => [
                ['type' => 'start', 'time' => '09:00', 'clock' => null],
                ['type' => 'break_start', 'time' => '13:00', 'clock' => null],
                ['type' => 'break_end', 'time' => '13:30', 'clock' => null],
                ['type' => 'end', 'time' => '17:30', 'clock' => null],
            ],
            'next' => ['start'],
            'flag' => null,
            'evidence' => 'strong',
        ]);
    });

    it('keeps a day started at 23:59 open after midnight, and starts a clean day after one that ended', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-09 23:59:00', 'America/Mexico_City'));
        anaPunches()->assertOk();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 00:01:00', 'America/Mexico_City'));

        anaPunches(['mark_type' => 'end'])->assertOk()->assertExactJson([
            'name' => 'Ana López',
            'marks' => [['type' => 'start', 'time' => '23:59', 'clock' => null], ['type' => 'end', 'time' => '00:01', 'clock' => null]],
            'next' => ['start'],
            'flag' => null,
            'evidence' => 'strong',
        ]);

        $this->travelTo(CarbonImmutable::parse('2026-11-11 00:01:00', 'America/Mexico_City'));
        anaPunches()->assertOk()->assertJsonPath('marks', [['type' => 'start', 'time' => '00:01', 'clock' => null]]);
    });
});

describe('who marks', function (): void {
    it('is only the person whose key signed, whatever the body says', function (): void {
        $other = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

        anaPunches(['employee_id' => $other->id])->assertOk();

        expect(AttendanceMark::query()->sole()->employee_id)->toBe($this->ana->id);
    });

    it('answers 404 to a revoked device', function (): void {
        resolve(RevokeCredential::class)->handle($this->device->id);

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

    it('logs a signature that did not verify with the company and the request only', function (): void {
        Log::spy();
        resolve(RevokeCredential::class)->handle($this->device->id);

        anaPunches()->assertNotFound();

        Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $event, array $context): bool => $event === 'attendance.mark_unverified'
            && array_keys($context) === ['tenant_id', 'request_id']
            && $context['tenant_id'] === $this->tenant->id
            && is_string($context['request_id']) && $context['request_id'] !== '');
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
        $body = ['mark_type' => 'start', 'credential' => $this->phone->get($options, $this->ana->id), 'q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE];

        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.store', 'acme'), $body)->assertOk();
        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.store', 'acme'), [...$body, 'mark_type' => 'end'])->assertNotFound();

        expect(AttendanceMark::query()->count())->toBe(1);
    });

    it('answers 404 to a signature of a challenge given to another session', function (): void {
        $cookie = config()->string('session.cookie');
        $options = $this->withCredentials()->withCookie($cookie, punchSession())->postJson(route('attendance.device.punch.options', 'acme'))->json();

        // Another browser: a session id of its own (withCookie keeps the last cookie for later requests of the test).
        $this->withCredentials()->withCookie($cookie, Str::random(40))->postJson(route('attendance.device.punch.store', 'acme'), [
            'mark_type' => 'start', 'credential' => $this->phone->get($options, $this->ana->id), 'q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE,
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
        'unknown type' => [['mark_type' => 'lunch']],
        'no type' => [['mark_type' => null]],
        'no credential' => [['credential' => null]],
        'code too long' => [['q' => str_repeat('a', 129)]],
    ]);

    it('accepts the edges of the ranges', function (): void {
        $this->zone->delete();

        anaPunches(['lat' => 90, 'lng' => -180, 'accuracy' => 0])->assertOk();
    });

    it('names its fields in Spanish', function (): void {
        anaPunches(['lat' => 91])->assertJsonValidationErrors(['lat' => 'latitud']);
        anaPunches(['mark_type' => 'lunch'])->assertJsonValidationErrors(['mark_type' => 'tipo de marca']);
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

describe('the agreement', function (): void {
    it('refuses the mark of a person who has not accepted the current agreement, and gives the screen its text', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Ruiz']);
        $bobPhone = new SoftAuthenticator;
        punchDevice($bobPhone, $bob, accepted: false);
        $agreement = resolve(CurrentAgreement::class)->get();

        punchWith($bobPhone, $bob->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE])
            ->assertUnprocessable()
            ->assertExactJson(['reason' => 'agreement_not_accepted', 'name' => 'Bob Ruiz', 'next' => ['start'], 'agreement' => ['version' => $agreement->version, 'body' => $agreement->body]]);

        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('asks for the agreement before the policy: a person who has not accepted it is not sent to fix the code first', function (): void {
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
        $bobPhone = new SoftAuthenticator;
        punchDevice($bobPhone, $bob, accepted: false);

        punchWith($bobPhone, $bob->id, ['q' => 'not-a-code', ...PUNCH_INSIDE])->assertUnprocessable()->assertJsonPath('reason', 'agreement_not_accepted');
    });

    it('refuses the mark when the agreement changed after the person accepted it, and takes it once they accept the new one', function (): void {
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'retention_months' => 36]);

        anaPunches()->assertUnprocessable()->assertJsonPath('reason', 'agreement_not_accepted')->assertJsonPath('agreement.version', 2);
        agreementAccepted($this->ana->id, $this->device->id);
        anaPunches()->assertOk();
    });

    it('accepts the current version with the key that signed, once, and then lets the person mark', function (): void {
        Log::spy();
        $bob = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'name' => 'Bob Ruiz']);
        $bobPhone = new SoftAuthenticator;
        $bobDevice = punchDevice($bobPhone, $bob, accepted: false);
        $version = resolve(CurrentAgreement::class)->get()->version;

        acceptWith($bobPhone, $bob->id, $version)->assertOk()->assertExactJson(['accepted' => true])->assertHeader('Cache-Control', 'no-store, private');
        acceptWith($bobPhone, $bob->id, $version)->assertOk()->assertExactJson(['accepted' => true]);

        $acceptance = AgreementAcceptance::query()->where('employee_id', $bob->id)->sole();
        expect($acceptance)->credential_id->toBe($bobDevice->id);
        Log::shouldHaveReceived('info')->once()->with('attendance.agreement_accepted', ['acceptance_id' => $acceptance->id, 'employee_id' => $bob->id, 'key_id' => $bobDevice->id, 'version' => $version]);
        punchWith($bobPhone, $bob->id, ['q' => punchQr($this->tenant->id, $this->plant->id), ...PUNCH_INSIDE])->assertOk();
    });

    it('answers 409 with the current text to an acceptance of a version that is not the current one, and records nothing', function (): void {
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'retention_months' => 36]);
        $current = resolve(CurrentAgreement::class)->get();

        acceptWith($this->phone, $this->ana->id, 1)->assertConflict()
            ->assertExactJson(['reason' => 'agreement_outdated', 'agreement' => ['version' => 2, 'body' => $current->body]]);

        expect(AgreementAcceptance::query()->where('agreement_id', $current->id)->count())->toBe(0);
    });

    it('answers 404 to an acceptance whose signature does not verify, of a revoked key or of a person who left', function (): void {
        $count = AgreementAcceptance::query()->count();
        $version = resolve(CurrentAgreement::class)->get()->version;
        $session = punchSession();
        $cookie = config()->string('session.cookie');
        $options = $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.agreement.options', 'acme'))->json();

        // Signed by an unknown phone.
        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.agreement.accept', 'acme'), [
            'agreement_version' => $version, 'credential' => (new SoftAuthenticator)->get($options, $this->ana->id),
        ])->assertNotFound();
        // A challenge given to mark does not accept the agreement.
        $markOptions = $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.punch.options', 'acme'))->json();
        $this->withCredentials()->withCookie($cookie, $session)->postJson(route('attendance.device.agreement.accept', 'acme'), [
            'agreement_version' => $version, 'credential' => $this->phone->get($markOptions, $this->ana->id),
        ])->assertNotFound();
        $this->ana->update(['deactivated_at' => now()]);
        acceptWith($this->phone, $this->ana->id, $version)->assertNotFound();

        expect(AgreementAcceptance::query()->count())->toBe($count);
    });

    it('validates the version and the signature', function (): void {
        $this->postJson(route('attendance.device.agreement.accept', 'acme'), [])->assertUnprocessable()->assertJsonValidationErrors(['agreement_version', 'credential']);
    });

    it('gives the agreement only with its own refusal', function (): void {
        anaPunches(PUNCH_OUTSIDE)->assertUnprocessable()->assertJsonMissingPath('agreement');
    });
});

it('never writes the position, the code or the key to a log line', function (): void {
    $path = storage_path('logs/punch-'.Str::random(12).'.log');
    config(['logging.default' => 'single', 'logging.channels.single.path' => $path]);
    Log::forgetChannel('single');
    $code = punchQr($this->tenant->id, $this->plant->id);

    anaPunches(['q' => $code])->assertOk();
    anaPunches(['q' => $code, ...PUNCH_OUTSIDE, 'mark_type' => 'end'])->assertUnprocessable();

    $log = File::get($path);
    File::delete($path);
    expect($log)->toContain('attendance.mark_recorded')->toContain('attendance.mark_rejected')
        ->not->toContain('19.4327')->not->toContain('19.4336')->not->toContain('99.1332')
        ->not->toContain($code)->not->toContain($this->device->credential_id);
});

describe('the code of another center (hallazgo 26; design of time zones §3.9)', function (): void {
    beforeEach(function (): void {
        $this->offices = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Oficinas Tijuana', 'timezone' => 'America/Tijuana']);
    });

    it('takes it where the policy allows and flags, at that center\'s hour, «fuera de su centro»', function (): void {
        AttendancePolicy::factory()->create(['tenant_id' => $this->tenant->id, 'out_of_zone' => OutOfZoneRule::AllowAndFlag]);

        anaPunches(['q' => punchQr($this->tenant->id, $this->offices->id)])->assertOk()
            ->assertJsonPath('flag', 'away_center')
            // 15:00 UTC is 07:00 in Tijuana: the person reads the hour of where they marked.
            ->assertJsonPath('marks', [['type' => 'start', 'time' => '07:00', 'clock' => 'America/Tijuana']]);

        expect(AttendanceMark::query()->sole())
            ->away_center_id->toBe($this->offices->id)
            ->timezone->toBe('America/Tijuana')
            ->work_center_id->toBe($this->plant->id)
            ->zone_id->toBeNull()
            ->method->toBe(MarkMethod::Qr);
    });

    it('refuses it where the policy blocks, naming the center whose code it is', function (): void {
        anaPunches(['q' => punchQr($this->tenant->id, $this->offices->id)])->assertUnprocessable()
            ->assertJsonPath('reason', 'qr_other_center')
            ->assertJsonPath('center', 'Oficinas Tijuana');

        expect(AttendanceMark::query()->count())->toBe(0);
    });

    it('tells a code of the person\'s center that expired, and gives no name for anything else', function (): void {
        $code = punchQr($this->tenant->id, $this->plant->id);
        $theirs = punchQr($this->tenant->id, $this->offices->id);
        $elsewhere = punchQr(Tenant::factory()->create()->id, WorkCenter::factory()->create()->id);
        $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
        $this->travel(2)->minutes();

        anaPunches(['q' => $code])->assertUnprocessable()->assertJsonPath('reason', 'qr_expired');
        // An old code of another center, another company's, or an archived center's: not one the person can use.
        anaPunches(['q' => $theirs])->assertUnprocessable()->assertJsonPath('reason', 'qr_invalid')->assertJsonMissingPath('center');
        anaPunches(['q' => $elsewhere])->assertUnprocessable()->assertJsonPath('reason', 'qr_invalid');
        anaPunches(['q' => punchQr($this->tenant->id, $archived->id)])->assertUnprocessable()->assertJsonPath('reason', 'qr_invalid');
    });
});
