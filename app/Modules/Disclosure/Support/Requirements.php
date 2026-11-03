<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\RequirementState;

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
        return [...$this->policy($facts->policy), ...$this->delivery($facts)];
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
}
