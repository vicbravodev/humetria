<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Models\LeaveStatement;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A person confirms, from their portal, that they received the annual statement of a period (criterio C-10): only the
 * moment stays as evidence, no address nor device. A statement that is not theirs does not exist; a second confirmation is
 * refused and changes nothing.
 */
final readonly class AcknowledgeStatement
{
    /**
     * @throws ModelNotFoundException<LeaveStatement> when the statement is not of this person
     * @throws ValidationException when it was already confirmed
     */
    public function handle(string $employeeId, string $statementId): void
    {
        DB::transaction(function () use ($employeeId, $statementId): void {
            $statement = LeaveStatement::query()->where('employee_id', $employeeId)->lockForUpdate()->findOrFail($statementId);

            if ($statement->acknowledged_at !== null) {
                throw ValidationException::withMessages(['statement' => 'Ya confirmó esta constancia.']);
            }

            $statement->update(['acknowledged_at' => now()]);
        });

        Log::info('leave.statement_acknowledged', ['statement_id' => $statementId, 'employee_id' => $employeeId]);
    }
}
