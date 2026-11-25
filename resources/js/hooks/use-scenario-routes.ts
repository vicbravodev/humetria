import { usePage } from '@inertiajs/react';
import { useTenantSlug } from '@/hooks/use-tenant-slug';
import { index as compensationHome } from '@/routes/tenant/compensation';
import * as quickRoutes from '@/routes/tenant/compensation/quick';
import * as rosterRoutes from '@/routes/tenant/compensation/scenarios';
import type { BreadcrumbItem } from '@/types';

/** Whether the page is one of «Cotizador rápido» (the scenarios of an average payroll): its address is under /compensation/quick. */
export function useIsQuick(): boolean {
    return usePage().url.includes('/compensation/quick');
}

/** The routes of the scenarios of the kind the page is for: both kinds have the same pages under the same names. */
export function useScenarioRoutes() {
    return useIsQuick() ? quickRoutes : rosterRoutes;
}

/** The breadcrumbs of a page of the scenarios: where its kind starts («Cotizador rápido» or Compensación), then the page itself. */
export function useScenarioCrumbs(...tail: BreadcrumbItem[]): BreadcrumbItem[] {
    const tenantSlug = useTenantSlug();
    const quick = useIsQuick();

    return [
        quick
            ? { title: 'Cotizador rápido', href: quickRoutes.index(tenantSlug) }
            : { title: 'Compensación', href: compensationHome(tenantSlug) },
        ...tail,
    ];
}
