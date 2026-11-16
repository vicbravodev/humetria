<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Data\WorkerIdentity;

/**
 * The person of the session, read once per request: the middleware that lets them in and the page they open ask the same,
 * and the session is checked once. Entering and leaving forget what was read (WorkerSessionStore).
 */
final readonly class RequestWorkerSession implements WorkerSession
{
    public const string ATTRIBUTE = 'worker_access.identity';

    public function __construct(private WorkerSessionStore $store) {}

    public function current(): ?WorkerIdentity
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::ATTRIBUTE)) {
            $attributes->set(self::ATTRIBUTE, $this->store->current());
        }

        $identity = $attributes->get(self::ATTRIBUTE);

        return $identity instanceof WorkerIdentity ? $identity : null;
    }
}
