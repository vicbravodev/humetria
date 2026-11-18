<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Leave\Contracts\PendingRequests;

/** Vacaciones by the requests that wait for RH («Por atender»): how many, never who asked nor why. */
final readonly class VacationsReader implements ComplianceReader
{
    public function __construct(private PendingRequests $requests, private PanelLinks $links) {}

    public function status(AssistantScope $scope): ComplianceStatus
    {
        $waiting = $this->requests->waiting();

        return $waiting === 0
            ? new ComplianceStatus('vacations', 'Vacaciones', 'up_to_date', 'Al día', 'No hay solicitudes de vacaciones por atender.', $this->links->vacationRequests())
            : new ComplianceStatus('vacations', 'Vacaciones', 'pending', 'Pendiente', 'Hay '.trans_choice('{1} :count solicitud|[2,*] :count solicitudes', $waiting).' de vacaciones por atender.', $this->links->vacationRequests());
    }
}
