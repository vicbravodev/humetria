<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Illuminate\Support\Facades\Log;

/**
 * Spends a one-time link of the current company: the link it was, or null, changing nothing, when it does not work (used,
 * revoked, expired, unknown, of another company) or its person left. One conditional UPDATE spends it, so of two requests
 * with the same link at once only one gets it.
 */
final readonly class OpenAccessLink
{
    public function __construct(private Roster $roster) {}

    public function handle(string $token): ?WorkerAccessLink
    {
        $link = WorkerAccessLink::findPending($token);
        $person = $link === null ? null : $this->roster->find($link->employee_id);

        if ($link === null || $person === null || ! $person->active || ! $link->spend()) {
            return null;
        }

        Log::info('worker_access.link_used', ['link_id' => $link->id, 'employee_id' => $link->employee_id, 'kind' => $link->kind->value]);

        return $link;
    }
}
