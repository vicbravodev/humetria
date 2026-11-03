<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\RequirementState;
use App\Modules\Results\Data\RiskBreakdown;

/**
 * The requirements the board judges for a work center (criterio C-50 onward), from what is recorded about it: the single
 * place that says when each one is met, has a notice or is missing. It counts what is recorded; it never certifies.
 */
final readonly class Requirements
{
    private const array INCISOS = ['a', 'b', 'c'];

    /**
     * @return list<Requirement>
     */
    public function for(CenterFacts $facts): array
    {
        return [
            ...$this->policy($facts->policy),
            ...$this->delivery($facts),
            ...$this->block($facts, 'D4', DocumentKind::ViolenceMeasures),
            ...$this->block($facts, 'D5', DocumentKind::PreventionMeasures),
            ...$this->block($facts, 'D6', DocumentKind::ComplaintMechanism),
            ...$this->results($facts),
            ...$this->block($facts, 'D8', DocumentKind::HealthEffects),
            ...$this->revision($facts),
            ...$this->link($facts),
        ];
    }

    /**
     * D1 and D2: the policy in writing (5.1) and the incisos it declares.
     *
     * @return list<Requirement>
     */
    private function policy(?PolicyFact $policy): array
    {
        if ($policy === null) {
            return [new Requirement('D1', '5.1', 'Política de prevención por escrito', RequirementState::Missing, 'Aún no hay una política publicada para este centro.')];
        }

        $missing = array_values(array_diff(self::INCISOS, $policy->covers));
        $declares = match (count($missing)) {
            0 => null,
            1 => "La versión {$policy->version} no declara el inciso {$missing[0]} del numeral 5.1.",
            default => "La versión {$policy->version} no declara los incisos ".implode(', ', array_slice($missing, 0, -1)).' y '.$missing[array_key_last($missing)].' del numeral 5.1.',
        };

        return [
            new Requirement('D1', '5.1', 'Política de prevención por escrito', RequirementState::Met, "Versión {$policy->version}, publicada el ".Days::moment($policy->publishedAt).'.'),
            new Requirement('D2', '5.1 a, b y c', 'Declara la prevención de los factores de riesgo, de la violencia laboral y la promoción de un entorno favorable', $declares === null ? RequirementState::Met : RequirementState::Notice, $declares),
        ];
    }

    /**
     * D3: the policy in force is in front of the people (the page is active or a physical means covered it) and, as a notice and
     * not a requirement, how many confirmed it (criteria C-52 and C-53). Not judged without a policy: D1 already says it.
     *
     * @return list<Requirement>
     */
    private function delivery(CenterFacts $facts): array
    {
        if ($facts->policy === null) {
            return [];
        }

        if (! $facts->delivered('a')) {
            return [new Requirement('D3', '5.1, 5.7 a', 'La política está a la vista de las personas', RequirementState::Missing, 'La política no está a la vista de las personas: publique la página o registre un medio físico (cartel, boletín, folleto).')];
        }

        $acks = $facts->acknowledgments ?? ['active' => 0, 'confirmed' => 0];

        if ($acks['active'] === 0) {
            return [new Requirement('D3', '5.1, 5.7 a', 'La política está a la vista de las personas', RequirementState::Met, null)];
        }

        $said = "{$acks['confirmed']} de {$acks['active']} ".($acks['active'] === 1 ? 'persona confirmó' : 'personas confirmaron');
        $all = $acks['confirmed'] === $acks['active'];

        return [new Requirement('D3', '5.1, 5.7 a', 'La política está a la vista de las personas', $all ? RequirementState::Met : RequirementState::Notice, $all ? "{$said}." : "{$said}; la norma no pide el acuse.")];
    }

    /**
     * D10: the link of the page expires soon (num. 10.4: the evidence must stay available). Judged for the company, shown in
     * every center, and only when it is near; the page is permanent unless RH sets a day.
     *
     * @return list<Requirement>
     */
    private function link(CenterFacts $facts): array
    {
        return $facts->linkExpiresOn === null ? [] : [new Requirement('D10', '10.4', 'Vigencia del enlace de la página', RequirementState::Notice, "El enlace de la página vence el {$facts->linkExpiresOn}: después ya no abrirá. Cambie el vencimiento o genere otro antes.")];
    }

    /**
     * D4, D5, D6 and D8: a block of 5.7 has something published for the center (a text the company wrote or what another tool
     * provides) and it is in front of the people (criterio C-52). The center is judged whether or not it has a policy.
     *
     * @return list<Requirement>
     */
    private function block(CenterFacts $facts, string $code, DocumentKind $kind): array
    {
        $numeral = "5.7 {$kind->inciso()}";

        if (! $facts->hasContent($kind->inciso())) {
            return [new Requirement($code, $numeral, $kind->label(), RequirementState::Missing, "Aún no hay información publicada sobre {$kind->topic()}.")];
        }

        return [$facts->delivered($kind->inciso())
            ? new Requirement($code, $numeral, $kind->label(), RequirementState::Met, null)
            : new Requirement($code, $numeral, $kind->label(), RequirementState::Missing, 'Está publicada, pero no está a la vista de las personas: publique la página o registre un medio físico que la cubra.'),
        ];
    }

    /**
     * D7: the results of the latest closed evaluation are published to the people (num. 5.7 e and 7.8; criterio C-54). It does not
     * apply to a center of up to 15 people nor to an evaluation of only the Guía I. Publishing is RH's act, so a closed evaluation
     * nobody published is missing.
     *
     * @return list<Requirement>
     */
    private function results(CenterFacts $facts): array
    {
        $fact = $facts->results;
        $row = fn (RequirementState $state, ?string $detail): array => [new Requirement('D7', '5.7 e, 7.8', 'Resultados disponibles para las personas', $state, $detail)];

        if ($fact === null) {
            return [];
        }

        if (! $fact->applies) {
            return $row(RequirementState::NotApplicable, $fact->latest === null ? 'El numeral 7.8 no aplica a centros de hasta 15 personas (numeral 2 a).' : 'La evaluación solo aplicó la Guía I: no hay cifras que publicar.');
        }

        if ($fact->latest === null) {
            return $row(RequirementState::Missing, 'Aún no hay una evaluación cerrada.');
        }

        if (! $fact->latestPublished) {
            return $fact->shown === null
                ? $row(RequirementState::Missing, "La evaluación cerrada el {$fact->latest->closedOn} no se ha publicado a las personas.")
                : $row(RequirementState::Notice, "Hay una evaluación cerrada el {$fact->latest->closedOn}, más reciente que la publicada, sin publicar.");
        }

        return $fact->shownBreakdown?->state === RiskBreakdown::BELOW_MINIMUM
            ? $row(RequirementState::Notice, 'Publicada, pero con muy pocas respuestas: las cifras no se muestran para proteger la confidencialidad.')
            : $row(RequirementState::Met, null);
    }

    /**
     * D9: Tablas 4 y 7 ask to review the policy and reinforce its disclosure when the risk is medium or more, and for more
     * disclosure when it is low (criterio C-56). Only when Results can say (with too few questionnaires it cannot, and nothing is
     * said), and only while the policy in force is older than the closing of the evaluation.
     *
     * @return list<Requirement>
     */
    private function revision(CenterFacts $facts): array
    {
        $fact = $facts->results;
        $breakdown = $fact?->latestBreakdown;

        if ($fact === null || $fact->latest === null || $breakdown === null || $breakdown->state !== RiskBreakdown::AVAILABLE || $facts->policy === null) {
            return [];
        }

        $policyDay = $facts->policy->publishedAt->setTimezone(config()->string('app.display_timezone'))->toDateString();

        if ($fact->latest->closedDate === null || $policyDay >= $fact->latest->closedDate) {
            return [];
        }

        $closed = "La evaluación cerrada el {$fact->latest->closedOn}";

        return match (true) {
            $breakdown->needingProgram() !== [] => [new Requirement('D9', 'Tablas 4 y 7', 'Revisar la política tras la evaluación', RequirementState::Notice, "{$closed} tiene riesgo medio o más y la política vigente es anterior: revise la política y refuerce su aplicación y difusión.")],
            self::hasLowRisk($breakdown) => [new Requirement('D9', 'Tablas 4 y 7', 'Revisar la política tras la evaluación', RequirementState::Notice, "{$closed} tiene riesgo bajo y la política vigente es anterior: dé mayor difusión a la política.")],
            default => [],
        };
    }

    /** Whether some questionnaire of the final score, a category or a domain is in Bajo (the second level, after Nulo). */
    private static function hasLowRisk(RiskBreakdown $breakdown): bool
    {
        return ($breakdown->final[1] ?? 0) > 0
            || array_any([...$breakdown->categories, ...$breakdown->domains], fn ($group): bool => ($group->levels[1] ?? 0) > 0);
    }
}
