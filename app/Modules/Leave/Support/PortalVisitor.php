<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Telework\Data\PortalIdentity;

/**
 * Who opens a vacations page of the portal: the person of the link, in a company that uses Vacaciones. Anything else (a
 * link that is not valid, a company that never opened the tool) is a 404 that says nothing more.
 */
final readonly class PortalVisitor
{
    public function __construct(private PortalAccess $access) {}

    public function open(string $token): PortalIdentity
    {
        $person = $this->access->open($token);

        if ($person === null || ! LeaveSetting::query()->exists()) {
            abort(404);
        }

        return $person;
    }
}
