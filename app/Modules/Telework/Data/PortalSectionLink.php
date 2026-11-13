<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * One tool in the home of the portal: where it goes, in plain words, and how many things wait for the person in it.
 */
final readonly class PortalSectionLink
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description,
        public string $href,
        public ?int $pending = null,
    ) {}
}
