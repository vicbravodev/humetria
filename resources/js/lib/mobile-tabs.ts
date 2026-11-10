import { complianceNavItems, mainNavItems } from '@/lib/app-navigation';
import type { NavItem, NavTenant } from '@/types';

export type MobileTab =
    | { key: 'panel' | 'complaints'; title: string; item: NavItem }
    | { key: 'compliance'; title: string; items: NavItem[] }
    | { key: 'more'; title: string };

/**
 * The phone's bottom bar (board 40, V4-Menu-Roles): Panel, Cumplimiento (the standards the role sees), Quejas and Más,
 * which opens the whole menu. Each tab appears only when the sidebar of that role has it, so it is role-aware the same way.
 */
export function mobileTabs(
    tenant: NavTenant | null,
    isStaff: boolean,
    inCourse = 0,
): MobileTab[] {
    const main = mainNavItems(tenant, isStaff);
    const compliance = complianceNavItems(tenant, inCourse);
    const panel = main.find((item) => item.title === 'Panel');
    const complaints =
        compliance.find((item) => item.title === 'Quejas') ??
        (tenant?.role === 'complaints' ? main[0] : undefined);
    const standards = compliance.filter((item) => item.title !== 'Quejas');

    const tabs: MobileTab[] = [];

    if (panel !== undefined) {
        tabs.push({ key: 'panel', title: 'Panel', item: panel });
    }

    if (standards.length > 0) {
        tabs.push({
            key: 'compliance',
            title: 'Cumplimiento',
            items: standards,
        });
    }

    if (complaints !== undefined) {
        tabs.push({ key: 'complaints', title: 'Quejas', item: complaints });
    }

    return [...tabs, { key: 'more', title: 'Más' }];
}
