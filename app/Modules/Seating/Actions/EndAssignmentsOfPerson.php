<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Models\PositionAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ends the current position of someone who left the company or changed work center: Ley Silla follows the center. It ends
 * on the day they left (a plain date, `Y-m-d`), never before it started.
 */
final readonly class EndAssignmentsOfPerson
{
    public function handle(string $employeeId, string $on): int
    {
        $open = PositionAssignment::query()->where('employee_id', $employeeId)->whereNull('ends_on');
        // One that started after the day they left ends on its own first day; the rest, on that day.
        $ended = (clone $open)->where('starts_on', '>', $on)->update(['ends_on' => DB::raw('starts_on')]) + $open->update(['ends_on' => $on]);

        if ($ended > 0) {
            Log::info('seating.assignments_ended', ['employee_id' => $employeeId, 'count' => $ended]);
        }

        return $ended;
    }
}
