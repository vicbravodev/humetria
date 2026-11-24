<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Actions\RecordAudit;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use App\Modules\WorkerAccess\Models\WorkerAccessState;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Notifications\KeyDisowned;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * «No fui yo» (CUJ 6): the person says a key registered to them was not theirs. At once the key stops verifying (reason
 * `not_me`, even when RH had revoked it before), every session of the person ends (valid_after moves) and every link of
 * theirs that still opens is revoked, as is every device key of the app; the audit trail has it and the company's contact is told. Said again, it changes
 * nothing.
 */
final readonly class DisownKey
{
    public function __construct(private RecordAudit $audit, private Companies $companies, private Roster $roster) {}

    /** @throws ModelNotFoundException when the key is not the company's */
    public function handle(string $credentialId): void
    {
        $credential = WorkerCredential::query()->findOrFail($credentialId);

        $disowned = DB::transaction(function () use ($credential): bool {
            // One conditional UPDATE: of two confirmations at once only one writes, so everything below happens once.
            $updated = WorkerCredential::query()->whereKey($credential->id)
                ->where(fn ($query) => $query->whereNull('revoked_reason')->orWhere('revoked_reason', '!=', 'not_me'))
                ->update(['revoked_at' => $credential->revoked_at ?? CarbonImmutable::now(), 'revoked_reason' => 'not_me']);

            if ($updated !== 1) {
                return false;
            }

            WorkerAccessState::moveValidAfter($credential->tenant_id, $credential->employee_id);
            WorkerAccessLink::revokePending($credential->employee_id);
            // A phone prepared in the intruder's session stops signing marks too (plan B of the offline marks).
            WorkerDeviceKey::revokeAllOf($credential->employee_id, 'not_me');
            $this->audit->handle('worker_access.not_me_reported', $credential, ['employee_id' => $credential->employee_id]);

            return true;
        });

        if (! $disowned) {
            return;
        }

        Log::info('worker_access.not_me', ['key_id' => $credential->id, 'employee_id' => $credential->employee_id]);
        event(new WorkerAccessRevoked($credential->employee_id, 'not_me', $credential->id));
        $contact = $this->companies->contact();
        $person = $this->roster->find($credential->employee_id);

        if ($contact !== null && $person !== null) {
            Notification::route('mail', $contact)->notify(new KeyDisowned($person->name, $credential->label, $this->companies->current()));
        }
    }
}
