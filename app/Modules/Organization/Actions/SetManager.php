<?php

declare(strict_types=1);

namespace App\Modules\Organization\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class SetManager
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * Puts the person under that manager, or under nobody. It does not judge the manager: whoever calls already asked
     * ManagerRules, and the database rejects a cycle that a simultaneous change would close.
     *
     * @return bool whether the manager changed; when it did not, nothing is written.
     */
    public function handle(Employee $employee, ?string $managerId): bool
    {
        $from = $employee->manager_id;

        if ($from === $managerId) {
            return false;
        }

        DB::transaction(function () use ($employee, $from, $managerId): void {
            $employee->update(['manager_id' => $managerId]);

            $change = ['from_manager_id' => $from, 'to_manager_id' => $managerId];
            Log::info('employee.manager_changed', ['employee_id' => $employee->id, ...$change]);
            $this->audit->handle('employee.manager_changed', $employee, $change);
        });

        return true;
    }
}
