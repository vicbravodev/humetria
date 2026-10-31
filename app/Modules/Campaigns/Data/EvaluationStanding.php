<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Data;

/**
 * A work center's NOM-035 evaluation as other modules read it. State: draft (being prepared), running (its last day
 * is `day`), due (the next one is due by `day`) or overdue (it was due by `day`). Days as the design system writes
 * them ("10 nov 2026"); a draft has none.
 */
final readonly class EvaluationStanding
{
    public function __construct(
        public string $state,
        public ?string $day,
    ) {}
}
