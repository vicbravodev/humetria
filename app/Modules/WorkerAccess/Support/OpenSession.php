<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Enums\EntryVia;
use Carbon\CarbonImmutable;

/**
 * A person's session that holds in this request: who, how they entered, the key they entered with and their last recent proof.
 */
final readonly class OpenSession
{
    public function __construct(
        public WorkerIdentity $identity,
        public EntryVia $via,
        public ?string $credentialId,
        public ?CarbonImmutable $proofAt,
    ) {}
}
