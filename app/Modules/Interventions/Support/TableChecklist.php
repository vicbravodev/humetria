<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionStatus;

/**
 * «Lo que pide la Tabla» (II.4 and III.4; criterio C-34): what Tablas 4 and 7 ask the Programa to include, according to the
 * highest level of the evaluation. From Medio: to review the prevention policy and to reinforce its application and
 * dissemination. From Alto: the analysis of each category and domain and an awareness campaign; at Muy alto, specific
 * evaluations too (at Alto the norm says they «podrá incluir» one, so it is shown as optional). A line of a kind of action
 * stands where its most advanced action stands: planned, running or done; the analysis is done when every row of Alto or Muy
 * alto has it written. It says nothing of whether that is enough, and it blocks nothing.
 */
final class TableChecklist
{
    private const array TEXTS = [
        'policy_review' => 'Revisar la política de prevención de riesgos psicosociales y los programas',
        'policy_dissemination' => 'Reforzar su aplicación y difusión',
        'analysis' => 'Análisis de cada categoría y dominio',
        'awareness_campaign' => 'Campaña de sensibilización',
        'specific_assessment' => 'Evaluaciones específicas',
    ];

    /**
     * @param  list<array{kind: ActionKind, status: ActionStatus}>  $actions  the kind and state of the actions of the Programa
     * @param  list<array{level: RiskLevel, written: bool}>  $rows  the rows that came from the results: their level and whether their analysis is written
     * @return list<array{key: string, text: string, status: 'planned'|'in_progress'|'done'|'missing'|'optional', detail: string|null}>
     */
    public static function for(?RiskLevel $highest, array $actions, array $rows): array
    {
        if ($highest === null) {
            return [];
        }

        $lines = [
            self::line('policy_review', self::stage($actions, ActionKind::PolicyReview)),
            self::line('policy_dissemination', self::stage($actions, ActionKind::PolicyDissemination)),
        ];

        if (! in_array($highest, [RiskLevel::High, RiskLevel::VeryHigh], true)) {
            return $lines;
        }

        $high = array_values(array_filter($rows, fn (array $row): bool => in_array($row['level'], [RiskLevel::High, RiskLevel::VeryHigh], true)));
        $written = count(array_filter($high, fn (array $row): bool => $row['written']));
        $lines[] = self::line('analysis', $written === count($high) ? 'done' : null, $written.' de '.count($high).(count($high) === 1 ? ' fila' : ' filas'));
        $lines[] = self::line('awareness_campaign', self::stage($actions, ActionKind::AwarenessCampaign));
        $lines[] = self::line('specific_assessment', self::stage($actions, ActionKind::SpecificAssessment), optional: $highest === RiskLevel::High);

        return $lines;
    }

    /**
     * Where the most advanced action of a kind stands; null when there is none that is not cancelled.
     *
     * @param  list<array{kind: ActionKind, status: ActionStatus}>  $actions
     * @return 'planned'|'in_progress'|'done'|null
     */
    private static function stage(array $actions, ActionKind $kind): ?string
    {
        $statuses = array_map(fn (array $action): ActionStatus => $action['status'], array_filter($actions, fn (array $action): bool => $action['kind'] === $kind));

        return match (true) {
            in_array(ActionStatus::Done, $statuses, true) => 'done',
            in_array(ActionStatus::InProgress, $statuses, true) => 'in_progress',
            in_array(ActionStatus::Planned, $statuses, true) => 'planned',
            default => null,
        };
    }

    /**
     * @param  'planned'|'in_progress'|'done'|null  $stage  null when nothing of it is recorded
     * @return array{key: string, text: string, status: 'planned'|'in_progress'|'done'|'missing'|'optional', detail: string|null}
     */
    private static function line(string $key, ?string $stage, ?string $detail = null, bool $optional = false): array
    {
        return ['key' => $key, 'text' => self::TEXTS[$key], 'status' => $stage ?? ($optional ? 'optional' : 'missing'), 'detail' => $detail];
    }
}
