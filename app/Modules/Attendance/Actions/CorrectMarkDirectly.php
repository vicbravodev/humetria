<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Support\CorrectedSequence;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\MarkLock;
use App\Modules\Tenancy\Contracts\Members;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * RH corrects a mark with no request first (criterio 5.4; H09: a person who left can no longer ask): to a local time, in
 * the zone the mark was taken, or voided. With a note, which the report and the person read beside the correction. As an
 * approved request does (CorrectMark): the original stays, the weeks it touches reopen and the audit trail keeps who. Only
 * an effective mark (`mark_changed` once corrected), never one to come (`future_time`) nor a time that leaves the day out
 * of order (`sequence_invalid`).
 */
final readonly class CorrectMarkDirectly
{
    public const int MIN_NOTE = 10;

    public function __construct(private Members $members, private CorrectMark $correct) {}

    /**
     * @param  string|null  $local  the new local time, Y-m-d H:i; null voids the mark
     *
     * @throws CorrectionRejected
     */
    public function handle(string $markId, ?string $local, string $note, int $userId): AttendanceMark
    {
        if ($this->members->find($userId) === null) {
            throw new InvalidArgumentException('Only a member of the company corrects a mark.');
        }

        $note = trim($note);

        if (mb_strlen($note) < self::MIN_NOTE) {
            throw new InvalidArgumentException('A correction needs a note.');
        }

        return DB::transaction(function () use ($markId, $local, $note, $userId) {
            $employeeId = AttendanceMark::query()->findOrFail($markId, ['id', 'employee_id'])->employee_id;
            // The person's marks hold still while the day is checked and changed.
            MarkLock::of($employeeId);
            $original = EffectiveMarks::of($employeeId)->whereKey($markId)->first() ?? throw new CorrectionRejected('mark_changed');
            $at = $local === null ? null : (CarbonImmutable::createFromFormat('!Y-m-d H:i', $local, $original->timezone) ?? throw new InvalidArgumentException('A local time is Y-m-d H:i.'));

            if ($at?->isFuture() === true) {
                throw new CorrectionRejected('future_time');
            }

            if ($at !== null && ! CorrectedSequence::follows($employeeId, $original, $original->type, $at)) {
                throw new CorrectionRejected('sequence_invalid');
            }

            return $this->correct->handle($original->id, $at, $note, $userId);
        });
    }
}
