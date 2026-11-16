<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Actions\RevokeCredential;
use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Data\CredentialCounts;
use App\Modules\WorkerAccess\Data\CredentialSummary;
use App\Modules\WorkerAccess\Models\WorkerCredential;

final readonly class CompanyWorkerCredentials implements WorkerCredentials
{
    public function __construct(private RevokeCredential $revoke) {}

    public function countsByEmployee(array $employeeIds): array
    {
        $active = [];
        $revoked = [];
        foreach (WorkerCredential::query()->among('employee_id', $employeeIds)->get(['employee_id', 'revoked_at']) as $key) {
            $revoked[$key->employee_id] = ($revoked[$key->employee_id] ?? 0) + ($key->revoked_at === null ? 0 : 1);
            $active[$key->employee_id] = ($active[$key->employee_id] ?? 0) + ($key->revoked_at === null ? 1 : 0);
        }

        $counts = [];
        foreach ($active as $employeeId => $count) {
            $counts[$employeeId] = new CredentialCounts($count, $revoked[$employeeId]);
        }

        return $counts;
    }

    public function ofEmployees(array $employeeIds): array
    {
        $keys = WorkerCredential::query()->among('employee_id', $employeeIds)
            ->orderByRaw('revoked_at is not null')->orderByDesc('registered_at')->orderBy('id')
            ->get(['id', 'employee_id', 'label', 'backup_eligible', 'backup_state', 'registered_at', 'last_used_at', 'revoked_at']);

        $listed = [];
        foreach ($keys as $key) {
            $listed[$key->employee_id][] = new CredentialSummary($key->id, $key->label, $key->backup_eligible || $key->backup_state, $key->registered_at, $key->last_used_at, $key->revoked_at);
        }

        return $listed;
    }

    public function revoke(string $credentialId): void
    {
        $this->revoke->handle($credentialId);
    }
}
