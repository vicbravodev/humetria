<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Events\MarkCorrected;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Support\CorrectedSequence;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\MarkLock;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Members;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * RH (owner or hr, a member of the company) resolves a person's request to correct their record, once. Approving corrects
 * the mark to the time asked (CorrectMark) or adds the mark the person forgot as a new `correction` mark, with the
 * person's reason and who approved; either way the confirmed weeks it touches reopen (MarkCorrected). An approval that
 * would leave the day out of order (CorrectedSequence) is refused and the request stays pending: RH rejects it or asks the
 * person for another time. Rejecting needs a note. To the audit trail with ids only: never the reason or the note.
 */
final readonly class ResolveCorrection
{
    public const int MIN_NOTE = 5;

    public function __construct(private Members $members, private Roster $roster, private CorrectMark $correct, private RecordAudit $audit) {}

    /**
     * @throws CorrectionRejected
     */
    public function handle(string $requestId, bool $approve, int $userId, ?string $note): CorrectionRequest
    {
        if ($this->members->find($userId) === null) {
            throw new InvalidArgumentException('Only a member of the company resolves a request.');
        }

        $note = trim($note ?? '');

        if (! $approve && mb_strlen($note) < self::MIN_NOTE) {
            throw new InvalidArgumentException('A rejection needs a note.');
        }

        $request = DB::transaction(function () use ($requestId, $approve, $userId, $note): CorrectionRequest {
            $request = CorrectionRequest::query()->lockForUpdate()->findOrFail($requestId);

            if ($request->status !== CorrectionStatus::Pending) {
                throw new CorrectionRejected('already_resolved');
            }

            $request->update([
                'status' => $approve ? CorrectionStatus::Approved : CorrectionStatus::Rejected,
                'resolved_by' => $userId,
                'resolved_at' => CarbonImmutable::now()->utc(),
                'note' => $note === '' ? null : $note,
                'resulting_mark_id' => $approve ? $this->apply($request, $userId) : null,
            ]);
            $this->audit->handle('attendance.correction_resolved', $request, $this->ids($request, $approve));

            return $request;
        });

        Log::info('attendance.correction_resolved', ['request_id' => $request->id, ...$this->ids($request, $approve)]);

        return $request;
    }

    /**
     * The mark that carries the approved request: the correction of its mark, or the mark the person forgot.
     *
     * @throws CorrectionRejected
     */
    private function apply(CorrectionRequest $request, int $userId): string
    {
        // The person's marks hold still while the day is checked and changed.
        MarkLock::of($request->employee_id);
        $original = $request->mark_id === null ? null
            : EffectiveMarks::of($request->employee_id)->whereKey($request->mark_id)->first() ?? throw new CorrectionRejected('mark_changed');

        if (! CorrectedSequence::follows($request->employee_id, $original, $request->type, $request->proposed_at)) {
            throw new CorrectionRejected('sequence_invalid');
        }

        if ($original !== null) {
            return $this->correct->handle($original->id, $request->proposed_at, $request->reason, $userId)->id;
        }

        $person = $this->roster->find($request->employee_id) ?? throw new InvalidArgumentException('The person is not of the company.');
        $mark = AttendanceMark::query()->create([
            'tenant_id' => $request->tenant_id,
            'employee_id' => $person->id,
            'work_center_id' => $person->workCenterId,
            'type' => $request->type,
            'marked_at' => $request->proposed_at->utc(),
            'method' => MarkMethod::Correction,
            'corrected_by' => $userId,
            'reason' => $request->reason,
        ]);
        Log::info('attendance.mark_added', ['mark_id' => $mark->id, 'employee_id' => $person->id, 'request_id' => $request->id]);
        // In the same transaction: a confirmed week it touches reopens with it, or neither happens.
        event(new MarkCorrected($mark->id, null, $person->id));

        return $mark->id;
    }

    /**
     * @return array{approved: bool, mark_id: string|null, employee_id: string}
     */
    private function ids(CorrectionRequest $request, bool $approve): array
    {
        return ['approved' => $approve, 'mark_id' => $request->resulting_mark_id, 'employee_id' => $request->employee_id];
    }
}
