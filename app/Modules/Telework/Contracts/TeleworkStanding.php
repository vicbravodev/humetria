<?php

declare(strict_types=1);

namespace App\Modules\Telework\Contracts;

use App\Modules\Telework\Data\TeleworkRow;

/**
 * Where the current company's work centers stand with NOM-037 (teletrabajo), row by row of the inspection table 10.3, as
 * other modules read it. Counts and states only: never a name, a health fact or a document of a person.
 */
interface TeleworkStanding
{
    /**
     * The rows of each center, in the table's order. A center of another company, or one that does not exist, is left
     * out, exactly like the other.
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, list<TeleworkRow>> by work center id
     */
    public function forCenters(array $workCenterIds): array;
}
