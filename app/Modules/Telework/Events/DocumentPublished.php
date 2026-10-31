<?php

declare(strict_types=1);

namespace App\Modules\Telework\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A new version of a NOM-037 document was published for one or more work centers (one document per center, sharing the
 * file). Public API: acknowledgements (Task 12) react to it. Dispatched once the publication is committed.
 */
final readonly class DocumentPublished implements ShouldDispatchAfterCommit
{
    /**
     * @param  list<string>  $documentIds
     */
    public function __construct(
        public string $tenantId,
        public array $documentIds,
    ) {}
}
