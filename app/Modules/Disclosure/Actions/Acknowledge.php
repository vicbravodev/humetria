<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Support\PolicyStanding;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A person confirms, from their link, that they read the version of the policy in force in their work center (criterio C-53):
 * the day and the time stay as evidence, no address. A request that is not theirs does not exist; a second confirmation, or one
 * of a version already superseded, is refused and changes nothing.
 */
final readonly class Acknowledge
{
    public function __construct(private Roster $roster, private PolicyStanding $standing) {}

    /**
     * @throws ModelNotFoundException when the version was not asked of this person.
     * @throws ValidationException when it is no longer the one in force, or it was already confirmed.
     */
    public function handle(string $employeeId, string $documentId): void
    {
        DB::transaction(function () use ($employeeId, $documentId): void {
            $request = Acknowledgment::query()->where('document_id', $documentId)->where('employee_id', $employeeId)->lockForUpdate()->firstOrFail();
            $center = $this->roster->find($employeeId)->workCenterId ?? '';

            if (($this->standing->inForce([$center])[$center]->documentId ?? null) !== $documentId) {
                throw ValidationException::withMessages(['document' => 'Esta versión ya no está vigente: hay una más reciente.']);
            }

            if ($request->acknowledged_at !== null) {
                throw ValidationException::withMessages(['document' => 'Ya confirmó esta política.']);
            }

            $request->update(['channel' => 'portal', 'acknowledged_at' => now()]);

            Log::info('disclosure.acknowledged', ['document_id' => $documentId, 'employee_id' => $employeeId, 'channel' => 'portal']);
        });
    }
}
