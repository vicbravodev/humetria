<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Data\OfflineOutcome;
use App\Modules\Attendance\Data\OfflineSubmission;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\OfflinePosition;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Enums\SignerType;
use App\Modules\Attendance\Events\OfflineMarkAccepted;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\AttendanceOfflineMark;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Support\AgreementStatus;
use App\Modules\Attendance\Support\CompanyPolicy;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\Geofence;
use App\Modules\Attendance\Support\MarkLock;
use App\Modules\Attendance\Support\OfflineChallenge;
use App\Modules\Attendance\Support\OfflineTicket;
use App\Modules\Attendance\Support\OfflineTime;
use App\Modules\Attendance\Support\QrToken;
use App\Modules\Attendance\Support\VerifiedOfflineMark;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\Timezones;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\WorkerAccess\Contracts\DeviceKeySignatures;
use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Data\DeviceSigned;
use App\Modules\WorkerAccess\Data\SignedBy;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Judges one mark a phone made without a connection and delivered later (criterio 3.3.1), apart from RecordMark: the server
 * does not stamp its time, it bounds the one the person declared. In order: the ticket (no row for one that is not ours), the
 * same delivery again (answered with what was kept), the signature (no row either: there is nobody to attribute it to, and the
 * ticket stays unspent for the genuine mark), then the rules of the person, the center, the pass and the agreement (a row
 * with the reason), and, under the person's lock, the QR, the position (only to flag it), the one rule of time and the
 * sequence. A mark that does not fit is rejected alone, never the whole delivery.
 */
final readonly class RecordOfflineMark
{
    public function __construct(
        private CurrentTenant $tenant,
        private Roster $roster,
        private WorkCenters $workCenters,
        private Timezones $timezones,
        private AgreementStatus $agreements,
        private OfflineTicket $tickets,
        private WorkerSignatures $passkeys,
        private DeviceKeySignatures $deviceKeys,
        private RecordAudit $audit,
    ) {}

    public function handle(OfflineSubmission $submission, CarbonImmutable $receivedAt): OfflineOutcome
    {
        $ticket = $this->tickets->verify($submission->ticket);

        if ($ticket === null) {
            return $this->unsaved($submission, $receivedAt, OfflineOutcome::TICKET_INVALID);
        }

        $passId = $submission->signerType === SignerType::DeviceKey && $submission->pass !== null ? $this->deviceKeys->passId($submission->pass) : null;

        if ($submission->signerType === SignerType::DeviceKey && $passId === null) {
            return $this->unsaved($submission, $receivedAt, OfflineOutcome::SIGNATURE_INVALID);
        }

        $challenge = OfflineChallenge::hash(OfflineChallenge::canonical(
            $this->tenant->id(), $ticket['employee_id'], $submission->workCenterId, $submission->type, $submission->qr, $submission->declaredAt->getTimestamp(),
            $submission->latE5, $submission->lngE5, $submission->accuracyM, $submission->agreementVersion, $submission->seq, $passId, $submission->ticket,
        ));
        $stored = $this->stored($ticket['nonce']);

        if ($stored !== null) {
            return $this->repeat($stored, $submission, bin2hex($challenge));
        }

        $signed = $this->verify($submission, $challenge, $passId, $ticket['issued_at']);

        if ($signed === null || $signed->employeeId !== $ticket['employee_id'] || ($signed->revokedAt !== null && $submission->declaredAt->greaterThan($signed->revokedAt))) {
            return $this->unsaved($submission, $receivedAt, OfflineOutcome::SIGNATURE_INVALID);
        }

        $person = $this->roster->find($ticket['employee_id']);

        if ($person === null) {
            return $this->unsaved($submission, $receivedAt, OfflineRejection::PersonInactive->value);
        }

        $verified = new VerifiedOfflineMark($submission, $person, $signed, $ticket['issued_at'], $ticket['nonce'], $receivedAt, bin2hex($challenge));
        $result = DB::transaction(fn (): AttendanceOfflineMark|OfflineOutcome => $this->settle($verified, $this->refusal($verified)));

        return $result instanceof OfflineOutcome ? $result : $this->afterwards($verified, $result);
    }

    /** What the delivery already kept for this ticket, if anything. */
    private function stored(string $nonce): ?AttendanceOfflineMark
    {
        return AttendanceOfflineMark::query()->where('ticket_nonce', $nonce)->first();
    }

    /** The same delivery again is answered with what was kept; the same ticket with another one is a conflict, with no row. */
    private function repeat(AttendanceOfflineMark $stored, OfflineSubmission $submission, string $challengeHash): OfflineOutcome
    {
        if ($stored->challenge_hash !== $challengeHash || $stored->assertion_hash !== hash('sha256', $submission->assertion())) {
            return OfflineOutcome::conflict($submission->seq);
        }

        return OfflineOutcome::duplicate($submission->seq, $stored->outcome === 'accepted' ? 'received' : 'rejected', $stored->reason?->value, $stored->employee_id);
    }

    /** Whose key signed the challenge, or null for any answer that does not verify. */
    private function verify(OfflineSubmission $submission, string $challenge, ?string $passId, CarbonImmutable $ticketIssuedAt): SignedBy|DeviceSigned|null
    {
        if ($submission->signerType === SignerType::Passkey) {
            return $this->passkeys->verifyChallenge($challenge, $submission->credential ?? [], $ticketIssuedAt);
        }

        return $passId === null || $submission->pass === null || $submission->signature === null
            ? null
            : $this->deviceKeys->verify($challenge, $submission->signature, $submission->pass, $ticketIssuedAt);
    }

    /**
     * Why a mark with a verified signature cannot be taken, by the rules that need no neighbours; the first that fails wins.
     */
    private function refusal(VerifiedOfflineMark $v): ?OfflineRejection
    {
        $signed = $v->signed;

        return match (true) { // @pest-mutate-ignore: TrueToFalse
            $signed instanceof DeviceSigned && ($v->submission->declaredAt->greaterThan($signed->passExpiresAt) || $signed->passIssuedAt->greaterThan($v->receivedAt)) => OfflineRejection::PassExpired,
            $v->receivedAt->greaterThan($v->ticketIssuedAt->addDays(config()->integer('attendance.offline.ticket_horizon_days'))) => OfflineRejection::TicketExpired,
            ! $v->person->active => OfflineRejection::PersonInactive,
            $v->submission->workCenterId !== $v->person->workCenterId => OfflineRejection::WrongCenter,
            ! $this->agreements->acceptedAt($v->person->id, $v->ticketIssuedAt) => OfflineRejection::AgreementNotAccepted,
            default => null,
        };
    }

    /**
     * Under the person's lock: the delivery once more (two at once are serialized), the neighbours of the mark and the rule of
     * time, and then the row that says what became of it. A row that cannot be kept because another one took its ticket in the
     * meantime is answered as a repeated delivery, and the savepoint leaves the transaction usable.
     */
    private function settle(VerifiedOfflineMark $v, ?OfflineRejection $refusal): AttendanceOfflineMark|OfflineOutcome
    {
        MarkLock::of($v->person->id);

        $stored = $this->stored($v->nonce);

        if ($stored !== null) {
            return $this->repeat($stored, $v->submission, $v->challengeHash);
        }

        try {
            return DB::transaction(fn (): AttendanceOfflineMark|OfflineOutcome => $refusal === null ? $this->judge($v) : $this->keep($v, $refusal));
        } catch (UniqueConstraintViolationException) {
            $stored = $this->stored($v->nonce) ?? throw new LogicException('A delivery collided with a ticket that has no row.');

            return $this->repeat($stored, $v->submission, $v->challengeHash);
        }
    }

    /** The QR, the position, the neighbours, the time and the sequence of a mark that passed the rest. */
    private function judge(VerifiedOfflineMark $v): AttendanceOfflineMark|OfflineOutcome
    {
        $s = $v->submission;
        [$windowStart, $awayCenterId] = $this->window($v);
        $reading = $this->reading($v, $awayCenterId ?? $v->person->workCenterId);
        $reference = OfflineTime::reference($s->declaredAt, $v->ticketIssuedAt, $v->receivedAt)->utc()->format('Y-m-d H:i:s.uP');
        $previous = EffectiveMarks::of($v->person->id)->where('marked_at', '<=', $reference)->orderByDesc('marked_at')->first();
        $next = EffectiveMarks::of($v->person->id)->where('marked_at', '>', $reference)->orderBy('marked_at')->first();
        $decision = OfflineTime::decide($s->declaredAt, $v->ticketIssuedAt, $v->receivedAt, $windowStart, $previous?->marked_at, $next?->marked_at, $reading['position']);
        $follows = in_array($s->type, MarkType::allowedAfter($previous?->type), true)
            && ($next === null || in_array($next->type, MarkType::allowedAfter($s->type), true));

        if ($decision === null || ! $follows) {
            return $this->alreadyRecorded($v, [$previous, $next]) ? $this->recordedBefore($v) : $this->keep($v, OfflineRejection::SequenceConflict);
        }

        $mark = AttendanceMark::query()->create([
            'tenant_id' => $this->tenant->id(),
            'employee_id' => $v->person->id,
            'work_center_id' => $v->person->workCenterId,
            'type' => $s->type,
            'marked_at' => $decision->countedAt,
            'method' => $decision->basis->method(),
            'zone_id' => $reading['zoneId'],
            'latitude' => $s->position()?->latitude,
            'longitude' => $s->position()?->longitude,
            'accuracy_m' => $s->position()?->accuracyM,
            'distance_m' => $reading['distance'],
            'credential_id' => $v->credentialId(),
            'timezone' => $this->timezones->forCenter($awayCenterId ?? $v->person->workCenterId),
            'away_center_id' => $awayCenterId,
        ]);
        $row = AttendanceOfflineMark::query()->create([
            ...$this->attributes($v),
            'outcome' => 'accepted',
            'mark_id' => $mark->id,
            'away_center_id' => $awayCenterId,
            'time_basis' => $decision->basis,
            'counted_at' => $decision->countedAt,
            'window_start' => $windowStart,
            'review_flags' => $decision->flags,
        ]);
        // Inside the transaction and the lock, as a correction is: whoever reopens a week reads the mark in its place.
        event(new OfflineMarkAccepted($mark->id, $v->person->id, $row->id));

        return $row->setRelation('mark', $mark);
    }

    /** A row for a delivery that is rejected, with its reason. */
    private function keep(VerifiedOfflineMark $v, OfflineRejection $reason): AttendanceOfflineMark
    {
        return AttendanceOfflineMark::query()->create([...$this->attributes($v), 'outcome' => 'rejected', 'reason' => $reason, 'review_flags' => []]);
    }

    /**
     * What every row of a delivery has: the person's own center (never the one the phone named, which may not exist), who
     * signed, the ticket and the hashes that tell a repeated delivery from another.
     *
     * @return array<model-property<AttendanceOfflineMark>, mixed>
     */
    private function attributes(VerifiedOfflineMark $v): array
    {
        $s = $v->submission;

        return [
            'tenant_id' => $this->tenant->id(),
            'employee_id' => $v->person->id,
            'work_center_id' => $v->person->workCenterId,
            'mark_type' => $s->type,
            'declared_at' => $s->declaredAt,
            'ticket_nonce' => $v->nonce,
            'ticket_issued_at' => $v->ticketIssuedAt,
            'received_at' => $v->receivedAt,
            'device_clock_at' => $s->deviceClockAt,
            'signer_type' => $s->signerType,
            'signer_id' => $v->signerId(),
            'seq' => $s->seq,
            'justification' => $s->justification,
            'agreement_version' => $s->agreementVersion,
            'challenge_hash' => $v->challengeHash,
            'assertion_hash' => hash('sha256', $s->assertion()),
        ];
    }

    /**
     * The start of the window of a QR that verifies for the person's center, or else for another active center of the company
     * (where the person physically stood); none for a QR that verifies for no center, which counts as no QR.
     *
     * @return array{0: CarbonImmutable|null, 1: string|null}
     */
    private function window(VerifiedOfflineMark $v): array
    {
        $qr = $v->submission->qr;

        if ($qr === null) {
            return [null, null];
        }

        $own = QrToken::windowStart($qr, $this->tenant->id(), $v->person->workCenterId, $v->receivedAt);

        if ($own !== null) {
            return [$own, null];
        }

        foreach ($this->workCenters->all() as $center) {
            $start = $center->archived || $center->id === $v->person->workCenterId ? null : QrToken::windowStart($qr, $this->tenant->id(), $center->id, $v->receivedAt);

            if ($start !== null) {
                return [$start, $center->id];
            }
        }

        return [null, null];
    }

    /**
     * Where the position falls against the zones of the center the mark was made at, only to flag it: never to block. A center
     * with no zone has nothing to be inside or outside of: `zones_not_configured`, not `outside_zone`.
     *
     * @return array{position: OfflinePosition, zoneId: string|null, distance: float|null}
     */
    private function reading(VerifiedOfflineMark $v, string $centerId): array
    {
        $position = $v->submission->position();

        if ($position === null) {
            return ['position' => OfflinePosition::Unknown, 'zoneId' => null, 'distance' => null];
        }

        $zones = AttendanceZone::query()->where('work_center_id', $centerId)->get()->all();

        if ($zones === []) {
            return ['position' => OfflinePosition::NoZones, 'zoneId' => null, 'distance' => null];
        }

        $reading = Geofence::evaluate($zones, $position->latitude, $position->longitude, $position->accuracyM, CompanyPolicy::current()->max_accuracy_m);

        return ['position' => OfflinePosition::fromReading($reading), 'zoneId' => $reading->inside ? $reading->zoneId : null, 'distance' => $reading->distanceM];
    }

    /**
     * Whether the mark collides with the same mark made with a connection after the ticket was issued and close to the time
     * declared: the answer of that mark was lost and the phone queued a copy of it.
     *
     * @param  list<AttendanceMark|null>  $neighbours
     */
    private function alreadyRecorded(VerifiedOfflineMark $v, array $neighbours): bool
    {
        $window = config()->integer('attendance.offline.already_recorded_window_minutes') * 60;

        foreach ($neighbours as $mark) {
            $at = $mark?->marked_at;

            if ($mark !== null && $at !== null && $mark->type === $v->submission->type
                && ! in_array($mark->method, [MarkMethod::OfflineQr, MarkMethod::OfflineDeclared], true)
                && $at->greaterThan($v->ticketIssuedAt)
                && abs($at->getTimestamp() - $v->submission->declaredAt->getTimestamp()) <= $window) {
                return true;
            }
        }

        return false;
    }

    /** The phone drops the copy: no row, only a log. */
    private function recordedBefore(VerifiedOfflineMark $v): OfflineOutcome
    {
        Log::info('attendance.offline_mark_already_recorded', ['employee_id' => $v->person->id, 'type' => $v->submission->type->value]);

        return OfflineOutcome::duplicate($v->submission->seq, 'received', OfflineOutcome::ALREADY_RECORDED, $v->person->id);
    }

    /** What is written once the transaction committed: the audit entry of an accepted mark and the log of every row. */
    private function afterwards(VerifiedOfflineMark $v, AttendanceOfflineMark $row): OfflineOutcome
    {
        $flags = $row->review_flags->map(fn (ReviewFlag $flag): string => $flag->value)->values()->all();

        if ($row->outcome === 'accepted') {
            $this->audit->handle('attendance.offline_mark_accepted', $row->mark, [
                'employee_id' => $v->person->id,
                'time_basis' => $row->time_basis?->value,
                'signer_type' => $v->submission->signerType->value,
                'interval_minutes' => $v->intervalMinutes(),
                'review_flags' => $flags,
            ]);
        }

        Log::info($row->outcome === 'accepted' ? 'attendance.offline_mark_received' : 'attendance.offline_mark_rejected', [
            'offline_mark_id' => $row->id,
            'employee_id' => $v->person->id,
            ...($row->reason === null ? [] : ['reason' => $row->reason->value]),
            'basis' => $row->time_basis?->value,
            'interval_minutes' => $v->intervalMinutes(),
            'queue_age_minutes' => intdiv($v->receivedAt->getTimestamp() - $v->submission->declaredAt->getTimestamp(), 60),
            'signer_type' => $v->submission->signerType->value,
            'review_flags' => $flags,
        ]);

        return $row->reason === null
            ? OfflineOutcome::received($v->submission->seq, $v->person->id)
            : OfflineOutcome::rejected($v->submission->seq, $row->reason, $v->person->id);
    }

    /** A rejection that leaves no row (no ticket, no signature, nobody to attribute it to): only a log, with no person. */
    private function unsaved(OfflineSubmission $submission, CarbonImmutable $receivedAt, string $reason): OfflineOutcome
    {
        Log::info('attendance.offline_mark_rejected', [
            'reason' => $reason,
            'queue_age_minutes' => intdiv($receivedAt->getTimestamp() - $submission->declaredAt->getTimestamp(), 60),
            'signer_type' => $submission->signerType->value,
        ]);

        return OfflineOutcome::rejected($submission->seq, $reason);
    }
}
