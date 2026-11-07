<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Models\AttendanceMark;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * RH's correction of a mark (criterio 5.4): a new mark of the same person and type that points to the original, with who
 * corrected and why. The original is never touched. Without a time, the correction voids the original. A mark is corrected
 * once: to change it again, the correction is the one corrected.
 */
final readonly class CorrectMark
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(string $markId, ?CarbonImmutable $newMarkedAt, string $reason, string $userId): AttendanceMark
    {
        if ($newMarkedAt?->isFuture() === true) {
            throw new InvalidArgumentException('A correction cannot set a mark in the future.');
        }

        $correction = DB::transaction(function () use ($markId, $newMarkedAt, $reason, $userId): AttendanceMark {
            $original = AttendanceMark::query()->findOrFail($markId);
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["attendance_marks:{$original->employee_id}"]);

            if (AttendanceMark::query()->where('corrects_mark_id', $original->id)->exists()) {
                throw new InvalidArgumentException('The mark was already corrected; correct its correction.');
            }

            $correction = AttendanceMark::query()->create([
                'tenant_id' => $original->tenant_id,
                'employee_id' => $original->employee_id,
                'work_center_id' => $original->work_center_id,
                'type' => $original->type,
                'marked_at' => $newMarkedAt,
                'method' => MarkMethod::Correction,
                'corrects_mark_id' => $original->id,
                'corrected_by' => $userId,
                'reason' => $reason,
            ]);

            $this->audit->handle('attendance.mark_corrected', $correction, ['corrects_mark_id' => $original->id, 'employee_id' => $original->employee_id]);

            return $correction;
        });

        Log::info('attendance.mark_corrected', ['mark_id' => $correction->id, 'corrects_mark_id' => $correction->corrects_mark_id, 'employee_id' => $correction->employee_id]);

        return $correction;
    }
}
