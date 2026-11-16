<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

/**
 * One tool in the person's start: where it goes (an address with no credential), in plain words, and how many things wait
 * for the person in it.
 */
final readonly class WorkerSectionLink
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $href,
        public ?int $pending = null,
    ) {}
}
