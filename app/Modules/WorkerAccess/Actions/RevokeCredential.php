<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Actions\RecordAudit;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stops a person's key of the company from verifying, at once. The revocation goes to the audit trail. Revoking it again
 * changes nothing: it keeps when it was first.
 */
final readonly class RevokeCredential
{
    public function __construct(private RecordAudit $audit) {}

    /** @throws ModelNotFoundException when the key is not the company's */
    public function handle(string $credentialId): void
    {
        $credential = WorkerCredential::query()->findOrFail($credentialId);

        // One conditional UPDATE: of two revocations at once, only one writes, so the audit trail has it once.
        $revoked = DB::transaction(function () use ($credential): bool {
            if (WorkerCredential::query()->whereKey($credential->id)->whereNull('revoked_at')->update(['revoked_at' => CarbonImmutable::now(), 'revoked_reason' => 'revoked_by_hr']) !== 1) {
                return false;
            }

            $this->audit->handle('worker_access.credential_revoked', $credential, ['employee_id' => $credential->employee_id]);

            return true;
        });

        if ($revoked) {
            Log::info('worker_access.credential_revoked', ['key_id' => $credential->id, 'employee_id' => $credential->employee_id]);
            event(new WorkerAccessRevoked($credential->employee_id, 'revoked_by_hr', $credential->id));
        }
    }
}
