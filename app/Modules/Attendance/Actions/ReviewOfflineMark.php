<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Events\OfflineMarkReviewed;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\MarkReview;
use App\Modules\Attendance\Support\CorrectedSequence;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Attendance\Support\MarkLock;
use App\Modules\Tenancy\Contracts\Members;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * RH (a member of the company) reviews a mark made without a connection (criterio 5.4): verifies it, or says it is
 * incorrect. Either way it is an insertion in `attendance_mark_reviews`; the decision in force is the last. Verifying
 * changes nothing in the mark: its time is the one the rule of the delivery counted. An incorrect one only exists with its
 * correction, in the same transaction (CorrectMark: to the local time RH writes, or voided without one), so a day left out
 * of order (`sequence_invalid`) leaves no review behind. To the audit trail, the log and the event with ids only: never the
 * note nor a time.
 */
final readonly class ReviewOfflineMark
{
    public const int MIN_NOTE = 5; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    /** The marks of one page of the queue. */
    public const int MAX_MARKS = 25; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    private const array OFFLINE = [MarkMethod::OfflineQr, MarkMethod::OfflineDeclared]; // @pest-mutate-ignore: RemoveArrayItem

    public function __construct(private Members $members, private CorrectMark $correct, private RecordAudit $audit) {}

    /**
     * @param  string|null  $local  the correct local time, Y-m-d H:i in the zone of the mark; null voids an incorrect mark
     *
     * @throws CorrectionRejected
     */
    public function handle(string $markId, ReviewDecision $decision, int $userId, ?string $note, ?string $local = null): MarkReview
    {
        if ($this->members->find($userId) === null) {
            throw new InvalidArgumentException('Only a member of the company reviews a mark.');
        }

        $note = trim($note ?? '');

        if ($decision === ReviewDecision::Incorrect && mb_strlen($note) < self::MIN_NOTE) {
            throw new InvalidArgumentException('An incorrect mark needs a note.');
        }

        [$review, $mark] = DB::transaction(function () use ($markId, $decision, $userId, $note, $local): array {
            $employeeId = AttendanceMark::query()->findOrFail($markId, ['id', 'employee_id'])->employee_id;
            // The person's marks hold still while the mark is checked and, if it is incorrect, corrected.
            MarkLock::of($employeeId);
            $mark = EffectiveMarks::of($employeeId)->whereKey($markId)->first() ?? throw new CorrectionRejected('mark_changed');

            if (! in_array($mark->method, self::OFFLINE, true)) {
                throw new CorrectionRejected('not_offline');
            }

            if ($decision === ReviewDecision::Incorrect) {
                $this->correct($mark, $local, $note, $userId);
            }

            $review = MarkReview::query()->create([
                'tenant_id' => $mark->tenant_id,
                'mark_id' => $mark->id,
                'decision' => $decision,
                'reviewer_id' => $userId,
                'note' => $note === '' ? null : $note,
            ]);
            $this->audit->handle('attendance.offline_mark_reviewed', $review, $this->ids($mark, $decision));

            return [$review, $mark];
        });

        Log::info('attendance.offline_mark_reviewed', $this->ids($mark, $decision));
        event(new OfflineMarkReviewed($mark->id, $mark->employee_id, $decision->value));

        return $review;
    }

    /**
     * Verifies the marks made without a connection, with no review yet, among these ids (at most one page). Each one in its
     * own transaction, with its own review, audit entry and event. What is not found (another company's, one already
     * reviewed or corrected, one made online) is skipped, and so is one that changed meanwhile.
     *
     * @param  list<string>  $markIds
     * @return array{verified: int, skipped: int}
     */
    public function verifyMany(array $markIds, int $userId): array
    {
        $ids = array_values(array_unique($markIds));

        if (count($ids) > self::MAX_MARKS) {
            throw new InvalidArgumentException('A page of the queue has at most '.self::MAX_MARKS.' marks.');
        }

        $pending = $ids === [] ? [] : EffectiveMarks::query()
            ->whereIn('id', array_values(array_filter($ids, Str::isUuid(...))))
            ->whereIn('method', array_map(fn (MarkMethod $method): string => $method->value, self::OFFLINE))
            ->whereNotExists(fn (QueryBuilder $query) => $query->from('attendance_mark_reviews')->whereColumn('attendance_mark_reviews.mark_id', 'attendance_marks.id'))
            ->get(['id'])->all();
        $skipped = count($ids) - count($pending);
        $verified = 0;

        foreach ($pending as $mark) {
            try {
                $this->handle($mark->id, ReviewDecision::Verified, $userId, null);
                $verified++;
            } catch (CorrectionRejected) {
                $skipped++;
            }
        }

        return ['verified' => $verified, 'skipped' => $skipped];
    }

    /**
     * The correction of a mark RH says is incorrect, to the local time written or voided.
     *
     * @throws CorrectionRejected
     */
    private function correct(AttendanceMark $mark, ?string $local, string $note, int $userId): void
    {
        $at = $local === null ? null : (CarbonImmutable::createFromFormat('!Y-m-d H:i', $local, $mark->timezone) ?? throw new InvalidArgumentException('A local time is Y-m-d H:i.'));

        if ($at?->isFuture() === true) {
            throw new CorrectionRejected('future_time');
        }

        if ($at !== null && ! CorrectedSequence::follows($mark->employee_id, $mark, $mark->type, $at)) {
            throw new CorrectionRejected('sequence_invalid');
        }

        $this->correct->handle($mark->id, $at, $note, $userId);
    }

    /**
     * @return array{mark_id: string, employee_id: string, decision: string, corrected: bool}
     */
    private function ids(AttendanceMark $mark, ReviewDecision $decision): array
    {
        return ['mark_id' => $mark->id, 'employee_id' => $mark->employee_id, 'decision' => $decision->value, 'corrected' => $decision === ReviewDecision::Incorrect];
    }
}
