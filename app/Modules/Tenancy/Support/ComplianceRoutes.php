<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * The route names of the compliance tools (NOM-035 with its intervention program, complaints channel and disclosure; NOM-037; Ley Silla; registro de jornada; organigrama; vacaciones; Humi and the work centers they hang from).
 * A union account never sees them: it answers 404, as if they did not exist. The list is the registry, and
 * UnionToolsTest fails with the name of any tenant.* route that is neither here nor allowed for a union.
 */
final class ComplianceRoutes
{
    /**
     * @return list<string>
     */
    public static function patterns(): array
    {
        return [
            'tenant.campaigns.*',
            'tenant.nom035.*',
            'tenant.telework.*',
            'tenant.seating.*',
            'tenant.assistant.*',
            'tenant.work-centers.*',
            'tenant.complaints.*',
            'tenant.interventions.*',
            'tenant.disclosure.*',
            'tenant.attendance.*',
            'tenant.org-chart.*',
            'tenant.vacations.*',
            'tenant.climate.*',
            'tenant.pulse.*',
        ];
    }
}
