<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Actions\RecordAudit;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Leave\Models\LeavePolicyAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Takes a policy out of use. Never while it is assigned to someone: first they get another one. The periods granted with
 * it keep its terms (criterio C-15).
 */
final readonly class ArchivePolicy
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(string $policyId): void
    {
        $archived = DB::transaction(function () use ($policyId): bool {
            // Held until the end: archiving it and assigning it never both pass their checks.
            $policy = LeavePolicy::query()->lockForUpdate()->findOrFail($policyId);

            if ($policy->archived_at !== null) {
                return false;
            }

            if (LeavePolicyAssignment::query()->where('leave_policy_id', $policy->id)->exists()) {
                throw ValidationException::withMessages(['policy_id' => 'Está asignada: cambia primero a quién le toca.']);
            }

            $policy->update(['archived_at' => now()]);
            $this->audit->handle('leave.policy_archived', $policy);

            return true;
        });

        if ($archived) {
            Log::info('leave.policy_archived', ['policy_id' => $policyId]);
        }
    }
}
