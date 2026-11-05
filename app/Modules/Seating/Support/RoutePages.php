<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\ReportStatus;
use App\Modules\Seating\Enums\RowState;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * What the Ley Silla route of a center sends to the browser (board V2-Ruta-Silla): its thirteen requirements in plain words,
 * each with the stage it belongs to and its way to fix it for who may; the seat reports that cannot wait and how many people
 * answered their two questions, for who assesses; and the way to «Si hoy llegara una inspección». Counts and states only:
 * no name, no answer.
 */
final readonly class RoutePages
{
    /** The requirements as an inspector would ask for them. */
    private const array PLAIN = [
        'r1' => 'Los puestos de pie, con su Figura 1',
        'r2' => 'Cada persona que trabaja de pie, evaluada',
        'r3' => 'Cada evaluación firmada, en un acta',
        'r4' => 'Asientos en cada puesto',
        'r5' => 'Un asiento de descanso donde el asiento va aparte',
        'r6' => 'Los asientos fuera del puesto, señalizados',
        'r7' => 'Una medida para cada riesgo medio o alto',
        'r8' => 'Cada persona enterada de su resultado',
        'r9' => 'Quien tiene malestares, canalizado',
        'r10' => 'Reglamento interior firmado, depositado y repartido',
        'r11' => 'Pausas acordadas donde el asiento va aparte',
        'r12' => 'Diagnóstico y programa de seguridad y salud',
        'r13' => 'La Comisión asignada al centro',
    ];

    /** What is behind a requirement that is not met: [missing, one] [missing, many] [warning, one] [warning, many]. */
    private const array DETAIL = [
        'r1' => ['Declara el sector y registra los puestos de pie', 'Declara el sector y registra los puestos de pie', '1 puesto sin nadie asignado', '%d puestos sin nadie asignado'],
        'r2' => ['1 persona sin evaluar', '%d personas sin evaluar', '1 evaluación por renovar', '%d evaluaciones por renovar'],
        'r3' => ['1 evaluación firmada sin acta', '%d evaluaciones firmadas sin acta', '', ''],
        'r4' => ['1 puesto sin asiento', '%d puestos sin asiento', '1 puesto con asientos de menos o por reparar', '%d puestos con asientos de menos o por reparar'],
        'r5' => ['1 puesto sin asiento de descanso', '%d puestos sin asiento de descanso', '', ''],
        'r6' => ['1 asiento sin señalizar', '%d asientos sin señalizar', '', ''],
        'r7' => ['1 evaluación de riesgo medio o alto sin medida', '%d evaluaciones de riesgo medio o alto sin medida', '1 medida vencida', '%d medidas vencidas'],
        'r8' => ['1 persona sin confirmar su resultado', '%d personas sin confirmar su resultado', '', ''],
        'r9' => ['1 persona con malestares sin canalizar', '%d personas con malestares sin canalizar', '', ''],
        'r10' => ['Falta completarlo y firmarlo', 'Falta completarlo y firmarlo', 'Falta depositarlo o repartirlo', 'Falta depositarlo o repartirlo'],
        'r11' => ['1 puesto con asiento aparte sin acuerdo de pausas', '%d puestos con asiento aparte sin acuerdo de pausas', '', ''],
        'r12' => ['Falta el documento; hay plantilla con lo que ya capturaste', 'Falta el documento; hay plantilla con lo que ya capturaste', '', ''],
        'r13' => ['Nadie de la Comisión está asignado al centro', 'Nadie de la Comisión está asignado al centro', '', ''],
    ];

    /**
     * @param  list<ComplianceRow>  $rows
     * @return array<string, mixed>
     */
    public function route(mixed $tenant, WorkCenterSummary $center, ComplianceFacts $facts, array $rows, RouteProgress $progress): array
    {
        $assess = Gate::allows('assess-seating');
        $people = array_values(array_unique($facts->assignments->map(fn (PositionAssignment $assignment): string => $assignment->employee_id)->all()));

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'people' => count($people),
            'positions' => $facts->positions->count(),
            'route' => ['applies' => $progress->applies, 'done' => $progress->done, 'status' => $progress->status, 'answered' => $progress->answered],
            'rows' => array_map(fn (ComplianceRow $row): array => [...$this->item($tenant, $center, $row), 'stage' => intdiv(RouteStages::rank($row->key), 100) + 1], $rows),
            'reports' => $assess ? $this->reports($tenant, $center) : null,
            'inputs' => $assess ? ['answered' => PersonInput::query()->where('work_center_id', $center->id)->whereIn('employee_id', $people)->distinct()->count('employee_id'), 'people' => count($people)] : null,
            'inspectionHref' => route('tenant.seating.inspection', [$tenant, $center->id]),
        ];
    }

    /**
     * «Si hoy llegara una inspección» (board V3-Inspeccion): the thirteen requirements split into what the center can show and
     * what is missing, both in the order of the route, each in plain words with its way to fix it for who may; and the dossier
     * for who may read it.
     *
     * @param  list<ComplianceRow>  $rows
     * @return array<string, mixed>
     */
    public function inspection(mixed $tenant, WorkCenterSummary $center, array $rows): array
    {
        usort($rows, fn (ComplianceRow $a, ComplianceRow $b): int => RouteStages::rank($a->key) <=> RouteStages::rank($b->key));
        $items = fn (bool $met): array => array_values(array_map(fn (ComplianceRow $row): array => $this->item($tenant, $center, $row), array_filter($rows, fn (ComplianceRow $row): bool => ($row->state === RowState::Met) === $met)));

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'applicable' => count($rows),
            'ready' => $items(true),
            'missing' => $items(false),
            'dossierHref' => Gate::allows('assess-seating') ? route('tenant.seating.dossier', [$tenant, $center->id]) : null,
        ];
    }

    /**
     * A requirement in plain words, where it stands and its way to fix it (or, met, to see it).
     *
     * @return array{numeral: string, title: string, detail: string|null, state: string, href: string|null, action: string|null}
     */
    private function item(mixed $tenant, WorkCenterSummary $center, ComplianceRow $row): array
    {
        [$missingOne, $missingMany, $warningOne, $warningMany] = self::DETAIL[$row->key];
        $detail = match ($row->state) {
            RowState::Met => null,
            RowState::Missing => sprintf($row->count === 1 ? $missingOne : $missingMany, $row->count),
            RowState::Warning => sprintf($row->count === 1 ? $warningOne : $warningMany, $row->count),
        };

        return [
            'numeral' => strtoupper($row->key),
            'title' => self::PLAIN[$row->key],
            'detail' => $detail,
            'state' => match ($row->state) {
                RowState::Met => 'done',
                RowState::Warning => 'partial',
                RowState::Missing => 'missing',
            },
            ...$this->fix($tenant, $center, $row->key, $row->state === RowState::Met),
        ];
    }

    /**
     * Where to fix a requirement, when the person may: nothing for the viewer. A requirement met opens where its evidence is.
     *
     * @return array{href: string|null, action: string|null}
     */
    private function fix(mixed $tenant, WorkCenterSummary $center, string $key, bool $met): array
    {
        $at = [$tenant, $center->id];
        $assess = Gate::allows('assess-seating');
        $manage = Gate::allows('manage-seating');
        [$route, $action, $may] = match ($key) {
            'r1' => ['tenant.seating.center', $met ? 'Ver puestos' : 'Registrar puestos', $manage],
            'r2' => ['tenant.seating.assessments.index', 'Evaluar', $assess],
            'r3' => ['tenant.seating.assessments.index', 'Asentar en un acta', $assess],
            'r8' => ['tenant.seating.assessments.index', 'Pedir la confirmación', $assess],
            'r9' => ['tenant.seating.assessments.index', 'Canalizar', $assess],
            'r4', 'r5', 'r6' => ['tenant.seating.seats.index', $met ? 'Ver asientos' : 'Registrar asientos', $manage],
            'r7' => ['tenant.seating.plan', 'Registrar medida', $assess],
            'r11' => ['tenant.seating.plan', 'Registrar acuerdo', $assess],
            'r10' => ['tenant.seating.regulation.show', 'Continuar el reglamento', $assess],
            'r12' => ['tenant.seating.documents.index', 'Registrar el documento', $assess],
            default => ['tenant.telework.committee.show', 'Asignar a la Comisión', Gate::allows('manage-telework')],
        };

        return $may ? ['href' => route($route, $at), 'action' => $met ? $this->seen($action) : $action] : ['href' => null, 'action' => null];
    }

    /** A requirement met is seen, not fixed: «Ver» unless its action already says what to see. */
    private function seen(string $action): string
    {
        return str_starts_with($action, 'Ver') ? $action : 'Ver';
    }

    /**
     * The seat reports open for longer than the alert allows: how many, since when the oldest waits, and where to resolve them.
     *
     * @return array{count: int, since: string, waiting: string, href: string}|null
     */
    private function reports(mixed $tenant, WorkCenterSummary $center): ?array
    {
        $late = SeatReport::query()->where('work_center_id', $center->id)->where('status', ReportStatus::Open)->where('created_at', '<', now()->subDays(config()->integer('seating.report_alert_days')))->orderBy('created_at')->get(['created_at']);
        $oldest = $late->first()?->created_at;

        if ($oldest === null) {
            return null;
        }

        $since = CarbonImmutable::parse($oldest)->setTimezone(config()->string('app.display_timezone'));

        return ['count' => $late->count(), 'since' => $since->toDateString(), 'waiting' => $since->diffForHumans(), 'href' => route('tenant.seating.reports.index', [$tenant, $center->id])];
    }
}
