<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * `policyReviewed` says RH saved the company's policy («Puesta en marcha»); `unscheduled` counts the active people of the
 * centers in use without a schedule; `pendingCorrections` the requests to correct a mark that wait for RH.
 */
final readonly class AttendanceSetup
{
    public function __construct(
        public bool $policyReviewed,
        public int $unscheduled,
        public int $pendingCorrections,
    ) {}
}
