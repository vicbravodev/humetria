<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Str;

/**
 * Where a person lands in a company: the first page of the menu they may open, else the page that says they have no
 * access yet. Each candidate asks for the permission of its own route (RoutePermissions), so the landing never answers 403;
 * a permission of a module the company does not have is not the person's (CompanyRoles::effectivePermissions), and a union
 * never lands on a compliance tool, which it does not have.
 */
final readonly class StartRoute
{
    /**
     * The panel is built from the work centers (their names and where each stands in each route): seeing it is not enough
     * to land on it without seeing them. The Comisión and the handlers of complaints have the panel and not the centers, so
     * they start in their tool, as they always did.
     */
    public const string PANEL_ALSO_NEEDS = 'view-work-centers';

    /**
     * The pages the sidebar links to, in its order (resources/js/lib/nav-sections.ts: the company's own pages, then «Cumple
     * la norma», «Escucha a tu gente» and «Ordena tu operación»). The menu is drawn in the browser, so its order is copied
     * here, and tests/Fixtures/navigation/menu-order.json ties both (menu-order.test.ts, StartRouteMenuOrderTest). The
     * complaints entry opens the cases for whoever handles them and the channel for whoever only manages it.
     */
    public const array MENU = [
        'tenant.dashboard',
        'tenant.work-centers.index',
        'tenant.employees.index',
        'tenant.worker-access.index',
        'tenant.members.index',
        'tenant.plan',
        'tenant.privacy.edit',
        'tenant.campaigns.index',
        'tenant.telework.index',
        'tenant.seating.index',
        'tenant.attendance.index',
        'tenant.complaints.index',
        'tenant.complaints.channel.show',
        'tenant.climate.index',
        'tenant.pulse.index',
        'tenant.vacations.index',
        'tenant.org-chart.index',
        'tenant.compensation.index',
    ];

    public function __construct(private CompanyRoles $roles) {}

    public function for(User $user, Tenant $tenant): string
    {
        $permissions = $this->roles->effectivePermissions($user, $tenant);

        foreach (self::MENU as $route) {
            $needs = [RoutePermissions::for($route), ...($route === 'tenant.dashboard' ? [self::PANEL_ALSO_NEEDS] : [])];

            if (array_all($needs, fn (?string $permission): bool => $permission !== null && isset($permissions[$permission]))
                && ! ($tenant->isUnion() && Str::is(ComplianceRoutes::patterns(), $route))) {
                return $route;
            }
        }

        return 'tenant.no-access';
    }
}
