<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Data\PendingChecklist;
use App\Modules\Telework\Data\PortalPendingData;
use App\Modules\Telework\Models\Workplace;

/**
 * The pending items of a person's portal. Today: the checklists of their workplaces; documents, equipment, photo requests
 * and visit authorizations join as those arrive. A sent checklist is only ever "En revisión de la Comisión": the outcome
 * is told by RH, not by the portal (criterio C-11).
 */
final class PortalPending
{
    public function for(OpenedPortal $portal): PortalPendingData
    {
        if ($portal->teleworkerId === null) {
            return new PortalPendingData([], []);
        }

        $items = ['pending' => [], 'sent' => []];

        foreach (Workplace::query()->where('teleworker_id', $portal->teleworkerId)->whereNull('retired_at')->with('applications')->orderBy('created_at')->orderBy('id')->get() as $workplace) {
            foreach ($workplace->applications->sortBy([['requested_at', 'asc'], ['id', 'asc']]) as $application) {
                $sent = $application->submitted_at !== null;
                $items[$sent ? 'sent' : 'pending'][] = new PendingChecklist($application->id, self::address($workplace), $application->section->value, $sent ? 'En revisión de la Comisión' : 'Pendiente de contestar');
            }
        }

        return new PortalPendingData($items['pending'], $items['sent']);
    }

    private static function address(Workplace $workplace): string
    {
        $number = $workplace->interior_number === null ? $workplace->exterior_number : "{$workplace->exterior_number}, int. {$workplace->interior_number}";

        return "{$workplace->street} {$number}, {$workplace->neighborhood}, {$workplace->municipality}";
    }
}
