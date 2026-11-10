<?php

declare(strict_types=1);

namespace App\Modules\Organization\Contracts;

/**
 * Who reports to whom in the current company. The line has as many levels as the company does; a person of another
 * company is nobody here.
 */
interface ReportingLines
{
    /**
     * The direct manager, whether they still work here or not; null with no manager.
     */
    public function managerOf(string $employeeId): ?string;

    /**
     * From the direct manager up to the top, without the person. Managers who left stay in the chain.
     *
     * @return list<string>
     */
    public function chainOf(string $employeeId): array;

    /**
     * The active people who report directly to the person, by name.
     *
     * @return list<string>
     */
    public function directReportIds(string $employeeId): array;

    /**
     * Every active person below, without the person. A manager in between who left is not listed, but their reports
     * still belong to the line.
     *
     * @return list<string>
     */
    public function subtreeIds(string $employeeId): array;
}
