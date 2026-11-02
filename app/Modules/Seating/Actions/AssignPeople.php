<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\Today;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Puts people of the position's work center in it from today. A person holds one position at a time: someone who already
 * holds another is refused, and someone already in this one is left as they are.
 */
final readonly class AssignPeople
{
    public function __construct(private CurrentTenant $tenant, private Roster $roster, private RecordAudit $audit) {}

    /**
     * @param  list<string>  $employeeIds
     * @return int How many people were put in the position.
     *
     * @throws ValidationException when someone is not active in the center or already holds another position.
     */
    public function handle(StandingPosition $position, array $employeeIds): int
    {
        $active = $this->roster->activeIdsIn($position->work_center_id);

        try {
            return DB::transaction(function () use ($position, $employeeIds, $active): int {
                $current = PositionAssignment::query()->whereIn('employee_id', $employeeIds)->whereNull('ends_on')->pluck('position_id', 'employee_id');
                $added = 0;

                foreach ($employeeIds as $index => $employeeId) {
                    $holds = $current->get($employeeId);

                    if (! in_array($employeeId, $active, true)) {
                        throw ValidationException::withMessages(["employee_ids.{$index}" => 'Esta persona no está activa en el centro del puesto.']);
                    }

                    if ($holds === $position->id) {
                        continue;
                    }

                    if ($holds !== null) {
                        throw ValidationException::withMessages(["employee_ids.{$index}" => 'Esta persona ya tiene otro puesto. Quítala de ahí primero.']);
                    }

                    PositionAssignment::query()->create(['tenant_id' => $this->tenant->id(), 'employee_id' => $employeeId, 'position_id' => $position->id, 'starts_on' => Today::date()]);
                    $added++;
                }

                if ($added > 0) {
                    $record = ['position_id' => $position->id, 'count' => $added];
                    $this->audit->handle('seating.people_assigned', $position, $record);
                    Log::info('seating.people_assigned', $record);
                }

                return $added;
            });
        } catch (UniqueConstraintViolationException) {
            // Two requests at once for the same person: the database kept one.
            throw ValidationException::withMessages(['employee_ids' => 'Una de estas personas ya tiene otro puesto.']);
        }
    }
}
