<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\RecordOfflineMark;
use App\Modules\Attendance\Data\OfflineOutcome;
use App\Modules\Attendance\Data\OfflineSubmission;
use App\Modules\Attendance\Enums\EvidenceLevel;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Enums\SignerType;
use App\Modules\Attendance\Enums\TimeBasis;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Events\OfflineMarkAccepted;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceOfflineMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\OfflineChallenge;
use App\Modules\Attendance\Support\OfflineTicket;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Attendance\Support\VerifiedOfflineMark;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Support\OfflinePasses;
use App\Modules\WorkerAccess\Support\WebAuthnCeremony;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\AttendanceOfflineMarkFactory;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\SoftAuthenticator;
use Tests\Support\SoftDeviceKey;

mutates(RecordOfflineMark::class, VerifiedOfflineMark::class);

/*
 * RecordOfflineMark: what becomes of each mark a phone made without a connection (criterio 3.3.1). Every time is UTC on
 * Monday 9 November 2026; the receipt is passed to the action, so no test waits.
 */
beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => ['http://localhost']]);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte', 'timezone' => 'America/Mexico_City']);
    $this->zone = AttendanceZone::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id, 'latitude' => 19.432712, 'longitude' => -99.133298, 'radius_m' => 50]);
    $this->ana = romPerson($this->tenant, $this->plant);
    agreementAccepted($this->ana['employee']->id);
    $this->key = WorkerDeviceKey::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana['employee']->id, 'public_key' => ($this->deviceKey = new SoftDeviceKey)->publicKey()]);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));
    $this->pass = OfflinePasses::issue($this->key);
    $this->record = resolve(RecordOfflineMark::class);
});

/** A person of the company with a passkey of their own, as the phone keeps it. */
function romPerson(Tenant $tenant, WorkCenter $center): array
{
    $employee = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
    $phone = new SoftAuthenticator;
    $ceremony = resolve(WebAuthnCeremony::class);
    $registered = $ceremony->verifyRegistration($employee->id, $phone->create($ceremony->registrationOptions($employee->id, 'Ana')));
    $credential = WorkerCredential::factory()->create([
        'tenant_id' => $tenant->id,
        'employee_id' => $employee->id,
        'credential_id' => $registered->credentialId,
        'public_key' => $registered->publicKey,
        'sign_count' => $registered->signCount,
        'backup_eligible' => $registered->backupEligible,
        'backup_state' => $registered->backupState,
    ]);

    return ['employee' => $employee, 'phone' => $phone, 'credential' => $credential];
}

function romAt(string $time): CarbonImmutable
{
    return CarbonImmutable::parse(str_contains($time, '-') ? $time : "2026-11-09 {$time}", 'UTC');
}

/** A ticket of `$employee` issued at `$issued`, whatever the clock says now. */
function romTicket(string $issued, ?Employee $employee = null): string
{
    $now = CarbonImmutable::now();
    test()->travelTo(romAt($issued));
    $ticket = resolve(OfflineTicket::class)->batch(($employee ?? test()->ana['employee'])->id)[0]['ticket'];
    test()->travelTo($now);

    return $ticket;
}

/**
 * The delivery element a phone would send: the challenge rebuilt and signed by the person's passkey or by their device key.
 *
 * @param  array<string, mixed>  $o
 */
function romMark(array $o = []): OfflineSubmission
{
    $who = $o['who'] ?? test()->ana;
    $type = $o['type'] ?? MarkType::Start;
    $declared = romAt($o['declared'] ?? '14:00:00');
    $ticket = $o['ticket'] ?? romTicket($o['issued'] ?? '13:00:00', $who['employee']);
    $center = $o['center'] ?? test()->plant->id;
    $position = array_key_exists('position', $o) ? $o['position'] : [1943271, -9913320, 10];
    $signer = $o['signer'] ?? 'passkey';
    $pass = $signer === 'device_key' ? ($o['pass'] ?? test()->pass['pass']) : null;
    $passId = $signer === 'device_key' ? ($o['passId'] ?? test()->pass['passId']) : null;
    $challenge = OfflineChallenge::hash(OfflineChallenge::canonical(
        test()->tenant->id, $who['employee']->id, $center, $type, $o['qr'] ?? null, $declared->getTimestamp(),
        $position[0] ?? null, $position[1] ?? null, $position[2] ?? null, $o['agreement'] ?? 1, $o['seq'] ?? 0, $passId, $ticket,
    ));

    return new OfflineSubmission(
        seq: $o['seq'] ?? 0,
        type: $type,
        ticket: $ticket,
        workCenterId: $center,
        qr: $o['qr'] ?? null,
        declaredAt: $declared,
        latE5: $position[0] ?? null,
        lngE5: $position[1] ?? null,
        accuracyM: $position[2] ?? null,
        deviceClockAt: isset($o['clock']) ? romAt($o['clock']) : null,
        justification: $o['note'] ?? null,
        agreementVersion: $o['agreement'] ?? 1,
        signerType: $signer === 'device_key' ? SignerType::DeviceKey : SignerType::Passkey,
        credential: $signer === 'passkey' ? $who['phone']->get(['challenge' => Base64UrlSafe::encodeUnpadded($challenge)], $who['employee']->id, tamper: $o['tamper'] ?? false) : null,
        deviceKeyId: $signer === 'device_key' ? test()->key->id : null,
        signature: $signer === 'device_key' ? test()->deviceKey->sign($challenge) : null,
        pass: $pass,
    );
}

function romDeliver(OfflineSubmission $mark, string $received = '15:00:00'): OfflineOutcome
{
    return test()->record->handle($mark, romAt($received));
}

/** A mark made with a connection, as the marking page leaves it. */
function romOnline(MarkType $type, string $at, ?Employee $employee = null): AttendanceMark
{
    return AttendanceMark::factory()->create([
        'tenant_id' => test()->tenant->id,
        'employee_id' => ($employee ?? test()->ana['employee'])->id,
        'work_center_id' => test()->plant->id,
        'type' => $type,
        'marked_at' => romAt($at),
        'method' => MarkMethod::QrAndGeofence,
    ]);
}

function romCounted(): AttendanceMark
{
    return AttendanceMark::query()->orderByDesc('marked_at')->firstOrFail();
}

describe('what is accepted', function (): void {
    it('counts a mark with a recent QR as offline_qr, medium, and raises the declared time to the start of its window', function (): void {
        $qr = QrToken::issue($this->tenant->id, $this->plant->id, romAt('13:50:00'));

        $outcome = romDeliver(romMark(['qr' => $qr, 'declared' => '13:40:00']));
        $mark = AttendanceMark::query()->sole();

        expect($outcome->toArray())->toBe(['seq' => 0, 'status' => 'received'])
            ->and($mark->method)->toBe(MarkMethod::OfflineQr)
            ->and($mark->method->evidence())->toBe(EvidenceLevel::Medium)
            ->and($mark->marked_at?->equalTo(romAt('13:50:00')))->toBeTrue();

        $row = AttendanceOfflineMark::query()->sole();
        expect($row)
            ->outcome->toBe('accepted')
            ->time_basis->toBe(TimeBasis::QrWindow)
            ->mark_id->toBe($mark->id)
            ->and($row->window_start?->equalTo(romAt('13:50:00')))->toBeTrue()
            ->and($row->review_flags->map->value->all())->toBe(['declared_adjusted']);
    });

    it('counts a mark with no QR as offline_declared, basic, at the declared time', function (): void {
        romDeliver(romMark(['declared' => '14:00:00']));
        $mark = AttendanceMark::query()->sole();

        expect($mark->method)->toBe(MarkMethod::OfflineDeclared)
            ->and($mark->method->evidence())->toBe(EvidenceLevel::Basic)
            ->and($mark->marked_at?->equalTo(romAt('14:00:00')))->toBeTrue()
            ->and(AttendanceOfflineMark::query()->sole())->time_basis->toBe(TimeBasis::DeclaredInterval)->window_start->toBeNull();
    });

    it('counts the exit of a day declared at 17:00 and delivered at 19:00, with the wide interval flag', function (): void {
        romOnline(MarkType::Start, '08:00:00');

        romDeliver(romMark(['type' => MarkType::End, 'issued' => '08:00:00', 'declared' => '17:00:00']), '19:00:00');

        expect(romCounted()->marked_at?->equalTo(romAt('17:00:00')))->toBeTrue()
            ->and(AttendanceOfflineMark::query()->sole()->review_flags->map->value->all())->toBe(['wide_interval']);
    });

    it('counts a time declared before the ticket just after the mark before it', function (): void {
        romOnline(MarkType::Start, '08:00:00');

        romDeliver(romMark(['type' => MarkType::End, 'issued' => '08:00:00', 'declared' => '07:30:00']), '08:30:00');

        expect(romCounted()->marked_at?->format('H:i:s.u'))->toBe('08:00:00.000001')
            ->and(AttendanceOfflineMark::query()->sole()->review_flags->map->value->all())->toBe(['declared_adjusted']);
    });

    it('takes a whole day without a connection in one delivery, in order, at the declared times', function (): void {
        $day = [[MarkType::Start, '14:00:00'], [MarkType::BreakStart, '18:00:00'], [MarkType::BreakEnd, '18:30:00'], [MarkType::End, '22:00:00']];

        foreach ($day as $seq => [$type, $declared]) {
            expect(romDeliver(romMark(['type' => $type, 'issued' => '06:00:00', 'declared' => $declared, 'seq' => $seq]), '23:00:00')->status)->toBe('received');
        }

        expect(AttendanceMark::query()->orderBy('marked_at')->get()->map(fn (AttendanceMark $mark): string => $mark->type->value.' '.$mark->marked_at?->format('H:i'))->all())
            ->toBe(['start 14:00', 'break_start 18:00', 'break_end 18:30', 'end 22:00']);
    });

    it('is medium with a device key and a QR and basic with a declared time, with no credential on the mark', function (): void {
        $qr = QrToken::issue($this->tenant->id, $this->plant->id, romAt('13:50:00'));

        romDeliver(romMark(['signer' => 'device_key', 'qr' => $qr]));
        romDeliver(romMark(['signer' => 'device_key', 'type' => MarkType::End, 'declared' => '14:30:00']));
        $marks = AttendanceMark::query()->orderBy('marked_at')->get();

        expect($marks[0]->method->evidence())->toBe(EvidenceLevel::Medium)
            ->and($marks[1]->method->evidence())->toBe(EvidenceLevel::Basic)
            ->and($marks->pluck('credential_id')->filter()->all())->toBe([])
            ->and(AttendanceOfflineMark::query()->get()->map(fn (AttendanceOfflineMark $row): string => $row->signer_type->value.' '.$row->signer_id)->unique()->all())
            ->toBe(['device_key '.$this->key->id]);
    });

    it('keeps where the QR proved the person stood when it is another center of the company, in that center\'s zone', function (): void {
        $other = WorkCenter::factory()->for($this->tenant)->create(['timezone' => 'America/Tijuana']);
        $qr = QrToken::issue($this->tenant->id, $other->id, romAt('13:50:00'));

        romDeliver(romMark(['qr' => $qr, 'declared' => '14:00:00']));
        $mark = AttendanceMark::query()->sole();

        expect($mark)->away_center_id->toBe($other->id)->work_center_id->toBe($this->plant->id)->timezone->toBe('America/Tijuana')
            ->and($mark->method)->toBe(MarkMethod::OfflineQr)
            ->and(AttendanceOfflineMark::query()->sole())->away_center_id->toBe($other->id);
    });

    it('flags a mark with no position and keeps no zone for it', function (): void {
        romDeliver(romMark(['position' => null]));

        expect(AttendanceMark::query()->sole())->zone_id->toBeNull()->latitude->toBeNull()->accuracy_m->toBeNull()
            ->and(AttendanceOfflineMark::query()->sole()->review_flags->map->value->all())->toBe(['no_position']);
    });

    it('writes on the mark the columns of one made with a connection', function (): void {
        romDeliver(romMark(['position' => [1943271, -9913320, 10]]));
        $mark = AttendanceMark::query()->sole();

        expect($mark)
            ->latitude->toBe('19.432710')
            ->longitude->toBe('-99.133200')
            ->accuracy_m->toBe(10.0)
            ->zone_id->toBe($this->zone->id)
            ->credential_id->toBe($this->ana['credential']->id)
            ->timezone->toBe('America/Mexico_City')
            ->away_center_id->toBeNull()
            ->employee_id->toBe($this->ana['employee']->id)
            ->work_center_id->toBe($this->plant->id)
            ->and($mark->distance_m)->toBeFloat()->toBeLessThan(50.0)
            ->and(AttendanceOfflineMark::query()->sole())
            ->signer_type->toBe(SignerType::Passkey)->signer_id->toBe($this->ana['credential']->id)
            ->mark_type->toBe(MarkType::Start)->seq->toBe(0)->agreement_version->toBe(1);
    });

    it('keeps the note and the hint of the phone\'s clock only for Recursos Humanos', function (): void {
        romDeliver(romMark(['note' => 'Se fue la luz en la planta.', 'clock' => '09:00:00']));

        expect(AttendanceOfflineMark::query()->sole())->justification->toBe('Se fue la luz en la planta.')
            ->and(AttendanceOfflineMark::query()->sole()->device_clock_at?->equalTo(romAt('09:00:00')))->toBeTrue();
    });

    it('flags a position outside the zone and still takes the mark under a policy that blocks out of zone', function (): void {
        // The policy is the company's default: block.
        romDeliver(romMark(['position' => [1943360, -9913320, 10]]));

        expect(AttendanceMark::query()->sole())->zone_id->toBeNull()
            ->and(AttendanceOfflineMark::query()->sole()->review_flags->map->value->all())->toBe(['outside_zone']);
    });
});

describe('the edges', function (): void {
    it('treats a QR whose HMAC does not verify as no QR, without rejecting', function (): void {
        $outcome = romDeliver(romMark(['qr' => '12345.'.str_repeat('f', 64)]));

        expect($outcome->status)->toBe('received')
            ->and(AttendanceMark::query()->sole()->method)->toBe(MarkMethod::OfflineDeclared)
            ->and(AttendanceOfflineMark::query()->sole())->time_basis->toBe(TimeBasis::DeclaredInterval)->window_start->toBeNull();
    });

    it('treats a QR read 31 minutes before the declared time as an old window and a declared interval, and one 30 minutes before as a QR window', function (): void {
        $qr = QrToken::issue($this->tenant->id, $this->plant->id, romAt('13:20:00'));

        romDeliver(romMark(['qr' => $qr, 'declared' => '13:50:00']));
        $fresh = AttendanceOfflineMark::query()->sole();
        romDeliver(romMark(['type' => MarkType::End, 'qr' => $qr, 'declared' => '13:51:00']));
        $old = AttendanceOfflineMark::query()->where('mark_type', 'end')->sole();

        expect($fresh)->time_basis->toBe(TimeBasis::QrWindow)
            ->and($fresh->review_flags->map->value->all())->toBe([])
            ->and($old)->time_basis->toBe(TimeBasis::DeclaredInterval)
            ->and($old->review_flags->map->value->all())->toBe(['old_window'])
            ->and($old->window_start?->equalTo(romAt('13:20:00')))->toBeTrue()
            ->and(AttendanceMark::query()->where('type', 'end')->sole()->method)->toBe(MarkMethod::OfflineDeclared);
    });

    it('flags a position in a center with no zones as zones_not_configured, never outside_zone, and takes the mark', function (): void {
        $other = WorkCenter::factory()->for($this->tenant)->create();
        $person = romPerson($this->tenant, $other);
        agreementAccepted($person['employee']->id);

        romDeliver(romMark(['who' => $person, 'center' => $other->id]));

        expect(AttendanceMark::query()->sole())->zone_id->toBeNull()->latitude->not->toBeNull()
            ->and(AttendanceOfflineMark::query()->sole()->review_flags->map->value->all())->toBe(['zones_not_configured']);
    });

    it('keeps no_position for a mark with no position, in a center with zones or without them', function (): void {
        $other = WorkCenter::factory()->for($this->tenant)->create();
        $person = romPerson($this->tenant, $other);
        agreementAccepted($person['employee']->id);

        romDeliver(romMark(['who' => $person, 'center' => $other->id, 'position' => null]));

        expect(AttendanceOfflineMark::query()->sole()->review_flags->map->value->all())->toBe(['no_position']);
    });

    it('serves a ticket of 35 days exactly and refuses one of 35 days and a second', function (): void {
        $ticket = romTicket('2026-10-05 15:00:00');

        expect(romDeliver(romMark(['ticket' => $ticket, 'declared' => '2026-10-05 16:00:00']))->status)->toBe('received');

        $late = romMark(['ticket' => romTicket('2026-10-05 14:59:59'), 'type' => MarkType::End, 'declared' => '2026-10-05 16:00:00']);
        $outcome = romDeliver($late);

        expect($outcome->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => 'ticket_expired'])
            ->and(AttendanceOfflineMark::query()->where('outcome', 'rejected')->sole()->reason)->toBe(OfflineRejection::TicketExpired);
    });

    it('counts the declared time bounded for a ticket of 63 hours, with the wide interval flag', function (): void {
        romDeliver(romMark(['issued' => '2026-11-06 22:00:00', 'declared' => '12:00:00']), '13:00:00');

        expect(AttendanceMark::query()->sole()->marked_at?->equalTo(romAt('12:00:00')))->toBeTrue()
            ->and(AttendanceOfflineMark::query()->sole()->review_flags->map->value->all())->toBe(['wide_interval']);
    });

    it('counts the lower bound for a ticket of 80 hours, flagged wide and adjusted', function (): void {
        romDeliver(romMark(['issued' => '2026-11-06 07:00:00', 'declared' => '14:00:00']), '15:00:00');

        expect(AttendanceMark::query()->sole()->marked_at?->equalTo(romAt('2026-11-06 07:00:00')))->toBeTrue()
            ->and(AttendanceOfflineMark::query()->sole()->review_flags->map->value->all())->toBe(['wide_interval', 'declared_adjusted']);
    });

    it('answers the same delivery twice with a duplicate and writes nothing the second time', function (): void {
        $mark = romMark();

        $first = romDeliver($mark);
        $again = romDeliver($mark);

        expect($first->status)->toBe('received')
            ->and($again->toArray())->toBe(['seq' => 0, 'status' => 'duplicate', 'stored_status' => 'received'])
            ->and(AttendanceMark::query()->count())->toBe(1)
            ->and(AttendanceOfflineMark::query()->count())->toBe(1);
    });

    it('answers a stored rejection repeated with a duplicate that says it was rejected, and why', function (): void {
        $other = WorkCenter::factory()->for($this->tenant)->create();
        $mark = romMark(['center' => $other->id]);

        $first = romDeliver($mark);
        $again = romDeliver($mark);

        expect($first->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => 'wrong_center'])
            ->and($again->toArray())->toBe(['seq' => 0, 'status' => 'duplicate', 'reason' => 'wrong_center', 'stored_status' => 'rejected'])
            ->and(AttendanceOfflineMark::query()->count())->toBe(1);
    });

    it('keeps the center of the person on a delivery rejected for the wrong one, never the one it named', function (): void {
        romDeliver(romMark(['center' => '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c']));

        expect(AttendanceOfflineMark::query()->sole())->work_center_id->toBe($this->plant->id)->reason->toBe(OfflineRejection::WrongCenter)->outcome->toBe('rejected')
            ->and(AttendanceMark::query()->count())->toBe(0);
    });

    it('answers a conflict, with no row, to the same ticket signed for another mark', function (): void {
        $ticket = romTicket('13:00:00');
        romDeliver(romMark(['ticket' => $ticket, 'seq' => 0]));

        $outcome = romDeliver(romMark(['ticket' => $ticket, 'seq' => 1, 'type' => MarkType::End, 'declared' => '14:30:00']));

        expect($outcome->toArray())->toBe(['seq' => 1, 'status' => 'conflict', 'reason' => 'ticket_reused'])
            ->and(AttendanceOfflineMark::query()->count())->toBe(1)
            ->and(AttendanceMark::query()->count())->toBe(1);
    });
});

describe('what is refused', function (): void {
    it('answers ticket_invalid, with no row, to a ticket that is not ours, signed with a key that is no longer one', function (): void {
        $key = config()->string('app.key');
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $foreign = romTicket('13:00:00');
        config(['app.key' => $key]);

        foreach (['not a ticket', $foreign] as $ticket) {
            $outcome = romDeliver(romMark(['ticket' => $ticket]));

            expect($outcome->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => 'ticket_invalid'])
                ->and($outcome->signedEmployeeId)->toBeNull();
        }

        expect(AttendanceOfflineMark::query()->count())->toBe(0);
    });

    it('answers ticket_invalid to the ticket of another company', function (): void {
        $other = Tenant::factory()->create();
        workIn($other);
        $stranger = EmployeeFactory::new()->create(['tenant_id' => $other->id]);
        $ticket = resolve(OfflineTicket::class)->batch($stranger->id)[0]['ticket'];
        workIn($this->tenant);

        expect(romDeliver(romMark(['ticket' => $ticket]))->reason)->toBe('ticket_invalid')
            ->and(AttendanceOfflineMark::query()->count())->toBe(0);
    });

    it('answers signature_invalid with no row and spends no ticket: the genuine mark with it enters afterwards', function (): void {
        $ticket = romTicket('13:00:00');

        $forged = romDeliver(romMark(['ticket' => $ticket, 'tamper' => true]));
        $genuine = romDeliver(romMark(['ticket' => $ticket]));

        expect($forged->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => 'signature_invalid'])
            ->and($forged->signedEmployeeId)->toBeNull()
            ->and($genuine->status)->toBe('received')
            ->and(AttendanceOfflineMark::query()->count())->toBe(1);
    });

    it('answers signature_invalid to a signature of another person than the ticket\'s', function (): void {
        $beto = romPerson($this->tenant, $this->plant);

        // Beto signs a challenge that names Ana's ticket and Ana: his key does not verify for what Ana's ticket says.
        $outcome = romDeliver(romMark(['who' => ['employee' => $this->ana['employee'], 'phone' => $beto['phone']]]));

        expect($outcome->reason)->toBe('signature_invalid')->and(AttendanceOfflineMark::query()->count())->toBe(0);
    });

    it('answers signature_invalid to a device key with a pass that is not ours', function (): void {
        $outcome = romDeliver(romMark(['signer' => 'device_key', 'pass' => 'v1.nope']));

        expect($outcome->reason)->toBe('signature_invalid')->and(AttendanceOfflineMark::query()->count())->toBe(0);
    });

    it('answers person_inactive, with no row, for a person the company no longer has', function (): void {
        $mark = romMark();
        // Gone from the roster between the ticket and the delivery.
        $this->mock(App\Modules\Organization\Contracts\Roster::class, fn ($roster) => $roster->shouldReceive('find')->with($this->ana['employee']->id)->andReturnNull());

        expect(resolve(RecordOfflineMark::class)->handle($mark, romAt('15:00:00'))->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => 'person_inactive'])
            ->and(AttendanceOfflineMark::query()->count())->toBe(0);
    });

    it('rejects, with its row, a person who left, who never accepted the agreement or who marks for another center', function (string $cause, string $reason): void {
        $who = $this->ana;
        $center = $this->plant->id;

        match ($cause) {
            'left' => DB::table('employees')->where('id', $this->ana['employee']->id)->update(['deactivated_at' => now()]),
            'agreement' => $who = romPerson($this->tenant, $this->plant),
            'center' => $center = WorkCenter::factory()->for($this->tenant)->create()->id,
        };

        $outcome = romDeliver(romMark(['who' => $who, 'center' => $center]));

        expect($outcome->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => $reason])
            ->and(AttendanceOfflineMark::query()->sole())->outcome->toBe('rejected')->mark_id->toBeNull()
            ->and(AttendanceMark::query()->count())->toBe(0);
    })->with([
        'a person who left' => ['left', 'person_inactive'],
        'no agreement accepted' => ['agreement', 'agreement_not_accepted'],
        'another center' => ['center', 'wrong_center'],
    ]);

    it('does not reject a mark for a version of the agreement published while the phone was without a connection', function (): void {
        // The ticket is of 8 November; version 2 of the text appears on the 9th and Ana only accepted version 1.
        DB::table('attendance_agreements')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'version' => 2, 'body' => 'Texto nuevo', 'created_at' => romAt('12:00:00')]);

        $before = romDeliver(romMark(['issued' => '2026-11-08 12:00:00', 'declared' => '2026-11-08 13:00:00', 'agreement' => 1]), '15:00:00');
        $after = romDeliver(romMark(['issued' => '2026-11-09 13:00:00', 'declared' => '14:00:00', 'type' => MarkType::End, 'agreement' => 1]), '15:00:00');

        expect($before->status)->toBe('received')
            ->and($after->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => 'agreement_not_accepted']);
    });

    it('rejects a device key mark declared after its pass expired or whose pass was issued after the receipt', function (string $cause): void {
        $mark = match ($cause) {
            'expired' => romMark(['signer' => 'device_key', 'issued' => '2026-11-10 15:30:00', 'declared' => '2026-11-10 16:00:00']),
            'later' => romMark(['signer' => 'device_key']),
        };

        $outcome = romDeliver($mark, $cause === 'expired' ? '2026-11-10 17:00:00' : '14:30:00');

        expect($outcome->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => 'pass_expired'])
            ->and(AttendanceOfflineMark::query()->sole()->reason)->toBe(OfflineRejection::PassExpired);
    })->with(['declared after the expiry' => ['expired'], 'issued after the receipt' => ['later']]);

    it('accepts a device key mark declared at the very instant its pass expires', function (): void {
        $outcome = romDeliver(romMark(['signer' => 'device_key', 'issued' => '2026-11-10 14:00:00', 'declared' => '2026-11-10 15:00:00']), '2026-11-10 15:30:00');

        expect($outcome->status)->toBe('received');
    });
});

describe('the sequence', function (): void {
    it('rejects, with its row, a start with the day open, an exit with no start and a time that leaves no room', function (string $case): void {
        match ($case) {
            'open' => romOnline(MarkType::Start, '13:30:00'),
            'no start' => null,
            'no room' => romOnline(MarkType::Start, '13:00:00'),
        };
        $mark = match ($case) {
            'open' => romMark(['declared' => '14:00:00']),
            'no start' => romMark(['type' => MarkType::End]),
            'no room' => romMark(['type' => MarkType::End, 'issued' => '12:00:00', 'declared' => '13:00:00']),
        };

        $outcome = romDeliver($mark, $case === 'no room' ? '13:00:00' : '15:00:00');

        expect($outcome->toArray())->toBe(['seq' => 0, 'status' => 'rejected', 'reason' => 'sequence_conflict'])
            ->and(AttendanceOfflineMark::query()->sole()->reason)->toBe(OfflineRejection::SequenceConflict)
            ->and(AttendanceMark::query()->where('method', '!=', MarkMethod::QrAndGeofence->value)->count())->toBe(0);
    })->with(['the day is open' => ['open'], 'no start' => ['no start'], 'lower past upper' => ['no room']]);

    it('rejects the mark that depended on one that conflicted', function (): void {
        $first = romDeliver(romMark(['type' => MarkType::End, 'seq' => 0]));
        $second = romDeliver(romMark(['type' => MarkType::BreakStart, 'seq' => 1, 'declared' => '14:10:00']));

        expect([$first->reason, $second->reason])->toBe(['sequence_conflict', 'sequence_conflict'])
            ->and(AttendanceMark::query()->count())->toBe(0);
    });

    it('rejects a mark that would leave the one after it out of order', function (): void {
        romOnline(MarkType::Start, '14:30:00');

        // A start declared before another one the day already has would leave two starts in a row.
        $outcome = romDeliver(romMark(['issued' => '12:00:00', 'declared' => '13:00:00']));

        expect($outcome->reason)->toBe('sequence_conflict');
    });

    it('answers a mark already made with a connection as a duplicate with no row, on either side of the time declared', function (string $declared): void {
        Log::spy();
        romOnline(MarkType::Start, '08:00:00');
        romOnline(MarkType::End, '17:02:00');

        $outcome = romDeliver(romMark(['type' => MarkType::End, 'issued' => '08:00:00', 'declared' => $declared]), '19:00:00');

        expect($outcome->toArray())->toBe(['seq' => 0, 'status' => 'duplicate', 'reason' => 'already_recorded', 'stored_status' => 'received'])
            ->and(AttendanceOfflineMark::query()->count())->toBe(0);
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'attendance.offline_mark_already_recorded' && ($context['employee_id'] ?? null) === $this->ana['employee']->id)->once();
    })->with(['declared two minutes before' => ['17:00:00'], 'declared three minutes after' => ['17:05:00'], 'ten minutes before' => ['16:52:00']]);

    it('does not take a copy 11 minutes away or a mark made with a connection before the ticket as already recorded', function (string $declared, string $online): void {
        romOnline(MarkType::Start, '07:00:00');
        romOnline(MarkType::End, $online);

        $outcome = romDeliver(romMark(['type' => MarkType::End, 'issued' => '08:00:00', 'declared' => $declared]), '19:00:00');

        expect($outcome->reason)->toBe('sequence_conflict');
    })->with(['11 minutes away' => ['16:51:00', '17:02:00'], 'before the ticket' => ['08:00:00', '07:58:00']]);
});

describe('the revocation', function (): void {
    it('keeps a mark declared before its credential or device key was revoked and refuses one declared after or revoked before the ticket', function (string $signer): void {
        $revoke = fn (string $at) => $signer === 'passkey'
            ? $this->ana['credential']->update(['revoked_at' => romAt($at), 'revoked_reason' => 'not_me'])
            : $this->key->update(['revoked_at' => romAt($at), 'revoked_reason' => 'not_me']);
        $tickets = [romTicket('13:00:00'), romTicket('13:00:00'), romTicket('13:00:00')];
        $revoke('14:00:00');

        $before = romDeliver(romMark(['signer' => $signer, 'ticket' => $tickets[0], 'declared' => '13:59:00']));
        $after = romDeliver(romMark(['signer' => $signer, 'ticket' => $tickets[1], 'declared' => '14:01:00', 'type' => MarkType::End]));
        $revoke('12:00:00');
        $early = romDeliver(romMark(['signer' => $signer, 'ticket' => $tickets[2], 'declared' => '13:30:00', 'type' => MarkType::End]));

        expect($before->status)->toBe('received')
            ->and($after->reason)->toBe('signature_invalid')
            ->and($early->reason)->toBe('signature_invalid')
            ->and(AttendanceOfflineMark::query()->count())->toBe(1);
    })->with(['passkey', 'device_key']);
});

describe('transaction and records', function (): void {
    it('dispatches the accepted mark inside the transaction and writes the audit entry', function (): void {
        $levels = [];
        $baseline = DB::transactionLevel();
        Event::listen(OfflineMarkAccepted::class, function (OfflineMarkAccepted $event) use (&$levels): void {
            $levels[] = [DB::transactionLevel(), $event->markId, $event->employeeId, $event->offlineMarkId];
        });

        romDeliver(romMark());
        $mark = AttendanceMark::query()->sole();
        $row = AttendanceOfflineMark::query()->sole();

        expect($levels)->toHaveCount(1)
            ->and($levels[0][0])->toBeGreaterThan($baseline)
            ->and(array_slice($levels[0], 1))->toBe([$mark->id, $this->ana['employee']->id, $row->id]);

        $audit = AuditEntry::query()->where('event', 'attendance.offline_mark_accepted')->sole();

        expect($audit->subject_id)->toBe($mark->id)
            ->and($audit->metadata)->toEqual(['employee_id' => $this->ana['employee']->id, 'time_basis' => 'declared_interval', 'signer_type' => 'passkey', 'interval_minutes' => 120, 'review_flags' => []]);
    });

    it('reopens the confirmed week an accepted mark falls in, and leaves a disputed one disputed', function (string $state, WeekStatus $after): void {
        $week = WeeklyConfirmation::factory()->{$state}()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana['employee']->id, 'week_start' => '2026-11-09']);

        romDeliver(romMark());

        expect($week->fresh()?->status)->toBe($after);
    })->with(['a confirmed week' => ['confirmed', WeekStatus::Pending], 'a disputed week' => ['disputed', WeekStatus::Disputed]]);

    it('dispatches nothing for a rejected delivery, a duplicate or a refused signature', function (): void {
        Event::fake([OfflineMarkAccepted::class]);

        romDeliver(romMark(['type' => MarkType::End]));
        romDeliver(romMark(['tamper' => true]));
        $mark = romMark();
        romDeliver($mark);
        romDeliver($mark);

        Event::assertDispatchedTimes(OfflineMarkAccepted::class, 1);
        expect(AuditEntry::query()->where('event', 'attendance.offline_mark_accepted')->count())->toBe(1);
    });

    it('logs what was received, rejected and refused, with no ticket, QR, position, note, pass or credential', function (): void {
        Log::spy();
        $qr = QrToken::issue($this->tenant->id, $this->plant->id, romAt('13:50:00'));

        romDeliver(romMark(['qr' => $qr, 'note' => 'Una nota privada', 'declared' => '14:00:00']));
        romDeliver(romMark(['type' => MarkType::End, 'center' => WorkCenter::factory()->for($this->tenant)->create()->id]));
        romDeliver(romMark(['tamper' => true]));

        Log::shouldHaveReceived('info')->withArgs(function (string $message, array $context = []) use ($qr): bool {
            $text = json_encode($context, JSON_THROW_ON_ERROR);

            return $message === 'attendance.offline_mark_received'
                && $context['signer_type'] === 'passkey' && $context['basis'] === 'qr_window' && $context['interval_minutes'] === 120 && $context['queue_age_minutes'] === 60
                && ! str_contains($text, $qr) && ! str_contains($text, 'privada') && ! array_key_exists('ticket', $context) && ! array_key_exists('lat', $context);
        })->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'attendance.offline_mark_rejected' && ($context['reason'] ?? null) === 'wrong_center' && isset($context['offline_mark_id']))->once();
        Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => $message === 'attendance.offline_mark_rejected' && ($context['reason'] ?? null) === 'signature_invalid' && ! isset($context['offline_mark_id']) && ! isset($context['employee_id']))->once();
    });

    it('answers as for a repeated delivery, with the transaction still usable, when the same ticket lands at the same moment', function (bool $sameAssertion): void {
        $mark = romMark();
        $verified = resolve(OfflineTicket::class)->verify($mark->ticket);
        $inserted = false;
        $reads = 0;
        DB::listen(function (Illuminate\Database\Events\QueryExecuted $query) use (&$inserted, &$reads, $mark, $verified, $sameAssertion): void {
            // The second read of the deliveries is the one under the lock: the competing row lands right after it.
            if ($inserted || $verified === null || ! str_contains($query->sql, 'from "attendance_offline_marks"') || ++$reads < 2) {
                return;
            }

            $inserted = true;
            AttendanceOfflineMarkFactory::new()->create([
                'tenant_id' => $this->tenant->id,
                'employee_id' => $this->ana['employee']->id,
                'work_center_id' => $this->plant->id,
                'ticket_nonce' => $verified['nonce'],
                'challenge_hash' => $sameAssertion ? hash('sha256', OfflineChallenge::canonical($this->tenant->id, $this->ana['employee']->id, $mark->workCenterId, $mark->type, null, $mark->declaredAt->getTimestamp(), $mark->latE5, $mark->lngE5, $mark->accuracyM, 1, 0, null, $mark->ticket)) : hash('sha256', 'another'),
                'assertion_hash' => $sameAssertion ? hash('sha256', $mark->assertion()) : hash('sha256', 'other'),
            ]);
        });

        $outcome = romDeliver($mark);

        expect($inserted)->toBeTrue()
            ->and($outcome->toArray())->toBe($sameAssertion
                ? ['seq' => 0, 'status' => 'duplicate', 'reason' => 'sequence_conflict', 'stored_status' => 'rejected']
                : ['seq' => 0, 'status' => 'conflict', 'reason' => 'ticket_reused'])
            ->and(AttendanceMark::query()->count())->toBe(0)
            ->and(AttendanceOfflineMark::query()->count())->toBe(1);
    })->with(['the same signature' => [true], 'another one' => [false]]);
});
