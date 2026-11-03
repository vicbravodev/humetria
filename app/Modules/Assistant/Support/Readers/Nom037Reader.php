<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Telework\Contracts\TeleworkStanding;
use App\Modules\Telework\Data\TeleworkRow;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * NOM-037 (teletrabajo) from the inspection table 10.3, only for the centers where someone teleworks or is about to (the
 * list of people, row 5.1, applies). A center is up to date when every row that applies is done, pending when none is
 * done or partial, in progress otherwise; the norm takes the worst: pending if any center is, then in progress.
 */
final readonly class Nom037Reader implements ComplianceReader
{
    public function __construct(private WorkCenters $workCenters, private TeleworkStanding $standing, private PanelLinks $links) {}

    public function status(): ComplianceStatus
    {
        $ids = array_values(array_map(fn (WorkCenterSummary $center): string => $center->id, array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)));
        $centers = array_filter(
            array_map(fn (array $rows): array => array_values(array_filter($rows, fn (TeleworkRow $row): bool => $row->state !== 'not_applicable')), $this->standing->forCenters($ids)),
            fn (array $rows): bool => array_any($rows, fn (TeleworkRow $row): bool => $row->numeral === '5.1'),
        );

        if ($centers === []) {
            return new ComplianceStatus('nom037', 'NOM-037', 'not_applicable', 'No aplica', 'Nadie teletrabaja todavía en tus centros.', $this->links->telework());
        }

        $points = 0;
        $done = 0;
        $pending = 0;
        $complete = 0;

        foreach ($centers as $rows) {
            $doneHere = count(array_filter($rows, fn (TeleworkRow $row): bool => $row->state === 'done'));
            $partialHere = count(array_filter($rows, fn (TeleworkRow $row): bool => $row->state === 'partial'));
            $points += count($rows);
            $done += $doneHere;
            $pending += $doneHere + $partialHere === 0 ? 1 : 0;
            $complete += $doneHere === count($rows) ? 1 : 0;
        }

        [$state, $label] = match (true) {
            $pending > 0 => ['pending', 'Pendiente'],
            $complete === count($centers) => ['up_to_date', 'Al día'],
            default => ['in_progress', 'En curso'],
        };

        return new ComplianceStatus(
            'nom037',
            'NOM-037',
            $state,
            $label,
            "Cumples {$done} de {$points} puntos de la tabla 10.3 en ".trans_choice('{1} :count centro con teletrabajo|[2,*] :count centros con teletrabajo', count($centers)).'.',
            $this->links->telework(),
        );
    }
}
