<?php

declare(strict_types=1);

namespace App\Modules\Telework\Contracts;

use App\Modules\Telework\Data\UnreviewedAccidents;
use App\Modules\Tenancy\Data\RouteProgress;

/**
 * The NOM-037 route of the current company as the panel reads it: where each work center stands in its six stages and the
 * accident notices that wait to be reviewed. Counts, states and, for a lone notice, the name of who sent it: never what the
 * notice says.
 */
interface TeleworkRoute
{
    /**
     * A center of another company, or one that does not exist, is left out.
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, RouteProgress> by work center id
     */
    public function progress(array $workCenterIds): array;

    /**
     * By center, the oldest waiting first.
     *
     * @return list<UnreviewedAccidents>
     */
    public function accidents(): array;
}
