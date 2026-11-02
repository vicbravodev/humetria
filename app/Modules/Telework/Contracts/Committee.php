<?php

declare(strict_types=1);

namespace App\Modules\Telework\Contracts;

/**
 * Who sits on the Comisión de seguridad e higiene of which work center of the current company. The Comisión is the
 * company's, not a tool's: other tools (Ley Silla) read it here instead of keeping their own.
 */
interface Committee
{
    public function isMember(int $userId, string $workCenterId): bool;

    /** @return list<string> */
    public function workCenterIds(int $userId): array;
}
