<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * `policyReviewed` says RH saved the company's policy («Puesta en marcha»); `unscheduled` counts the active people of the
 * centers in use without a schedule; `pendingCorrections` the requests to correct a mark that wait for RH; `disputedWeeks`
 * the weeks people disputed that wait for RH to resolve them, of any week; `offlineNeedsReview` the marks made without a
 * connection that nobody reviewed and have flags or arrived over a week ago (a count, never whose).
 */
final readonly class AttendanceSetup
{
    public function __construct(
        public bool $policyReviewed,
        public int $unscheduled,
        public int $pendingCorrections,
        public int $disputedWeeks = 0,
        public int $offlineNeedsReview = 0,
    ) {}
}
