<?php

declare(strict_types=1);

use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Notifications\EnrollmentInvitation;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Support\SoftAuthenticator;

beforeEach(function (): void {
    $this->logPath = storage_path('logs/attendance-privacy-'.getmypid().'.log');
    File::delete($this->logPath);
    config(['logging.default' => 'single', 'logging.channels.single.path' => $this->logPath, 'logging.channels.single.level' => 'debug']);
    Log::forgetChannel('single');
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    Notification::fake();
});

afterEach(function (): void {
    File::delete($this->logPath);
});

/**
 * A request of the phone in the session `$session`, signed with a challenge of `$optionsRoute`.
 *
 * @param  array<string, mixed>  $body
 */
function privacySigned(SoftAuthenticator $phone, string $userHandle, string $session, string $optionsRoute, string $url, array $body): TestResponse
{
    $cookie = config()->string('session.cookie');
    $options = test()->withCredentials()->withCookie($cookie, $session)->postJson(route($optionsRoute, 'acme'))->assertOk()->json();

    return test()->withCredentials()->withCookie($cookie, $session)->postJson($url, ['credential' => $phone->get($options, $userHandle), ...$body]);
}

it('logs no position, credential, screen code, enrollment or kiosk token along a whole marking flow, rejections included', function (): void {
    // Monday 2 November 2026, 08:00 in Mexico City.
    $this->travelTo(CarbonImmutable::parse('2026-11-02 14:00:00', 'UTC'));
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create(['name' => 'Planta Norte']);
    AttendanceZone::factory()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'latitude' => 19.432712, 'longitude' => -99.133298, 'radius_m' => 50]);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana López', 'email' => 'ana@acme.test']);
    $hr = memberOf($tenant, TenantRole::Hr);
    $cookie = config()->string('session.cookie');
    $phone = new SoftAuthenticator;

    // RH puts a screen at the plant and sends Ana her link.
    $this->actingAs($hr)->post(route('tenant.attendance.kiosks.store', 'acme'), ['work_center_id' => $plant->id])->assertRedirect();
    $kioskLink = (string) session('inertia.flash_data.kioskLink');
    $this->from(route('tenant.attendance.devices.index', 'acme'))->post(route('tenant.attendance.devices.enroll', 'acme'), ['employee_id' => $ana->id])->assertRedirect();
    $enrollUrl = '';
    Notification::assertSentOnDemand(EnrollmentInvitation::class, function (EnrollmentInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$enrollUrl): bool {
        $enrollUrl = (string) $notification->toMail($notifiable)->actionUrl;

        return true;
    });
    $enrollToken = basename($enrollUrl);
    auth()->logout();

    // The screen opens its link and shows its code.
    $activated = $this->get($kioskLink)->assertRedirect();
    $kioskSecret = (string) $activated->getCookie('attendance_kiosk')?->getValue();
    $qr = (string) $this->withCredentials()->withCookie('attendance_kiosk', $kioskSecret)->getJson(route('attendance.device.kiosk.qr', 'acme'))->assertOk()->json('token');

    // Ana links her phone.
    $options = $this->postJson(route('attendance.device.enroll.options', ['acme', $enrollToken]))->assertOk()->json();
    $this->postJson(route('attendance.device.enroll.register', ['acme', $enrollToken]), [
        'agreement_accepted' => true, 'agreement_version' => 1, 'device_label' => 'iPhone', 'credential' => $phone->create($options),
    ])->assertOk();
    $device = AttendanceDevice::query()->sole();

    // She marks her start at the door and her end in the afternoon.
    $session = $this->get(route('attendance.device.punch.show', ['acme', 'q' => $qr]))->assertOk()->getCookie($cookie)?->getValue() ?? '';
    $position = ['lat' => 19.4327, 'lng' => -99.1332, 'accuracy' => 12];
    // First she is turned away: outside every zone (about 111 m north), then with a code that is not the screen's.
    $badQr = str_repeat('f', 64);
    privacySigned($phone, $ana->id, $session, 'attendance.device.punch.options', route('attendance.device.punch.store', 'acme'), ['mark_type' => 'start', 'q' => $qr, 'lat' => 19.4336, 'lng' => -99.1332, 'accuracy' => 12])->assertUnprocessable();
    privacySigned($phone, $ana->id, $session, 'attendance.device.punch.options', route('attendance.device.punch.store', 'acme'), ['mark_type' => 'start', 'q' => $badQr, ...$position])->assertUnprocessable();
    privacySigned($phone, $ana->id, $session, 'attendance.device.punch.options', route('attendance.device.punch.store', 'acme'), ['mark_type' => 'start', 'q' => $qr, ...$position])->assertOk();
    $this->travel(9)->hours();
    $qrLater = (string) $this->withCredentials()->withCookie('attendance_kiosk', $kioskSecret)->getJson(route('attendance.device.kiosk.qr', 'acme'))->assertOk()->json('token');
    privacySigned($phone, $ana->id, $session, 'attendance.device.punch.options', route('attendance.device.punch.store', 'acme'), ['mark_type' => 'end', 'q' => $qrLater, ...$position])->assertOk();

    // A week later she reads and confirms her week, then asks to correct her start.
    $this->travelTo(CarbonImmutable::parse('2026-11-09 18:00:00', 'UTC'));
    $week = WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02'));
    $session = $this->get(route('attendance.device.week.show', 'acme'))->assertOk()->getCookie($cookie)?->getValue() ?? '';
    privacySigned($phone, $ana->id, $session, 'attendance.device.week.options', route('attendance.device.week.read', 'acme'), ['start' => '2026-11-02'])->assertOk();
    privacySigned($phone, $ana->id, $session, 'attendance.device.week.options', route('attendance.device.week.confirm', ['acme', '2026-11-02']), ['digest' => WeekDigest::of($ana->id, $week)])->assertOk();
    $start = AttendanceMark::query()->orderBy('marked_at')->firstOrFail();
    privacySigned($phone, $ana->id, $session, 'attendance.device.correction.options', route('attendance.device.correction.store', 'acme'), [
        'mark_id' => $start->id, 'mark_type' => 'start', 'proposed_at' => '2026-11-02 07:55', 'reason' => 'Llegué antes, el QR no cargaba.', 'start' => '2026-11-02',
    ])->assertOk();

    // RH approves it and downloads the report.
    $this->actingAs($hr)->from(route('tenant.attendance.corrections.index', 'acme'))
        ->post(route('tenant.attendance.corrections.approve', ['acme', CorrectionRequest::query()->sole()->id]))->assertRedirect();
    $this->get(route('tenant.attendance.report.export', ['acme', 'center' => $plant->id, 'from' => '2026-11-02', 'to' => '2026-11-08']))->assertOk()->streamedContent();

    $log = File::get($this->logPath);
    expect(substr_count($log, '"attendance.mark_rejected"'))->toBe(2);
    foreach (['attendance.kiosk_issued', 'attendance.enrollment_issued', 'attendance.kiosk_activated', 'attendance.device_enrolled', 'attendance.mark_recorded', 'attendance.week_confirmed', 'attendance.correction_requested', 'attendance.correction_resolved', 'attendance.report_exported'] as $event) {
        expect($log)->toContain("\"{$event}\"");
    }
    expect($log)
        ->not->toContain('19.43')->not->toContain('-99.13')
        ->not->toContain($device->credential_id)->not->toContain($device->public_key)
        ->not->toContain($qr)->not->toContain($qrLater)->not->toContain($badQr)
        ->not->toContain($enrollToken)
        ->not->toContain(basename($kioskLink))->not->toContain($kioskSecret);
});
