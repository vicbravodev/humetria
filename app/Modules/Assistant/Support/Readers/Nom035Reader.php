<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Campaigns\Contracts\EvaluationSchedule;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * NOM-035 by work center (num. 7.9: evaluate at least every two years). A center never evaluated or overdue is pending;
 * one with an evaluation in preparation or running is in progress; one whose next evaluation is not due yet is up to date.
 * The norm is pending while any center is, in progress while any is running, up to date when all are.
 */
final readonly class Nom035Reader implements ComplianceReader
{
    public function __construct(private WorkCenters $workCenters, private EvaluationSchedule $schedule, private PanelLinks $links) {}

    public function status(): ComplianceStatus
    {
        $ids = array_map(fn (WorkCenterSummary $center): string => $center->id, array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived));

        if ($ids === []) {
            return new ComplianceStatus('nom035', 'NOM-035', 'pending', 'Pendiente', 'Aún no registras centros de trabajo.', $this->links->workCenters());
        }

        $standings = $this->schedule->forWorkCenters(array_values($ids));
        $running = 0;
        $upToDate = 0;

        foreach ($ids as $id) {
            match ($standings[$id]->state ?? null) {
                'draft', 'running' => $running++,
                'due' => $upToDate++,
                default => null,
            };
        }

        $pending = count($ids) - $running - $upToDate;
        [$state, $label] = match (true) {
            $pending > 0 => ['pending', 'Pendiente'],
            $running > 0 => ['in_progress', 'En curso'],
            default => ['up_to_date', 'Al día'],
        };
        $summary = trans_choice('{1} De :count centro de trabajo|[2,*] De :count centros de trabajo', count($ids))
            .": {$upToDate} al día, {$running} en curso, ".trans_choice('{1} :count pendiente|[0,2,*] :count pendientes', $pending).'.';

        return new ComplianceStatus('nom035', 'NOM-035', $state, $label, $summary, $this->links->campaigns());
    }
}
