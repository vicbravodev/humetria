<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * A visit the Comisión asked a person to authorize, as their portal lists it.
 */
final readonly class PendingVisit
{
    public function __construct(public string $id, public string $workplace) {}
}
