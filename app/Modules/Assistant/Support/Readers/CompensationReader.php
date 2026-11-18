<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Compensation\Contracts\CompensationStanding;

/**
 * Compensación, which every company has: how many people the cost of the contract covers, the categories without a salary
 * and the scenarios. Pending with nobody loaded, in progress while something cannot be costed, up to date otherwise.
 * Never a salary, an amount nor a person; and nothing at all to whoever may not see compensation.
 */
final readonly class CompensationReader implements ComplianceReader
{
    public function __construct(private CompensationStanding $compensation, private PanelLinks $links) {}

    public function status(AssistantScope $scope): ComplianceStatus
    {
        if (! $scope->canSee('view-compensation')) {
            return new ComplianceStatus('compensation', 'Compensación', 'not_applicable', 'No aplica', 'Compensación solo la ven el dueño y RH.', $this->links->dashboard());
        }

        $contract = $this->compensation->current();

        if ($contract->people === 0) {
            return new ComplianceStatus('compensation', 'Compensación', 'pending', 'Pendiente', 'Aún no cargas a tu gente para costear el contrato colectivo.', $this->links->compensation());
        }

        $complete = $contract->costed === $contract->people && $contract->gradesWithoutSalary === 0;
        $covers = $contract->costed === $contract->people
            ? trans_choice('{1} El costo cubre a tu única persona|[2,*] El costo cubre a las :count personas', $contract->people)
            : "El costo cubre a {$contract->costed} de ".trans_choice('{1} :count persona|[2,*] :count personas', $contract->people);
        $grades = $contract->gradesWithoutSalary === 0 ? '.' : '; '.trans_choice('{1} :count categoría no tiene salario|[2,*] :count categorías no tienen salario', $contract->gradesWithoutSalary).'.';
        $scenarios = $contract->scenarios === 0 ? 'Aún no armas ningún escenario.' : 'Tienes '.trans_choice('{1} :count escenario activo|[2,*] :count escenarios activos', $contract->scenarios).'.';
        $summary = "{$covers}{$grades} {$scenarios}";

        return $complete
            ? new ComplianceStatus('compensation', 'Compensación', 'up_to_date', 'Al día', $summary, $this->links->compensation())
            : new ComplianceStatus('compensation', 'Compensación', 'in_progress', 'En curso', $summary, $this->links->compensation());
    }
}
