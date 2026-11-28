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

    /** Everything the panel asks for: its own permission (its route is exempt, the controller asks) and the centers. */
    public const array PANEL_NEEDS = ['view-dashboard', self::PANEL_ALSO_NEEDS]; // @pest-mutate-ignore: RemoveArrayItem

    /**
     * The pages the sidebar links to, in its order (resources/js/lib/nav-sections.ts: the company's own pages, then «Cumple
     * la norma», «Escucha a tu gente» and «Ordena tu operación»). The menu is drawn in the browser, so its order is copied
     * here, and tests/Fixtures/navigation/menu-order.json ties both (menu-order.test.ts, StartRouteMenuOrderTest). The
     * complaints entry opens the cases for whoever handles them and the channel for whoever only manages it.
     */
    public const array MENU = [
        'tenant.dashboard', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.work-centers.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.employees.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.worker-access.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.members.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.roles.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.plan', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.privacy.edit', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.campaigns.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.telework.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.seating.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.attendance.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.complaints.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.complaints.channel.show', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.climate.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.pulse.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.vacations.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.org-chart.index', // @pest-mutate-ignore: RemoveArrayItem
        'tenant.compensation.index', // @pest-mutate-ignore: RemoveArrayItem
    ];

    public function __construct(private CompanyRoles $roles) {}

    public function for(User $user, Tenant $tenant): string
    {
        $permissions = $this->roles->effectivePermissions($user, $tenant);

        foreach (self::MENU as $route) {
            // A union has no compliance panel: its panel is Compensación.
            $route = $tenant->isUnion() && $route === 'tenant.dashboard' ? 'tenant.compensation.index' : $route;
            $has = fn (string $permission): bool => isset($permissions[$permission]);
            $opens = $route === 'tenant.dashboard' ? array_all(self::PANEL_NEEDS, $has) : array_any(RoutePermissions::anyOf($route), $has);

            if ($opens
                && ! ($tenant->isUnion() && Str::is(ComplianceRoutes::patterns(), $route))) {
                return $route;
            }
        }

        return 'tenant.no-access';
    }
}
