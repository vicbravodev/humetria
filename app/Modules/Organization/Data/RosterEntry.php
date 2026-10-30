<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * A person of the company as other modules need them: to invite them, to place them in a work center and an area, and
 * to split a sample by gender ("female", "male" or "other"), and to tell them apart by their job title.
 */
final readonly class RosterEntry
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public string $gender,
        public string $areaId,
        public string $areaName,
        public string $workCenterId,
        public bool $active,
        public string $jobTitle,
    ) {}
}
