import {
    complianceNavItems,
    mainNavItems,
    type NavSection,
} from '@/lib/app-navigation';
import { platformNavItems } from '@/lib/staff-navigation';
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
 * The panel's pages in the groups the sidebar shows, the trail names and the ⌘K palette lists: the company's own pages,
 * then the three families the landing offers (Cumple la norma, Escucha a tu gente, Ordena tu operación) and the
 * platform for Humetría staff. A group the role has nothing to open in is left out.
 */
export function navSections({
    tenant,
    isStaff,
    isSuperadmin = false,
    inCourse = 0,
    home = 'Humetría',
}: Options): NavSection[] {
    const sections: NavSection[] = [
        { key: 'main', label: home, items: mainNavItems(tenant, isStaff) },
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
        {
            key: 'platform',
            label: 'Plataforma',
            items: isStaff ? platformNavItems(isSuperadmin) : [],
        },
    ];

    return sections.filter((section) => section.items.length > 0);
}
