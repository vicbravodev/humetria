<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Events;

/** The access code changed: every printed QR and every link given out before is dead. */
final readonly class ComplaintChannelCodeRotated
{
    public function __construct(public string $tenantId) {}
}
