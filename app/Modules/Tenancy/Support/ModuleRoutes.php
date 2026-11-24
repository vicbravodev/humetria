<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * The route names of each module, so one middleware can close them for a company that does not have it. The list is the
 * registry: ModuleGateTest fails with the name of any tenant.* route that is in none of them and not in `always()`.
 */
final class ModuleRoutes
{
    /**
     * @return array<string, list<string>> route name patterns, by module key.
     */
    public static function patterns(): array
    {
        return [
            'attendance' => ['tenant.attendance.*'],
            'nom035' => ['tenant.campaigns.*', 'tenant.nom035.*', 'tenant.interventions.*', 'tenant.disclosure.*'],
            'nom037' => ['tenant.telework.*'],
            'seating' => ['tenant.seating.*'],
            'complaints' => ['tenant.complaints.*'],
            'vacations' => ['tenant.vacations.*'],
            'org_chart' => ['tenant.org-chart.*'],
            'pulse' => ['tenant.pulse.*'],
            'climate' => ['tenant.climate.*'],
            'compensation' => ['tenant.compensation.*'],
            'sso' => ['tenant.sso.*'],
        ];
    }

    /**
     * What every company has whatever its modules: the panel, its people, their access and centers, Humi, and the page for
     * whoever may open nothing yet.
     *
     * @return list<string>
     */
    public static function always(): array
    {
        return [
            'tenant.dashboard',
            'tenant.areas.*',
            'tenant.assistant.*',
            'tenant.channels.*',
            'tenant.employees.*',
            'tenant.first-steps.*',
            'tenant.job-grades.*',
            'tenant.members.*',
            'tenant.no-access',
            'tenant.plan',
            'tenant.privacy.*',
            'tenant.roles.*',
            'tenant.work-centers.*',
            'tenant.worker-access.*',
        ];
    }
}
