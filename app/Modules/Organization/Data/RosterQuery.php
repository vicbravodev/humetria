<?php

declare(strict_types=1);

namespace App\Modules\Organization\Data;

/**
 * Which people of the company another module asks for: some by id (deactivated ones included), or the active people
 * of a work center; narrowed by text in the name, email or number, and by area.
 */
final readonly class RosterQuery
{
    /**
     * @param  list<string>|null  $employeeIds
     */
    public function __construct(
        public ?array $employeeIds = null,
        public ?string $activeInWorkCenter = null,
        public string $search = '',
        public ?string $areaId = null,
    ) {}
}
