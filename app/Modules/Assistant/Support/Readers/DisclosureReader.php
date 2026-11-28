<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Disclosure\Contracts\DisclosureStanding;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;

/**
 * Difusión of NOM-035 by its policy of prevention (num. 5.1): up to date when a published version reached every center in
 * use, pending otherwise.
 */
final readonly class DisclosureReader implements ComplianceReader
{
    public function __construct(private WorkCenters $workCenters, private DisclosureStanding $disclosure, private PanelLinks $links) {}

    public function status(AssistantScope $scope): ComplianceStatus
    {
        $ids = array_values(array_map(fn (WorkCenterSummary $center): string => $center->id, array_filter($this->workCenters->all(), fn (WorkCenterSummary $center): bool => ! $center->archived)));
        $reached = $ids === [] ? 0 : count($this->disclosure->withPolicy($ids));

        return match (true) { // @pest-mutate-ignore: TrueToFalse
            $reached === 0 => new ComplianceStatus('disclosure', 'Difusión', 'pending', 'Pendiente', 'Aún no publicas tu política de prevención de riesgos psicosociales.', $this->links->policy()),
            $reached < count($ids) => new ComplianceStatus('disclosure', 'Difusión', 'pending', 'Pendiente', "Tu política de prevención está publicada en {$reached} de ".trans_choice('{1} :count centro de trabajo|[2,*] :count centros de trabajo', count($ids)).'.', $this->links->policy()),
            default => new ComplianceStatus('disclosure', 'Difusión', 'up_to_date', 'Al día', 'Tu política de prevención está publicada en '.trans_choice('{1} tu centro de trabajo|[2,*] tus :count centros de trabajo', $reached).'.', $this->links->policy()),
        };
    }
}
