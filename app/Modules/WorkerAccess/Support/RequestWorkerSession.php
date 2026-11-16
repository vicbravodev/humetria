<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Data\WorkerIdentity;

/**
 * The person's session, read once per request: the middleware that lets them in, the one that asks for a recent proof and
 * the page they open ask the same, and the session is checked once. Entering, proving and leaving forget what was read
 * (WorkerSessionStore).
 */
final readonly class RequestWorkerSession implements WorkerSession
{
    public const string ATTRIBUTE = 'worker_access.session';

    public function __construct(private WorkerSessionStore $store) {}

    public function current(): ?WorkerIdentity
    {
        return $this->session()?->identity;
    }

    public function session(): ?OpenSession
    {
        $attributes = request()->attributes;

        if (! $attributes->has(self::ATTRIBUTE)) {
            $attributes->set(self::ATTRIBUTE, $this->store->open());
        }

        $session = $attributes->get(self::ATTRIBUTE);

        return $session instanceof OpenSession ? $session : null;
    }
}
