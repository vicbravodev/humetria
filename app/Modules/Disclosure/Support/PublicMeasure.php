<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

/**
 * A measure of the Programa as the public page may show it, and nothing more: what it is called, which inciso of 5.7 it
 * answers (b: violence and the work environment; c: prevention and control), its level (the first or second of num. 8.5), where it
 * stands and by when. No person, area, note, file or responsible (criterio C-33).
 */
final readonly class PublicMeasure
{
    public function __construct(
        public string $title,
        public string $inciso,
        public int $level,
        public string $status,
        public string $statusLabel,
        public string $dueOn,
    ) {}
}
