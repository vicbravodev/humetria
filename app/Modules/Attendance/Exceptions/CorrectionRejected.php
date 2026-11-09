<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Exceptions;

use RuntimeException;

/**
 * A request to correct the record that cannot be taken or resolved: `mark_not_found` (not an effective mark of the
 * person), `reason_invalid` (over 500 characters or with control characters), `type_mismatch`, `future_time`, `too_old`, `already_requested` (a pending request of that mark),
 * `too_many_pending`, `already_resolved`, `mark_changed` (the mark was corrected since) or `sequence_invalid` (the day
 * would not follow: an end before its start, a start while another is open).
 */
final class CorrectionRejected extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("The correction request was rejected: {$reason}.");
    }
}
