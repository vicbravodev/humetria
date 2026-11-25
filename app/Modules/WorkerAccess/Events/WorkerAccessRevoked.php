<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Events;

/**
 * A person's access was taken away without their phone in hand: RH revoked a key or reissued their access, they said «No fui yo»,
 * or they left the company. `reason` is `revoked_by_hr`, `reissued`, `not_me` or `left_company`; `credentialId` names the key
 * when it was one (null when the whole access of the person fell). Dispatched after the commit, only when something changed.
 * Ids only. «Salir» dispatches nothing: the phone itself gives its push subscription up.
 */
final readonly class WorkerAccessRevoked
{
    public function __construct(public string $employeeId, public string $reason, public ?string $credentialId = null) {}
}
