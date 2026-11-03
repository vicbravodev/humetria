<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Support\Today;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Takes a person out of their position: the assignment ends today and stays as history (criterio C-17).
 */
final readonly class UnassignPerson
{
    public function __construct(private RecordAudit $audit) {}

    public function handle(PositionAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            $assignment->update(['ends_on' => Today::date()]);

            $record = ['position_id' => $assignment->position_id, 'employee_id' => $assignment->employee_id];
            $this->audit->handle('seating.person_unassigned', $assignment, $record);
            Log::info('seating.person_unassigned', $record);
        });
    }
}
