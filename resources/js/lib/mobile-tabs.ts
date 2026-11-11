import { complianceNavItems, mainNavItems } from '@/lib/app-navigation';
import { listeningNavItems } from '@/lib/tool-navigation';
import type { NavItem, NavTenant } from '@/types';

export type MobileTab =
    | { key: 'panel' | 'complaints'; title: string; item: NavItem }
    | { key: 'compliance'; title: string; items: NavItem[] }
    | { key: 'more'; title: string };

/**
 * The phone's bottom bar (board 40, V4-Menu-Roles): Panel, Normas (the sidebar's "Cumple la norma"), Quejas and Más,
 * which opens the whole menu. Each tab appears only when the sidebar of that role has it, so it is role-aware the same way.
 */
export function mobileTabs(
    tenant: NavTenant | null,
    isSuperadmin: boolean,
    inCourse = 0,
): MobileTab[] {
    const main = mainNavItems(tenant, isSuperadmin);
    const compliance = complianceNavItems(tenant, inCourse);
    const panel = main.find((item) => item.title === 'Panel');
    const complaints =
        listeningNavItems(tenant).find((item) => item.title === 'Quejas') ??
        (tenant?.role === 'complaints' ? main[0] : undefined);

    const tabs: MobileTab[] = [];

    if (panel !== undefined) {
        tabs.push({ key: 'panel', title: 'Panel', item: panel });
    }

    if (compliance.length > 0) {
        tabs.push({
            key: 'compliance',
            title: 'Normas',
            items: compliance,
        });
    }

    if (complaints !== undefined) {
        tabs.push({ key: 'complaints', title: 'Quejas', item: complaints });
    }

    return [...tabs, { key: 'more', title: 'Más' }];
}
