<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Interventions\Enums\ActionKind;

/**
 * «Lo que pide la Tabla» (II.4 and III.4; criterio C-34): what Tablas 4 and 7 ask the Programa to include, according to the
 * highest level of the evaluation. From Medio: to review the prevention policy and to reinforce its application and
 * dissemination. From Alto: the analysis of each category and domain and an awareness campaign; at Muy alto, specific
 * evaluations too (at Alto the norm says they «podrá incluir» one, so it is shown as optional). A line is «covered» when
 * something of its kind is recorded: it says nothing of whether that is enough, and it blocks nothing.
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
     * @param  list<ActionKind>  $kinds  the kinds of the actions that are not cancelled
     * @param  list<array{level: RiskLevel, written: bool}>  $rows  the rows that came from the results: their level and whether their analysis is written
     * @return list<array{key: string, text: string, status: 'covered'|'missing'|'optional', detail: string|null}>
     */
    public static function for(?RiskLevel $highest, array $kinds, array $rows): array
    {
        if ($highest === null) {
            return [];
        }

        $lines = [
            self::line('policy_review', in_array(ActionKind::PolicyReview, $kinds, true)),
            self::line('policy_dissemination', in_array(ActionKind::PolicyDissemination, $kinds, true)),
        ];

        if (! in_array($highest, [RiskLevel::High, RiskLevel::VeryHigh], true)) {
            return $lines;
        }

        $high = array_values(array_filter($rows, fn (array $row): bool => in_array($row['level'], [RiskLevel::High, RiskLevel::VeryHigh], true)));
        $written = count(array_filter($high, fn (array $row): bool => $row['written']));
        $lines[] = self::line('analysis', $written === count($high), $written.' de '.count($high).(count($high) === 1 ? ' fila' : ' filas'));
        $lines[] = self::line('awareness_campaign', in_array(ActionKind::AwarenessCampaign, $kinds, true));
        $assessment = in_array(ActionKind::SpecificAssessment, $kinds, true);
        $lines[] = self::line('specific_assessment', $assessment, optional: $highest === RiskLevel::High && ! $assessment);

        return $lines;
    }

    /**
     * @return array{key: string, text: string, status: 'covered'|'missing'|'optional', detail: string|null}
     */
    private static function line(string $key, bool $covered, ?string $detail = null, bool $optional = false): array
    {
        return ['key' => $key, 'text' => self::TEXTS[$key], 'status' => $covered ? 'covered' : ($optional ? 'optional' : 'missing'), 'detail' => $detail];
    }
}
