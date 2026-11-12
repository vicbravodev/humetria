<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Enums\ReportStatus;
use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\PreventiveMeasure;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\SeatingDocument;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Models\StandingPosition;

/**
 * The board of a work center: its thirteen requirements with their words, state and count, a summary of how many are met,
 * with a warning, missing or pending («Sin registros», never counted as met), and how many records each part of the center holds. Counts only: no name, no answer.
 */
final readonly class CenterBoard
{
    /** @var array<string, array{string, string|null}> key => [what is asked, the tab where it is put right] */
    private const array REQUIREMENTS = [
        'r1' => ['Sector declarado y puestos con su Figura 1', 'positions'],
        'r2' => ['Evaluación vigente de cada persona que trabaja de pie', 'people'],
        'r3' => ['Acta de cada evaluación firmada', 'people'],
        'r4' => ['Asientos en cada puesto', 'inventory'],
        'r5' => ['Asiento de descanso en un área designada', 'inventory'],
        'r6' => ['Asientos fuera del puesto señalizados', 'inventory'],
        'r7' => ['Medida para cada evaluación Media o Alta', 'plan'],
        'r8' => ['Confirmación de cada persona evaluada', 'people'],
        'r9' => ['Canalización de quien tiene malestares', 'people'],
        'r10' => ['Reglamento interior firmado, depositado y repartido', 'regulation'],
        'r11' => ['Acuerdo de pausas en cada puesto con asiento aparte', 'plan'],
        'r12' => ['Diagnóstico y programa de seguridad y salud', 'documents'],
        'r13' => ['Integrantes de la Comisión asignados al centro', null],
    ];

    public function __construct(private ComplianceRows $rows) {}

    /**
     * @return array{requirements: list<array{key: string, number: string, label: string, tab: string|null, state: string, stateLabel: string, count: int, since: string|null}>, summary: array{met: int, warning: int, missing: int, pending: int}}
     */
    public function for(string $workCenterId): array
    {
        $rows = $this->rows->for($workCenterId);
        $inState = fn (RowState $state): int => count(array_filter($rows, fn (ComplianceRow $row): bool => $row->state === $state));

        return [
            'requirements' => array_map(fn (ComplianceRow $row): array => [
                'key' => $row->key,
                'number' => strtoupper($row->key),
                'label' => self::REQUIREMENTS[$row->key][0],
                'tab' => self::REQUIREMENTS[$row->key][1],
                'state' => $row->state->value,
                'stateLabel' => $row->state->label(),
                'count' => $row->count,
                'since' => $row->since?->toDateString(),
            ], $rows),
            'summary' => ['met' => $inState(RowState::Met), 'warning' => $inState(RowState::Warning), 'missing' => $inState(RowState::Missing), 'pending' => $inState(RowState::Pending)],
        ];
    }

    /**
     * How many records each part of the center holds, for its tabs.
     *
     * @return array{positions: int, people: int, inventory: int, reports: int, plan: int, documents: int}
     */
    public function counts(string $workCenterId): array
    {
        return [
            'positions' => StandingPosition::query()->where('work_center_id', $workCenterId)->count(),
            'people' => PositionAssignment::query()->whereIn('position_id', StandingPosition::query()->where('work_center_id', $workCenterId)->select('id'))->whereNull('ends_on')->distinct()->count('employee_id'),
            'inventory' => Seat::query()->where('work_center_id', $workCenterId)->count(),
            'reports' => SeatReport::query()->where('work_center_id', $workCenterId)->where('status', ReportStatus::Open)->count(),
            'plan' => PreventiveMeasure::query()->where('work_center_id', $workCenterId)->where('status', '!=', MeasureStatus::Cancelled)->count(),
            'documents' => SeatingDocument::query()->where('work_center_id', $workCenterId)->count(),
        ];
    }
}
