<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Data\PortalPendingData;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSection;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;

/**
 * «Su teletrabajo» in the person's start, for a company with NOM-037: to a teleworker always (their pending items and their
 * notices), to anyone else only while a document waits for them; with how many things wait.
 */
final readonly class TeleworkWorkerSection implements WorkerSection
{
    public function __construct(private SessionPortal $portals, private PortalPending $pending, private Companies $companies) {}

    public function for(WorkerIdentity $person): ?WorkerSectionLink
    {
        if ($this->companies->current()->stopped('nom037')) {
            return null;
        }

        $portal = $this->portals->for($person);

        $waiting = self::waiting($this->pending->for($portal));

        if ($portal->teleworkerId === null && $waiting === 0) {
            return null;
        }

        return new WorkerSectionLink(
            'telework',
            'Su teletrabajo',
            $portal->teleworkerId === null ? 'Los documentos que su empresa le pidió confirmar.' : 'Sus pendientes y sus avisos: cambio de domicilio, riesgo de trabajo o alteración de sus condiciones.',
            route('portal.telework.show', $this->companies->current()->slug),
            $waiting === 0 ? null : $waiting,
        );
    }

    private static function waiting(PortalPendingData $items): int
    {
        return count($items->checklists) + count($items->photos) + count($items->visits) + count($items->documents) + count($items->equipment);
    }
}
