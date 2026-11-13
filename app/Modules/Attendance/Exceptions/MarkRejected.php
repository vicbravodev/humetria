<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/**
 * A mark the company's policy does not take (criterios 4.2 to 4.4): `qr_invalid`, `qr_required`, `no_location`,
 * `accuracy_too_low`, `outside_zone` or `center_not_ready`. The reason is a stable code; the screen words it.
 */
final class MarkRejected extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("The mark was rejected: {$reason}.");
    }
}
