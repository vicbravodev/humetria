import { complianceNavItems, mainNavItems } from '@/lib/app-navigation';
import { listeningNavItems } from '@/lib/tool-navigation';
import { toUrl } from '@/lib/utils';
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
    isStaff: boolean,
    inCourse = 0,
): MobileTab[] {
    const main = mainNavItems(tenant, isStaff);
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

/**
 * The tab of the page the person is on, so the bar always shows where they are: Panel on the panel itself, Normas on any of
 * its standards (or a page below one), Quejas on its cases, and Más on anything else the whole menu holds.
 */
export function currentTab(
    tabs: MobileTab[],
    isCurrentUrl: (url: string) => boolean,
    isCurrentOrParentUrl: (url: string) => boolean,
): MobileTab['key'] {
    const within = (item: NavItem): boolean =>
        isCurrentOrParentUrl(toUrl(item.href)) ||
        (item.children ?? []).some((child) =>
            isCurrentOrParentUrl(toUrl(child.href)),
        );
    const current = tabs.find((tab) => {
        switch (tab.key) {
            case 'panel':
                return isCurrentUrl(toUrl(tab.item.href));
            case 'complaints':
                return within(tab.item);
            case 'compliance':
                return tab.items.some(within);
            default:
                return false;
        }
    });

    return current?.key ?? 'more';
}
