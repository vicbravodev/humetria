<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

/**
 * Since a moment, at a center: whether its mode was set, how many zones and kiosks (still in use) it got and how many marks
 * its people took (RH's corrections aside).
 */
final readonly class CenterAttendanceActivity
{
    public function __construct(
        public bool $modeSet,
        public int $zones,
        public int $kiosks,
        public int $marks,
    ) {}
}
