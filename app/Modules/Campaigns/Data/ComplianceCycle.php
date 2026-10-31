<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Data;

/**
 * Where a work center stands in the two years NOM-035 gives between evaluations (num. 7.9). While an evaluation runs,
 * the span is from its launch to its last day; otherwise from the last evaluation to the date the next one is due.
 * Days are written as the design system writes them ("2 nov 2026"); a center never evaluated has no span.
 */
final readonly class ComplianceCycle
{
    /**
     * @param  'in_course'|'up_to_date'|'overdue'|'never'  $standing
     * @param  int  $elapsed  percent of the span gone by, 0 to 100
     */
    public function __construct(
        public string $standing,
        public ?string $from,
        public ?string $until,
        public int $elapsed,
    ) {}
}
