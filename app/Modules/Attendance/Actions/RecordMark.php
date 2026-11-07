<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Data\MarkContext;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Exceptions\InvalidMarkSequence;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Support\EffectiveMarks;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Records a mark of an active person at the server's time (criterio 3.3), only if it follows their last one: a start only
 * with no day open, a break or an end only with the day open, a break end only during a break. A start after an end opens
 * a new segment.
 */
final readonly class RecordMark
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster) {}

    public function handle(string $employeeId, MarkType $type, MarkContext $context): AttendanceMark
    {
        $person = $this->roster->find($employeeId);
        if ($person === null || ! $person->active) {
            throw new InvalidArgumentException('Only an active person of the company marks.');
        }

        $mark = DB::transaction(function () use ($person, $type, $context): AttendanceMark {
            // Two taps at once must not both pass the check.
            DB::select('select pg_advisory_xact_lock(hashtext(?))', ["attendance_marks:{$person->id}"]);
            $this->ensureFollows($person->id, $type);

            return AttendanceMark::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $person->id,
                'work_center_id' => $person->workCenterId,
                'type' => $type,
                'marked_at' => now(),
                'method' => $context->method,
                'zone_id' => $context->zoneId,
                'latitude' => $context->latitude,
                'longitude' => $context->longitude,
                'accuracy_m' => $context->accuracy,
                'distance_m' => $context->distance,
                'device_id' => $context->deviceId,
            ]);
        });

        Log::info('attendance.mark_recorded', ['mark_id' => $mark->id, 'employee_id' => $person->id, 'type' => $type->value, 'method' => $context->method->value]);

        return $mark;
    }

    private function ensureFollows(string $employeeId, MarkType $type): void
    {
        $last = EffectiveMarks::of($employeeId)->latest('marked_at')->first(['type'])?->type;
        if (in_array($type, MarkType::allowedAfter($last), true)) {
            return;
        }

        // Only why it does not follow is left to tell.
        $reason = match ($type) {
            MarkType::Start => 'day_already_open',
            MarkType::BreakStart => $last === MarkType::BreakStart ? 'already_on_break' : 'day_not_open',
            MarkType::BreakEnd => 'not_on_break',
            MarkType::End => 'day_not_open',
        };

        throw new InvalidMarkSequence($reason);
    }
}
