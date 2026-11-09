<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Events\WeekDisputed;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\MarkLock;
use App\Modules\Attendance\Support\WeekDigest;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * A person disputes their week (criterio 5.2) with their verified device and a reason: it stays in view of RH until
 * resolved. Only a week that is over. A person may dispute a week they had confirmed (they may notice later), not one
 * already disputed, nor a version of the week RH changed since the person read it (its digest, WeekDigest). A week with no
 * marks may be disputed: the person's marks may be missing. The reason stays in the row, never in the log or the audit
 * trail.
 */
final readonly class DisputeWeek
{
    public const int MIN_REASON = 10;

    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @throws WeekRejected
     */
    public function handle(string $employeeId, CarbonImmutable $weekStart, string $reason, string $deviceId, string $digest): WeeklyConfirmation
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON) {
            throw new InvalidArgumentException('A dispute needs a reason.');
        }

        $week = WeekPeriod::ofDate($weekStart);

        if (! $week->isOver()) {
            throw new WeekRejected('week_not_over');
        }

        return DB::transaction(function () use ($employeeId, $week, $reason, $deviceId, $digest): WeeklyConfirmation {
            // Before the row (a correction takes this lock, then the row): a correction in flight commits first, and the
            // marks read below are the ones after it.
            MarkLock::of($employeeId);
            $confirmation = WeeklyConfirmation::lockedFor($this->tenant->id(), $employeeId, $week);

            if ($confirmation->status === WeekStatus::Disputed) {
                throw new WeekRejected('already_disputed');
            }

            if (! hash_equals(WeekDigest::of($employeeId, $week), $digest)) {
                throw new WeekRejected('week_changed');
            }

            $confirmation->update(['status' => WeekStatus::Disputed, 'confirmed_at' => null, 'disputed_at' => CarbonImmutable::now()->utc(), 'reason' => $reason, 'device_id' => $deviceId, 'digest' => $digest]);
            $ids = ['employee_id' => $employeeId, 'week_start' => $week->start()->toDateString(), 'device_id' => $deviceId];
            $this->audit->handle('attendance.week_disputed', $confirmation, [...$ids, 'digest' => $digest]);
            Log::info('attendance.week_disputed', ['confirmation_id' => $confirmation->id, ...$ids]);
            event(new WeekDisputed($confirmation->id, $employeeId, $ids['week_start']));

            return $confirmation;
        });
    }
}
