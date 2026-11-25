import {
    adminNavItems,
    complianceNavItems,
    mainNavItems,
    type NavSection,
} from '@/lib/app-navigation';
import {
    consoleNavItems,
    platformNavItems,
    salesNavItems,
} from '@/lib/staff-navigation';
import { listeningNavItems, operationsNavItems } from '@/lib/tool-navigation';
import type { NavTenant } from '@/types';

type Options = {
    tenant: NavTenant | null;
    isStaff: boolean;
    /** The superadmin also sees the platform's users and its audit trail. */
    isSuperadmin?: boolean | undefined;
    /** Evaluations in course, counted on NOM-035. */
    inCourse?: number;
    /** What the company's own pages are called in the trail: the company's name. */
    home?: string;
};

/**
 * The panel's pages in the groups the sidebar shows, the trail names and the ⌘K palette lists: the company's daily
 * pages, the three families the landing offers (Cumple la norma, Escucha a tu gente, Ordena tu operación), the
 * company's administration and, for Humetría staff in the console, Ventas and Plataforma. A group the role has
 * nothing to open in is left out.
 */
export function navSections({
    tenant,
    isStaff,
    isSuperadmin = false,
    inCourse = 0,
    home = 'Humetría',
}: Options): NavSection[] {
    const sections: NavSection[] = [
        {
            key: 'main',
            label: home,
            // Outside a company, staff's daily pages are the console's: the customers.
            items:
                tenant === null && isStaff
                    ? consoleNavItems(isSuperadmin)
                    : mainNavItems(tenant, isStaff),
        },
        // Right after the daily pages, where they always were: the menu's order is a contract with StartRoute.
        { key: 'admin', label: 'Administración', items: adminNavItems(tenant) },
        {
            key: 'compliance',
            label: 'Cumple la norma',
            items: complianceNavItems(tenant, inCourse),
        },
        {
            key: 'listening',
            label: 'Escucha a tu gente',
            items: listeningNavItems(tenant),
        },
        {
            key: 'operations',
            label: 'Ordena tu operación',
            items: operationsNavItems(tenant),
        },
        // Staff inside a company still reach the console from here; outside one, these are the main pages.
        {
            key: 'console',
            label: 'Consola',
            items:
                isStaff && tenant !== null ? consoleNavItems(isSuperadmin) : [],
        },
        {
            key: 'sales',
            label: 'Ventas',
            items: isStaff ? salesNavItems(isSuperadmin) : [],
        },
        {
            key: 'platform',
            label: 'Plataforma',
            items: isStaff ? platformNavItems(isSuperadmin) : [],
        },
    ];

    return sections.filter((section) => section.items.length > 0);
}
