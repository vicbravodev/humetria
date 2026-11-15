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

function isUnassigned(value: unknown): value is UnassignedPerson {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const fields = new Map<string, unknown>(Object.entries(value));

    return ['id', 'name', 'job_title', 'area', 'work_center'].every(
        (key) => typeof fields.get(key) === 'string',
    );
}

function numberOf(fields: Map<string, unknown>, key: string): number | null {
    const value = fields.get(key);

    return typeof value === 'number' ? value : null;
}

/** A page of the tray as the server answers it: only what has the shape of a person is kept; null when it is no page. */
export function trayPage(body: unknown): {
    people: UnassignedPerson[];
    total: number;
    nextPage: number | null;
} | null {
    if (typeof body !== 'object' || body === null) {
        return null;
    }

    const fields = new Map<string, unknown>(Object.entries(body));
    const data = fields.get('data');
    const total = numberOf(fields, 'total');
    const current = numberOf(fields, 'current_page');
    const last = numberOf(fields, 'last_page');

    if (
        !Array.isArray(data) ||
        total === null ||
        current === null ||
        last === null
    ) {
        return null;
    }

    return {
        people: data.filter(isUnassigned),
        total,
        nextPage: current < last ? current + 1 : null,
    };
}
