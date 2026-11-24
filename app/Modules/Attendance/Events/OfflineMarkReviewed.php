<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Events;

/**
 * RH reviewed a mark made without a connection (criterio 5.4): `decision` is `verified` or `incorrect` as text (the enums
 * are internal). Dispatched after the commit, on both decisions. Ids only: never the note nor a time.
 */
final readonly class OfflineMarkReviewed
{
    public function __construct(public string $markId, public string $employeeId, public string $decision) {}
}
