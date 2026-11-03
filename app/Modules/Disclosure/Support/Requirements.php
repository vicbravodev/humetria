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
        return [...$this->policy($facts->policy)];
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
}
