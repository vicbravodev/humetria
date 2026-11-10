<?php

declare(strict_types=1);

namespace App\Modules\Leave\Data;

use Carbon\CarbonImmutable;

/**
 * A stretch of days a person is away, both ends included. The kind only says vacations or leave: the type of a leave can
 * reveal something personal and never crosses the module (criterio C-22).
 */
final readonly class AbsenceData
{
    /**
     * @param  'vacation'|'leave'  $kind
     */
    public function __construct(
        public string $employeeId,
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public string $kind,
    ) {}
}
