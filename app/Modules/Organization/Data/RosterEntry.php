<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * A person of the company as other modules need them: to invite them, to place them in a work center and an area, and
 * to split a sample by gender ("female", "male" or "other"), to tell them apart by their job title, and whether they are
 * trusted staff (de confianza). Their job grade and their staff group ("unionized" or "trusted") are null when the company
 * has not said them.
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
        public bool $trusted = false,
        public ?string $jobGradeId = null,
        public ?string $staffGroup = null,
    ) {}
}
