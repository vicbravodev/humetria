<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Support\Today;
use Illuminate\Support\Facades\Log;

/**
 * Ends the current position of someone who left the company or changed work center: Ley Silla follows the center.
 */
final readonly class EndAssignmentsOfPerson
{
    public function handle(string $employeeId): int
    {
        $ended = PositionAssignment::query()->where('employee_id', $employeeId)->whereNull('ends_on')->update(['ends_on' => Today::date()]);

        if ($ended > 0) {
            Log::info('seating.assignments_ended', ['employee_id' => $employeeId, 'count' => $ended]);
        }

        return $ended;
    }
}
