<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Data;

/**
 * A work center's NOM-035 evaluation as other modules read it. State: draft (being prepared), running (its last day
 * is `day`), due (the next one is due by `day`) or overdue (it was due by `day`). Days as the design system writes
 * them ("10 nov 2026"); `date` is the same day as Y-m-d, for a form that takes a date. A draft has neither. A due one says
 * with how many answers it closed: `answered` of `target` (C-57).
 */
final readonly class EvaluationStanding
{
    public function __construct(
        public string $state,
        public ?string $day,
        public ?string $date = null,
        public ?int $answered = null,
        public ?int $target = null,
    ) {}
}
