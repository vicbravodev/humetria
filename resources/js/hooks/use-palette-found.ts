import { router } from '@inertiajs/react';
import { usePaletteOptions } from '@/hooks/use-palette-options';
import type { NavTenant } from '@/lib/app-navigation';
import {
    companyCommands,
    type PaletteCommand,
    peopleCommands,
} from '@/lib/palette-commands';
import { options as companyOptions } from '@/routes/companies';
import { options as peopleOptions } from '@/routes/tenant/org-chart/people';

/**
 * What ⌘K finds on the server for the text typed: inside a company, its people (for whoever manages the organization);
 * outside one, for Humetría's staff, the companies by name.
 */
export function usePaletteFound(
    query: string,
    tenant: NavTenant | null,
    isStaff: boolean,
): PaletteCommand[] {
    const visit = (href: Parameters<typeof router.visit>[0]) =>
        router.visit(href);
    const people = usePaletteOptions(
        tenant?.can.manage_organization === true
            ? peopleOptions.url(tenant.slug)
            : null,
        query,
    );
    const companies = usePaletteOptions(
        isStaff && tenant === null ? companyOptions.url() : null,
        query,
    );

    return [
        ...companyCommands(companies, visit),
        ...(tenant === null ? [] : peopleCommands(people, tenant.slug, visit)),
    ];
}
