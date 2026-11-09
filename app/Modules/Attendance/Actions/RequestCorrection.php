<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Exceptions\CorrectionRejected;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * A person asks RH to correct their record (criterio 5.4, the design's Correcciones) from their verified device: a mark of
 * theirs that is wrong (`$markId`, one of their effective marks, of the same type) or one they forgot (no mark). The time
 * they propose is not in the future nor older than 35 days, and is kept in UTC. The reason is plain text of 10 to 500
 * characters. A mark has one pending request at a time,
 * and a person ten. The reason stays in the row, never in the log.
 */
final readonly class RequestCorrection
{
    public const int MIN_REASON = 10;

    public const int MAX_REASON = 500;

    /** Plain text: no control characters (line breaks and tabs are fine). */
    public const string CONTROL_CHARACTERS = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/';

    public const int MAX_PENDING = 10;

    public const int MAX_AGE_DAYS = 35;

    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    /**
     * @throws CorrectionRejected
     */
    public function handle(string $employeeId, ?string $markId, MarkType $type, CarbonImmutable $proposedAt, string $reason, ?string $deviceId = null): CorrectionRequest
    {
        $reason = trim($reason);
        $person = $this->roster->find($employeeId);

        if (mb_strlen($reason) < self::MIN_REASON || $person === null || ! $person->active) {
            throw new InvalidArgumentException('Only an active person of the company asks, with a reason.');
        }

        if (mb_strlen($reason) > self::MAX_REASON || preg_match(self::CONTROL_CHARACTERS, $reason) === 1) {
            throw new CorrectionRejected('reason_invalid');
        }

        if ($proposedAt->isFuture()) {
            throw new CorrectionRejected('future_time');
        }

        if ($proposedAt->lessThan(CarbonImmutable::now()->subDays(self::MAX_AGE_DAYS))) {
            throw new CorrectionRejected('too_old');
        }

        $request = DB::transaction(function () use ($person, $markId, $type, $proposedAt, $reason, $deviceId): CorrectionRequest {
            // Two requests at once must not both pass the counts.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["attendance_corrections:{$person->id}"]);
            $this->ensureCanAsk($person->id, $markId, $type);

            return CorrectionRequest::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $person->id,
                'mark_id' => $markId,
                'type' => $type,
                'proposed_at' => $proposedAt->utc(),
                'reason' => $reason,
                'device_id' => $deviceId,
            ]);
        });

        Log::info('attendance.correction_requested', ['request_id' => $request->id, 'employee_id' => $person->id, 'mark_id' => $markId]);

        return $request;
    }

    /**
     * @throws CorrectionRejected
     */
    private function ensureCanAsk(string $employeeId, ?string $markId, MarkType $type): void
    {
        if ($markId !== null) {
            $mark = EffectiveMarks::of($employeeId)->whereKey($markId)->first(['id', 'type']) ?? throw new CorrectionRejected('mark_not_found');

            if ($mark->type !== $type) {
                throw new CorrectionRejected('type_mismatch');
            }

            if (CorrectionRequest::query()->where('mark_id', $markId)->where('status', CorrectionStatus::Pending)->exists()) {
                throw new CorrectionRejected('already_requested');
            }
        }

        if (CorrectionRequest::query()->where('employee_id', $employeeId)->where('status', CorrectionStatus::Pending)->count() >= self::MAX_PENDING) {
            throw new CorrectionRejected('too_many_pending');
        }
    }
}
