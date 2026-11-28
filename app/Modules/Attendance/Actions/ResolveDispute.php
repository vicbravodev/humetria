<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Tenancy\Contracts\Members;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * RH (owner or hr, a member of the company) resolves a person's dispute of their week (criterio 5.2) with a note the person
 * reads: the week goes back to pending, so the person confirms it or disputes it again. What was disputed stays beside the
 * note. Once: a week already resolved is not disputed anymore. To the audit trail with ids only: never the reason or the note.
 */
final readonly class ResolveDispute
{
    public const int MIN_NOTE = 10; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function __construct(private Members $members, private RecordAudit $audit) {}

    /**
     * @throws WeekRejected
     */
    public function handle(string $confirmationId, int $userId, string $note): WeeklyConfirmation
    {
        if ($this->members->find($userId) === null) {
            throw new InvalidArgumentException('Only a member of the company resolves a dispute.');
        }

        $note = trim($note);

        if (mb_strlen($note) < self::MIN_NOTE) {
            throw new InvalidArgumentException('A resolution needs a note.');
        }

        $week = DB::transaction(function () use ($confirmationId, $userId, $note): WeeklyConfirmation {
            $week = WeeklyConfirmation::query()->lockForUpdate()->findOrFail($confirmationId);

            if ($week->status !== WeekStatus::Disputed) {
                throw new WeekRejected('not_disputed');
            }

            $week->update([
                'status' => WeekStatus::Pending, 'credential_id' => null, 'digest' => null,
                'resolution_note' => $note, 'resolved_by' => $userId, 'resolved_at' => CarbonImmutable::now()->utc(),
            ]);
            $this->audit->handle('attendance.week_dispute_resolved', $week, $this->ids($week));

            return $week;
        });

        Log::info('attendance.week_dispute_resolved', ['confirmation_id' => $week->id, ...$this->ids($week)]);

        return $week;
    }

    /**
     * @return array{employee_id: string, week_start: string}
     */
    private function ids(WeeklyConfirmation $week): array
    {
        return ['employee_id' => $week->employee_id, 'week_start' => $week->week_start->toDateString()];
    }
}
