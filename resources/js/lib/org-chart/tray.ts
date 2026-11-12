import type { OrgNode, UnassignedPerson } from '@/types';

/** Someone of the tray as the chart draws a person once they have a manager: with nobody below them. */
export function asOrgNode(person: UnassignedPerson): OrgNode {
    return {
        ...person,
        active: true,
        direct_reports: 0,
        total_reports: 0,
        flag: null,
        matches: true,
        manager_inactive: false,
        absent_today: false,
    };
}

/** The address of a page of the tray; what was typed goes only when it is something. */
export function trayUrl(base: string, search: string, page: number): string {
    const query = new URLSearchParams({ page: String(page) });

    if (search.trim() !== '') {
        query.set('q', search.trim());
    }

    return `${base}?${query.toString()}`;
}
