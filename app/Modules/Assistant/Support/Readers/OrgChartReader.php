<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Organization\Contracts\Headcount;

/**
 * Organigrama by its active people and those the tree leaves out (no manager and nobody to lead): pending while there is
 * nobody or someone is out, up to date otherwise. Counts only, never who.
 */
final readonly class OrgChartReader implements ComplianceReader
{
    public function __construct(private Headcount $headcount, private PanelLinks $links) {}

    public function status(AssistantScope $scope): ComplianceStatus
    {
        $people = $this->headcount->ofCompany($scope->tenantId);
        $unplaced = $people === 0 ? 0 : $this->headcount->unplaced();

        return match (true) {
            $people === 0 => new ComplianceStatus('org_chart', 'Organigrama', 'pending', 'Pendiente', 'Aún no cargas a tu gente.', $this->links->orgChart()),
            $unplaced > 0 => new ComplianceStatus('org_chart', 'Organigrama', 'pending', 'Pendiente', 'Tu organigrama tiene '.trans_choice('{1} :count persona activa|[2,*] :count personas activas', $people).'; '.trans_choice('{1} :count no tiene jefe ni personas a cargo y no aparece|[2,*] :count no tienen jefe ni personas a cargo y no aparecen', $unplaced).' en el árbol.', $this->links->orgChartQuality()),
            default => new ComplianceStatus('org_chart', 'Organigrama', 'up_to_date', 'Al día', "Las {$people} personas activas están en el árbol del organigrama.", $this->links->orgChart()),
        };
    }
}
