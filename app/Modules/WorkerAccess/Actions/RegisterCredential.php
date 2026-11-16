<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Actions\RecordAudit;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\WorkerAccess\Data\CredentialData;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Saves a key a person's device made and the ceremony verified, and records it in the audit trail and the log with ids only. Null,
 * writing nothing, when that key is already saved.
 */
final readonly class RegisterCredential
{
    public function __construct(private RecordAudit $audit, private CurrentTenant $tenant) {}

    /** @param  'issued_link'|'session'  $registeredVia */
    public function handle(string $employeeId, CredentialData $key, string $label, string $registeredVia): ?WorkerCredential
    {
        try {
            // Its own savepoint: a key saved twice must not abort the caller's transaction.
            $credential = DB::transaction(fn (): WorkerCredential => WorkerCredential::query()->create([
                'tenant_id' => $this->tenant->id(),
                'employee_id' => $employeeId,
                'credential_id' => $key->credentialId,
                'public_key' => $key->publicKey,
                'sign_count' => $key->signCount,
                'backup_eligible' => $key->backupEligible,
                'backup_state' => $key->backupState,
                'label' => $label,
                'registered_via' => $registeredVia,
                'registered_at' => CarbonImmutable::now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $this->audit->handle('worker_access.credential_registered', $credential, ['employee_id' => $employeeId]);
        Log::info('worker_access.credential_registered', ['key_id' => $credential->id, 'employee_id' => $employeeId]);

        return $credential;
    }
}
