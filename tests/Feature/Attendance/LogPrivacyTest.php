<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Support\OfflineChallenge;
use App\Modules\Attendance\Support\OfflineTicket;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Notifications\WorkerInvitation;
use App\Modules\WorkerAccess\Support\OfflinePasses;
use App\Modules\WorkerAccess\Support\WebAuthnCeremony;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\OfflineDeliveries;
use Tests\Support\SoftAuthenticator;
use Tests\Support\SoftDeviceKey;

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

it('logs no position, credential, screen code, access link or kiosk token along a whole marking flow, rejections included', function (): void {
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

    // RH puts a screen at the plant and invites Ana.
    $this->actingAs($hr)->post(route('tenant.attendance.kiosks.store', 'acme'), ['work_center_id' => $plant->id])->assertRedirect();
    $kioskLink = (string) session('inertia.flash_data.kioskLink');
    $this->from(route('tenant.worker-access.index', 'acme'))->post(route('tenant.worker-access.invite', 'acme'), ['employee_id' => $ana->id])->assertRedirect();
    $accessUrl = '';
    Notification::assertSentOnDemand(WorkerInvitation::class, function (WorkerInvitation $notification, array $channels, AnonymousNotifiable $notifiable) use (&$accessUrl): bool {
        $accessUrl = (string) $notification->toMail($notifiable)->actionUrl;

        return true;
    });
    $accessToken = basename($accessUrl);
    auth()->logout();

    // The screen opens its link and shows its code.
    $activated = $this->get($kioskLink)->assertRedirect();
    $kioskSecret = (string) $activated->getCookie('attendance_kiosk')?->getValue();
    $qr = (string) $this->withCredentials()->withCookie('attendance_kiosk', $kioskSecret)->getJson(route('attendance.device.kiosk.qr', 'acme'))->assertOk()->json('token');

    // Ana opens her link and creates her key on her phone.
    $this->post(route('worker.link.open', ['acme', $accessToken]))->assertRedirect(route('worker.key.show', 'acme'));
    $options = $this->postJson(route('worker.key.options', 'acme'))->assertOk()->json();
    $this->postJson(route('worker.key.store', 'acme'), ['label' => 'iPhone', 'credential' => $phone->create($options)])->assertOk();
    $device = WorkerCredential::query()->sole();

    // She accepts the agreement and marks her start at the door and her end in the afternoon.
    $session = $this->get(route('attendance.device.punch.show', ['acme', 'q' => $qr]))->assertOk()->getCookie($cookie)?->getValue() ?? '';
    privacySigned($phone, $ana->id, $session, 'attendance.device.agreement.options', route('attendance.device.agreement.accept', 'acme'), ['agreement_version' => 1])->assertOk();
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
    $week = WeekPeriod::ofDate(CarbonImmutable::parse('2026-11-02'), 'America/Mexico_City');
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
    foreach (['attendance.kiosk_issued', 'worker_access.link_issued', 'attendance.kiosk_activated', 'worker_access.key_registered', 'attendance.agreement_accepted', 'attendance.mark_recorded', 'attendance.week_confirmed', 'attendance.correction_requested', 'attendance.correction_resolved', 'attendance.report_exported'] as $event) {
        expect($log)->toContain("\"{$event}\"");
    }
    expect($log)
        // The whole coordinates: a short prefix like '19.43' matches a log timestamp such as `…:19.4312`, with nothing leaked.
        ->not->toContain('19.4327')->not->toContain('19.4336')->not->toContain('-99.1332')
        ->not->toContain($device->credential_id)->not->toContain($device->public_key)
        ->not->toContain($qr)->not->toContain($qrLater)->not->toContain($badQr)
        ->not->toContain($accessToken)
        ->not->toContain(basename($kioskLink))->not->toContain($kioskSecret);
});

it('logs no ticket, screen code, position, note, pass, signature or credential along a delivery of offline marks, refusals included', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', 'UTC'));
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create();
    $other = WorkCenter::factory()->for($tenant)->create();
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id]);
    $phone = new SoftAuthenticator;
    $ceremony = resolve(WebAuthnCeremony::class);
    $registered = $ceremony->verifyRegistration($ana->id, $phone->create($ceremony->registrationOptions($ana->id, 'Ana')));
    $credential = WorkerCredential::factory()->create([
        'tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'credential_id' => $registered->credentialId, 'public_key' => $registered->publicKey,
        'sign_count' => $registered->signCount, 'backup_eligible' => $registered->backupEligible, 'backup_state' => $registered->backupState,
    ]);
    $deviceKey = new SoftDeviceKey;
    $key = WorkerDeviceKey::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'public_key' => $deviceKey->publicKey()]);
    agreementAccepted($ana->id);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));
    $pass = OfflinePasses::issue($key);
    $ticket = function () use ($ana): string {
        $now = CarbonImmutable::now();
        test()->travelTo(CarbonImmutable::parse('2026-11-09 13:00:00', 'UTC'));
        $ticket = resolve(OfflineTicket::class)->batch($ana->id)[0]['ticket'];
        test()->travelTo($now);

        return $ticket;
    };
    $qr = QrToken::issue($tenant->id, $plant->id, CarbonImmutable::parse('2026-11-09 13:50:00', 'UTC'));

    // Five marks of one delivery: accepted with a QR, a note and a position; rejected for the center; by the key of the device
    // with its pass; one signed wrong; and one with a ticket that is not ours.
    $mark = function (MarkType $type, int $seq, string $declared, string $center, string $ticket, ?string $qr, string $signer, bool $tamper = false) use ($tenant, $ana, $phone, $deviceKey, $key, $pass): array {
        $at = CarbonImmutable::parse("2026-11-09 {$declared}", 'UTC')->getTimestamp();
        $challenge = OfflineChallenge::hash(OfflineChallenge::canonical($tenant->id, $ana->id, $center, $type, $qr, $at, 1943271, -9913320, 10, 1, $seq, $signer === 'device_key' ? $pass['passId'] : null, $ticket));

        return [
            'seq' => $seq, 'type' => $type->value, 'ticket' => $ticket, 'center_id' => $center, 'declared_at' => $at,
            ...($qr === null ? [] : ['qr' => $qr]), 'lat_e5' => 1943271, 'lng_e5' => -9913320, 'accuracy_m' => 10, 'justification' => 'Me sentí mal y fui al médico.', 'agreement_version' => 1, 'signer' => $signer,
            ...($signer === 'passkey'
                ? ['credential' => $phone->get(['challenge' => Base64UrlSafe::encodeUnpadded($challenge)], $ana->id, tamper: $tamper)]
                : ['device_key_id' => $key->id, 'signature' => $deviceKey->sign($challenge), 'pass' => $pass['pass']]),
        ];
    };
    $accepted = $mark(MarkType::Start, 0, '14:00:00', $plant->id, $ticket(), $qr, 'passkey');
    $wrongCenter = $mark(MarkType::End, 1, '14:10:00', $other->id, $ticket(), null, 'passkey');
    $byKey = $mark(MarkType::End, 2, '14:20:00', $plant->id, $ticket(), null, 'device_key');
    $forged = $mark(MarkType::BreakStart, 3, '14:30:00', $plant->id, $ticket(), null, 'passkey', tamper: true);
    $foreign = [...$accepted, 'seq' => 4, 'ticket' => 'v1.'.str_repeat('a', 200).'.'.str_repeat('b', 30)];

    $response = $this->postJson(route('attendance.device.punch.offline', 'acme'), ['items' => [$accepted, $wrongCenter, $byKey, $forged, $foreign]])->assertOk();
    $this->postJson(route('attendance.device.tickets', 'acme'), ['ticket' => $accepted['ticket'], 'queue' => ['count' => 4, 'oldest_age_minutes' => 190]])->assertOk();

    expect(array_column($response->json('results'), 'status'))->toBe(['received', 'rejected', 'received', 'rejected', 'rejected']);

    $log = File::get($this->logPath);
    foreach (['attendance.offline_mark_received', 'attendance.offline_mark_rejected', 'attendance.offline_queue_reported'] as $event) {
        expect($log)->toContain("\"{$event}\"");
    }
    expect($log)->toContain('"queue_age_minutes"')->toContain('"interval_minutes"')
        ->not->toContain($accepted['ticket'])->not->toContain($byKey['ticket'])->not->toContain($wrongCenter['ticket'])->not->toContain($forged['ticket'])
        ->not->toContain($qr)->not->toContain('Me sentí mal')
        ->not->toContain('19.43271')->not->toContain('-99.1332')->not->toContain('1943271')->not->toContain('-9913320')
        ->not->toContain($pass['pass'])->not->toContain($byKey['signature'])->not->toContain($forged['credential']['response']['signature'])
        ->not->toContain($accepted['credential']['response']['signature'])->not->toContain($credential->credential_id)->not->toContain($key->id);
});

it('logs and audits nothing of what RH writes when it reviews the marks made without a connection', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($tenant);
    $plant = WorkCenter::factory()->for($tenant)->create();
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Ana López']);
    $hr = memberOf($tenant, TenantRole::Hr);
    $verified = OfflineDeliveries::accepted($ana, '2026-11-02 08:13', justification: 'Estuve en urgencias con mi hijo');
    $many = [OfflineDeliveries::accepted($ana, '2026-11-03 08:13')->mark_id, OfflineDeliveries::accepted($ana, '2026-11-04 08:13')->mark_id];
    $incorrect = OfflineDeliveries::accepted(EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $plant->id, 'name' => 'Beatriz Soto']), '2026-11-05 08:13');
    $this->actingAs($hr)->from(route('tenant.attendance.corrections.index', 'acme'));

    $this->post(route('tenant.attendance.offline.verify', ['acme', $verified->mark_id]), ['note' => 'Confirmado con su jefa Marisol'])->assertSessionHasNoErrors();
    $this->post(route('tenant.attendance.offline.verify-many', 'acme'), ['ids' => $many])->assertSessionHasNoErrors();
    $this->post(route('tenant.attendance.offline.reject', ['acme', $incorrect->mark_id]), ['note' => 'La cámara la muestra entrando tarde', 'corrected_at' => '2026-11-05 07:41'])->assertSessionHasNoErrors();

    $log = File::get($this->logPath);
    $audit = json_encode(AuditEntry::query()->get(), JSON_THROW_ON_ERROR);
    expect(substr_count($log, '"attendance.offline_mark_reviewed"'))->toBe(4)
        ->and(AuditEntry::query()->where('event', 'attendance.offline_mark_reviewed')->count())->toBe(4);
    foreach ([$log, $audit] as $text) {
        expect($text)->not->toContain('urgencias')->not->toContain('Marisol')->not->toContain('cámara')->not->toContain('07:41')->not->toContain('08:13')
            ->not->toContain('13:41')->not->toContain('14:13');
    }
});
