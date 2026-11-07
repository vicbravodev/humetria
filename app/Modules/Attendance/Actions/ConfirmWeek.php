<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Actions\RecordAudit;
use App\Modules\Attendance\Enums\WeekStatus;
use App\Modules\Attendance\Events\WeekConfirmed;
use App\Modules\Attendance\Exceptions\WeekRejected;
use App\Modules\Attendance\Models\WeeklyConfirmation;
use App\Modules\Attendance\Support\WeekPeriod;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A person confirms their week (criterio 5.2) with their verified device: what they confirm is «acordado» (1.3), so it
 * goes to the audit trail with the device. Only a week that is over (its Sunday ended, local). Confirming again keeps the
 * first moment. A disputed week is not confirmed over the dispute: RH resolves it.
 */
final readonly class ConfirmWeek
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    /**
     * @throws WeekRejected
     */
    public function handle(string $employeeId, CarbonImmutable $weekStart, string $deviceId): WeeklyConfirmation
    {
        $week = WeekPeriod::ofDate($weekStart);

        if (! $week->isOver()) {
            throw new WeekRejected('week_not_over');
        }

        return DB::transaction(function () use ($employeeId, $week, $deviceId): WeeklyConfirmation {
            $confirmation = WeeklyConfirmation::lockedFor($this->tenant->id(), $employeeId, $week);

            if ($confirmation->status === WeekStatus::Confirmed) {
                return $confirmation;
            }

            if ($confirmation->status === WeekStatus::Disputed) {
                throw new WeekRejected('week_disputed');
            }

            $confirmation->update(['status' => WeekStatus::Confirmed, 'confirmed_at' => CarbonImmutable::now()->utc(), 'device_id' => $deviceId]);
            $ids = ['employee_id' => $employeeId, 'week_start' => $week->start()->toDateString(), 'device_id' => $deviceId];
            $this->audit->handle('attendance.week_confirmed', $confirmation, $ids);
            Log::info('attendance.week_confirmed', ['confirmation_id' => $confirmation->id, ...$ids]);
            event(new WeekConfirmed($confirmation->id, $employeeId, $ids['week_start']));

            return $confirmation;
        });
    }
}
