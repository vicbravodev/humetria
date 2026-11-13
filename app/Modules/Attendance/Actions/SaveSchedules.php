<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Actions;

use App\Modules\Attendance\Enums\ShiftType;
use App\Modules\Attendance\Enums\WorkMode;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The same agreed weekly schedule for several people of the company at once (SaveSchedule for each): all or none. Someone
 * who is not of the company, or unknown, fails the whole batch the same way, so a stranger's id says nothing.
 */
final readonly class SaveSchedules
{
    public function __construct(private Roster $roster, private SaveSchedule $save) {}

    /**
     * @param  list<string>  $employeeIds
     * @param  array<int, int>  $minutesByWeekday  minutes agreed by ISO weekday, 1 Monday to 7 Sunday
     * @param  array<int, int|null>|null  $startsByWeekday  the minute of the day each ISO weekday starts; null for none
     * @return list<RosterEntry> the people saved, in the order given
     *
     * @throws EmployeeNotAvailable when any of them is not of the company
     */
    public function handle(array $employeeIds, ShiftType $shiftType, WorkMode $workMode, array $minutesByWeekday, ?array $startsByWeekday = null): array
    {
        $found = $this->roster->findMany($employeeIds);
        $people = array_map(fn (string $id): RosterEntry => $found[$id] ?? throw new EmployeeNotAvailable, $employeeIds);

        // ponytail: one save per person, at most 100 of them; a single upsert if batches ever grow.
        DB::transaction(function () use ($people, $shiftType, $workMode, $minutesByWeekday, $startsByWeekday): void {
            foreach ($people as $person) {
                $this->save->handle($person->id, $shiftType, $workMode, $minutesByWeekday, $startsByWeekday);
            }
        });

        Log::info('attendance.schedules_saved', ['people' => count($people)]);

        return $people;
    }
}
