<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Data;

/**
 * `people` are the active people of the roster and `costed` those the calculator can cost; `gradesWithoutSalary` the
 * categories with no salary in force today; `scenarios` the scenarios of the roster not archived and `averageScenarios` the ones
 * of an average payroll («Cotizador rápido») not archived.
 */
final readonly class ContractStanding
{
    public function __construct(
        public int $people,
        public int $costed,
        public int $gradesWithoutSalary,
        public int $scenarios,
        public int $averageScenarios = 0,
    ) {}
}
