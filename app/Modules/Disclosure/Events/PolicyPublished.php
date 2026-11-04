<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A version of the policy of prevention was published for one or more work centers. Public API: the people of those
 * centers are asked to acknowledge it, and a reaction elsewhere (Humi) needs no change in Disclosure. Dispatched once the
 * publication is committed.
 */
final readonly class PolicyPublished implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<string>  $workCenterIds
     */
    public function __construct(
        public string $tenantId,
        public string $documentId,
        public array $workCenterIds,
    ) {}
}
