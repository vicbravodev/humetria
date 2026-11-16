<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Data\WorkerIdentity;

/**
 * Who opens a vacations page of the person: the person of the session, in a company that uses Vacaciones. A company that
 * never opened the tool is a 404 that says nothing more.
 */
final readonly class PortalVisitor
{
    public function __construct(private WorkerSession $session) {}

    public function current(): WorkerIdentity
    {
        $person = $this->session->current();

        if ($person === null || ! LeaveSetting::query()->exists()) {
            abort(404);
        }

        return $person;
    }
}
