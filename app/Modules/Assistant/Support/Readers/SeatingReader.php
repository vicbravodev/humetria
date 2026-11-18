<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Seating\Contracts\SeatingRoute;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * Ley Silla from the six-stage route of each center where someone works standing; one that declared nobody does is left
 * out, one that has not said yet keeps it pending. Up to date when every such center finished its route, pending while
 * any has not started or not answered, in progress otherwise.
 */
final readonly class SeatingReader implements ComplianceReader
{
    public function __construct(private WorkCenters $workCenters, private SeatingRoute $route, private PanelLinks $links) {}

    public function status(): ComplianceStatus
    {
        $ids = array_values(array_map(fn (WorkCenterSummary $center): string => $center->id, array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)));
        $progress = $this->route->progress($ids);
        $applies = array_filter($progress, fn (RouteProgress $center): bool => $center->applies);
        $unanswered = count(array_filter($progress, fn (RouteProgress $center): bool => ! $center->answered));

        if ($applies === [] && $unanswered === 0) {
            return new ComplianceStatus('seating', 'Ley Silla', 'not_applicable', 'No aplica', 'Nadie trabaja de pie en tus centros.', $this->links->seating());
        }

        $done = array_sum(array_map(fn (RouteProgress $center): int => count(array_filter($center->done)), $applies));
        $stages = count($applies) * RouteProgress::STAGES;

        [$state, $label] = match (true) {
            $unanswered > 0 || array_any($applies, fn (RouteProgress $center): bool => ! in_array(true, $center->done, true)) => ['pending', 'Pendiente'],
            $done === $stages => ['up_to_date', 'Al día'],
            default => ['in_progress', 'En curso'],
        };

        $summary = implode(' ', array_filter([
            $applies === [] ? null : "Cumples {$done} de {$stages} etapas de la ruta en ".trans_choice('{1} :count centro con puestos de pie|[2,*] :count centros con puestos de pie', count($applies)).'.',
            $unanswered === 0 ? null : 'Falta contestar si aplica en '.trans_choice('{1} :count centro|[2,*] :count centros', $unanswered).'.',
        ]));

        return new ComplianceStatus('seating', 'Ley Silla', $state, $label, $summary, $this->links->seating());
    }
}
