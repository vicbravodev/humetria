<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Data;

/**
 * One criterion of a norm as Humetría reads it: its number (C-23), its title, who it comes from (Norma, STPS, Humetría)
 * and its text.
 */
final readonly class RegulationChunk
{
    public function __construct(
        public string $norm,
        public string $id,
        public string $title,
        public string $source,
        public string $text,
    ) {}
}
