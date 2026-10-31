<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

/**
 * How many people work where, in the current company. Deactivated employees do not count.
 */
interface Headcount
{
    /**
     * @return array<string, int> active employees by work center id; a work center with nobody is left out.
     */
    public function byWorkCenter(): array;

    /**
     * How many active people a company has, from anywhere: the console works in no company.
     */
    public function ofCompany(string $tenantId): int;

    /**
     * The areas of each of those work centers with their active people, the largest first and then by name.
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, list<array{name: string, employees: int}>> by work center id; one with nobody is left out.
     */
    public function areasByWorkCenter(array $workCenterIds): array;
}
