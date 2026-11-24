<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Contracts\ComplianceReader;
use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Assistant\Support\PanelLinks;
use App\Modules\Tenancy\Contracts\CompanyAccess;
use App\Modules\Tenancy\Data\AreaAccess;
use App\Modules\Tenancy\Data\RoleCount;

/**
 * Roles y permisos. To whoever administers them (the owner): how many roles of its own the company has of the maximum, how
 * many people hold each role and how many have permissions besides it. To anyone else: only what they themselves can do,
 * area by area. Counts and levels, never who holds what.
 */
final readonly class AccessReader implements ComplianceReader
{
    public function __construct(private CompanyAccess $access, private PanelLinks $links) {}

    public function status(AssistantScope $scope): ComplianceStatus
    {
        if (! $scope->canSee('manage-members')) {
            return $this->line($this->own($this->access->areasOf($scope->tenantId, $scope->userId)), $this->links->dashboard());
        }

        $overview = $this->access->overview($scope->tenantId);

        return $this->line(implode(' ', [
            match ($overview->ownRoles) {
                0 => "Aún no tienes roles propios (máximo {$overview->maxOwnRoles}).",
                $overview->maxOwnRoles => "Tienes {$overview->ownRoles} de {$overview->maxOwnRoles} roles propios, el máximo: para crear otro, borra uno que no uses.",
                default => "Tienes {$overview->ownRoles} de {$overview->maxOwnRoles} roles propios.",
            },
            'Personas por rol: '.implode('; ', array_map(fn (RoleCount $role): string => "{$role->label}, {$role->people}", $overview->roles)).'.',
            $overview->withExtras === 0 ? 'Nadie tiene permisos además de su rol.' : trans_choice('{1} :count persona tiene|[2,*] :count personas tienen', $overview->withExtras).' permisos además de su rol.',
            'Tú administras todo lo que incluye tu plan, también los roles y las personas.',
        ]), $this->links->roles());
    }

    /**
     * @param  list<AreaAccess>  $areas
     */
    private function own(array $areas): string
    {
        $with = array_filter($areas, fn (AreaAccess $area): bool => $area->level !== 'Sin acceso' || $area->extras !== []);
        $without = array_diff_key($areas, $with);

        return implode(' ', array_filter([
            $with === [] ? 'Aún no tienes acceso a ninguna parte del panel.' : 'Tu acceso: '.implode('; ', array_map(
                fn (AreaAccess $area): string => "{$area->area}: {$area->level}".($area->extras === [] ? '' : ', y '.implode(' y ', $area->extras)),
                $with,
            )).'.',
            $without === [] ? null : 'Sin acceso: '.implode(', ', array_map(fn (AreaAccess $area): string => $area->area, $without)).'.',
        ]));
    }

    private function line(string $summary, string $link): ComplianceStatus
    {
        // Roles are not a norm to comply with: a neutral badge that only informs, never «Al día».
        return new ComplianceStatus('access', 'Roles y permisos', 'not_applicable', 'Informativo', $summary, $link);
    }
}
